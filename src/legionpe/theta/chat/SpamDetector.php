<?php

/*
 * LegionPE Theta
 *
 * Copyright (C) 2015 PEMapModder and contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PEMapModder
 */

namespace legionpe\theta\chat;

use legionpe\theta\lang\Phrases;
use legionpe\theta\Session;

class SpamDetector{
	/** @var Session */
	private $session;
	/** @var ChatLogEntry[] */
	private $chatLog = [];
	public function __construct(Session $session){
		$this->session = $session;
	}
	public function censor(&$message){
		$this->chatLog[] = new ChatLogEntry($message);
		if(count($this->chatLog) > 5){
			array_shift($this->chatLog);
		}
		if(!$this->detectBadWords($message)){
			return false;
		}
		$this->detectAds($message);
		if(count($this->chatLog) < 5){
			return true;
		}
		$lengthSum = 0;
		foreach($this->chatLog as $log){
			$lengthSum += $log->length;
		}
		if($lengthSum < 10){
			$this->session->send(Phrases::CHAT_BLOCKED_TOO_SHORT);
			return false;
		}
		$cpm = $lengthSum / (microtime(true) - $this->chatLog[0]->time) * 60;
		if($cpm > 250){
			$this->session->send(Phrases::CHAT_BLOCKED_TOO_FAST, ["cpm" => $cpm]);
			return false;
		}
		if(microtime(true) - $this->chatLog[3]->time < 2){
			$this->session->send(Phrases::CHAT_BLOCKED_TOO_FREQUENT);
			return false;
		}
		return true;
	}
	public function detectBadWords($string){
		$badWords = $this->session->getMain()->getBadWords();
		$string = preg_replace('#[^A-Za-z0-9]#i', "", $string);
		$string = str_replace("0", "o", $string);
		$string = str_replace("4", "a", $string);
		$string = str_replace("3", "e", $string);
		foreach($badWords as $word){
			if(stripos(str_replace("1", "i", $string), $word) !== false or stripos(str_replace("1", "l", $string), $word) !== false){
				// TODO warn
				return false;
			}
		}
		return true;
	}
	public function detectAds(&$string){
		$string = preg_replace_callback('%([0-9]{1,3}\.){3}[0-9]{1,3}%i', function ($match){
			return str_repeat("-", strlen($match));
		}, $string);
		$string = str_replace([".lbsg.net", ".leet.cc"], "", $string);
		$string = preg_replace_callback('%(http[s]?://)?(([A-Za-z0-9\-]+\.){2,}([A-Za-z0-9\-]{1,3}))%', function ($match){
			if(strlen($match[1]) > 6){
				return $match[0];
			}
			$domain = $match[3] . $match[4];
			if(in_array($domain, $this->session->getMain()->getApprovedDomains())){
				return $match[0];
			}
			return str_repeat("-", strlen($match[0]));
		}, $string);
	}
}

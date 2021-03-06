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

namespace legionpe\theta\query;

use legionpe\theta\BasePlugin;
use legionpe\theta\chat\ChatType;
use legionpe\theta\config\Settings;
use legionpe\theta\utils\FireSyncChatQueryTask;
use pocketmine\Server;

class SyncChatQuery extends AsyncQuery{
	private $class;
	private $lastId;
	private $fetchedMaxId;
	public function __construct(BasePlugin $main, FireSyncChatQueryTask $task){
		$this->class = Settings::$LOCALIZE_CLASS;
		$task->canFireNext = false;
		$this->lastId = $main->getInternalLastChatId();
		parent::__construct($main);
	}
	public function onPreQuery(\mysqli $db){
		if($this->lastId === null){
			$r = $db->query("SELECT MAX(id)AS id FROM chat");
			$this->fetchedMaxId = $r->fetch_assoc()["id"];
			$r->close();
		}
	}
	public function getQuery(){
		return $this->lastId === null ? ("SELECT json,src,msg FROM chat WHERE (type=" . ChatType::MUTE_CHAT . ") AND unix_timestamp() - unix_timestamp(creation) <= 86400") : "SELECT id,unix_timestamp(creation)AS creation,src,msg,type,class,json FROM chat WHERE id>$this->lastId AND (class=0 OR class=$this->class)";
	}
	public function getResultType(){
		return self::TYPE_ALL;
	}
	public function getExpectedColumns(){
		return $this->lastId === null ? [
			"id" => self::COL_INT
		] : [
			"id" => self::COL_INT,
			"creation" => self::COL_UNIXTIME,
			"src" => self::COL_STRING,
			"msg" => self::COL_STRING,
			"type" => self::COL_INT,
			"class" => self::COL_INT,
			"json" => self::COL_STRING
		];
	}
	public function onCompletion(Server $server){
		$main = BasePlugin::getInstance($server);
		$result = $this->getResult();
		if($this->lastId === null){
			$main->setInternalLastChatId($this->fetchedMaxId);
			$result = $this->getResult()["result"];
			foreach($result as $row){
				$type = ChatType::get($main, ChatType::MUTE_CHAT, $result["src"], $result["msg"], Settings::$LOCALIZE_CLASS, json_decode($row["json"]));
				$type->execute();
			}
		}elseif($result["resulttype"] === self::TYPE_ALL){
			$result = $this->getResult()["result"];
			foreach($result as $row){
				$row["json"] = json_decode($row["json"], true);
				$main->handleChat($row);
			}
		}
		$task = $main->getFireSyncChatQueryTask();
		$task->canFireNext = true;
	}
	protected function reportDebug(){
		return false;
	}
}

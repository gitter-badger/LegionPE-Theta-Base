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

namespace legionpe\theta\command\session\team;

use legionpe\theta\BasePlugin;
use legionpe\theta\chat\ChatType;
use legionpe\theta\command\SessionCommand;
use legionpe\theta\config\Settings;
use legionpe\theta\lang\Phrases;
use legionpe\theta\Session;

class TeamQuitCommand extends SessionCommand{
	public function __construct(BasePlugin $main){
		parent::__construct($main, "tq", "Quit a team", "/tq");
	}
	protected function run(array $args, Session $sender){
		if($sender->getTeamId() === -1){
			return $sender->translate(Phrases::CMD_TEAM_ERR_NOT_IN_TEAM);
		}
		if(!$sender->confirmQuitTeam){
			$sender->confirmQuitTeam = true;
			return $sender->getTeamRank() === Settings::TEAM_RANK_LEADER ?
				$sender->translate(Phrases::CMD_TEAM_QUIT_WARNING_LEADER) :
				($sender->getTeamRank() === Settings::TEAM_RANK_JUNIOR ?
					$sender->translate(Phrases::CMD_TEAM_QUIT_WARNING_JUNIOR) :
					$sender->translate(Phrases::CMD_TEAM_QUIT_WARNING_NORMAL));
		}
		$type = ChatType::get($this->getMain(), ChatType::TEAM_CHAT, "Network", "%tr%" . Phrases::CMD_TEAM_QUITTED, Settings::CLASS_ALL, [
			"tid" => $sender->getTeamId(),
			"teamName" => $sender->getTeamName(),
			"ign" => "Network",
			"data" => [
				"name" => $sender->getPlayer()->getName(),
				"teamname" => $sender->getTeamName(),
			]
		]);
		$type->push();
		$sender->setLoginDatum("tid", -1);
		$sender->setLoginDatum("teamrank", 0);
		$sender->setLoginDatum("teamjoin", 0);
		$sender->setLoginDatum("teampts", 0);
		return true;
	}
}

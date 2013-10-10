<?php

/*

	Copyright 2013 Jindrich Dolezy (dzindra)

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

		http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.

*/


require_once "config.php";

function listPools(RPCMiner $miner) {
	$pools = $miner->pools();

	$poolsConverted = array();
	foreach ($pools as $pool) {
		$poolsConverted[] = // add to array
			convert($pool, array( // converted pool info
				'POOL' => 'id',
				'URL' => 'url',
				'Status' => 'status',
				'Priority' => 'priority',
				'User' => 'userName',
			));
	}
	return $poolsConverted;
}

function main(RPCMiner $miner) {
	$cmd = param("command");
	switch ($cmd) {
		case "add":
			$url = param('url');
			$user = param('user');
			$pass = param('pass');
			if ($url && $user && $pass) {
				$result = $miner->addPool($url, $user, $pass);
				$miner->save();
				return array("message" => $result, "pools" => listPools($miner));
			}
			break;

		case "remove":
			$id = (int)param('id', -1);
			if ($id != -1) {
				$result = $miner->deletePool($id);
				$miner->save();
				return array("message" => $result, "pools" => listPools($miner));
			}
			break;

		case "top":
			$id = (int)param('id', -1);
			if ($id != -1) {
				$result = $miner->topPool($id);
				$miner->save();
				return array("message" => $result, "pools" => listPools($miner));
			}
			break;

		case "disable":
		case "enable":
			$id = (int)param('id', -1);
			if ($id != -1) {
				$result = $miner->enablePool($id, $cmd == 'enable');
				$miner->save();
				return array("message" => $result, "pools" => listPools($miner));
			}
			break;

		case "list":
			return array("pools" => listPools($miner));
			break;

		default:
			throw new Exception("No command specified or command unknown (" . param("command") . ")", -100);
	}

	throw new Exception("Missing required parameter", -101);
}

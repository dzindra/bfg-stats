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

// this script outputs combined miner and pool statistics as json

require_once "config.php";

function main(RPCMiner $miner) {
	// fetch notify info
	$notify = $miner->notify();

	// fetch devices info
	$devices = $miner->devices();

	// fetch pools info
	$pools = $miner->pools();

	// convert json data from bfgminer notify and devs calls into one array with changed keys
	// this assumes same devices are present on same indexes in both arrays
	$devicesConverted = array();
	for ($i = 0; $i < count($devices); $i++) {
		$devicesConverted[] = // add to array
			convert($devices[$i], array( // converted device info
				'Name' => 'name',
				'ID' => 'id',
				'Status' => 'status',
				'MHS av' => 'mhsAvg',
				'MHS 5s' => 'mhs5s',
				'Utility' => 'utility',
				'Last Share Time' => 'lastShareTime',
				'Accepted' => 'accepted',
				'Rejected' => 'rejected',
				'Device Rejected%' => 'rejectedPct',
				'Hardware Errors' => 'hwErrors',
				'Device Hardware%' => 'hwErrorsPct'))
			+ convert($notify[$i], array( // merged with converted notify info
				'Last Not Well' => 'notWellTime',
				'Reason Not Well' => 'notWellReason'
			));
	}

	$poolsConverted = array();
	foreach ($pools as $pool) {
		$poolsConverted[] = // add to array
			convert($pool, array( // converted pool info
				'POOL' => 'id',
				'URL' => 'url',
				'Status' => 'status',
				'Priority' => 'priority',
				'Accepted' => 'accepted',
				'Rejected' => 'rejected',
				'Pool Rejected%' => 'rejectedPct',
				'Stale' => 'stale',
				'Pool Stale%' => 'stalePct',
				'User' => 'userName',
				'Last Share Time' => 'lastShareTime'
			));
	}

	// return $results array (it will be printed as json)
	return array('devices' => $devicesConverted, 'pools' => $poolsConverted);
}

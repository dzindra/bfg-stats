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


// modify values here
// do not remove any keys!

$config = array(
	'miner' => array(
		'address' => '127.0.0.1',
		'port' => 4028,
		'timeout' => 5, // seconds
	),
	'debug' => false // true to turn on debug mode
);


// do not modify below this line if you do not know what you are doing
//---------------------------------------------------------------------------------

require_once "RPCMiner.php";

/**
 * Pick keys in $convert array from $input array and place them into new array under
 * new keys (which are corresponding $convert values).
 *
 * Example:
 *
 * $input = array('key'=>'Apple', 'key2'=>'Bannana', 'key3'=>'Orange');
 * $convert = array('key'=>'appleKey', 'key2'=>'bannanaKey', 'anotherKey'=>'strawberryKey');
 * $output = convert($input, $convert)
 *
 * $output will contain array('appleKey'=>'Apple','bannanaKey'=>'Bannana');
 *
 *
 * @param array $input input array (key=>value). This array is searched for keys present in $convert
 * @param array $convert specifies what keys to search for in $input and how to rename them
 * @return array converted array
 */
function convert(array $input, array $convert) {
	$result = array();
	foreach ($convert as $key => $value) {
		if (isset($input[$key]))
			$result[$value] = $input[$key];
	}
	return $result;
}

/**
 * Outputs json formatted $data (or $data dump if $debug==true)
 *
 * @param array $data
 * @param bool $debug
 */
function output(array $data, $debug = false) {
	if ($debug) {
		echo "<pre>";
		print_r($data);
		echo "</pre>";
	} else {
		@header("Content-Type: text/json");
		echo json_encode($data);
	}
}

/**
 * Calls supplied callback and provides it with configured RPCMiner class instance.
 * Outputs callback's return array as JSON along with success status.
 * Exceptions thrown in callback are caught and printed as json with failure status.
 *
 * @param $callback
 */
function json($callback) {
	global $config;

	$debug = $config['debug'];
	if (isset($_REQUEST['debug'])) {
		$debug = true;
	}

	try {
		// check callback
		if (!is_callable($callback))
			throw new Exception("Supplied callback not callable");

		// construct miner
		$miner = new RPCMiner($config['miner']['address'], $config['miner']['port'], $config['miner']['timeout']);

		// call supplied callback
		$result = $callback($miner, $debug);

		// check result
		if (!is_array($result))
			throw new Exception("Return from callback should be array!");

		output($result + array('status' => 1), $debug);
	} catch (Exception $e) {
		// when exception is thrown, respond with error status and proper message
		output(array('status' => 0, 'code' => $e->getCode(), 'error' => $e->getMessage()), $debug);
	}
}

// calls main function if not blocked
if (empty($doNotCallMain))
	json('main');
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


/**
 * Class RPCMinerException is throws from RPCMiner when connection fails
 */
class RPCMinerException extends Exception {
	public function __construct($message = "", $code = 0) {
		parent::__construct((string)$message, (int)$code);
	}
}


/**
 * Class encapsulates one connection to bfgminer. Can send and receive json-formatted API commands.
 */
class RPCMiner {
	/** @var string */
	private $server;
	/** @var int */
	private $port;
	/** @var int */
	private $timeout;

	/** @var resource */
	private $stream;


	/**
	 * Constructs miner connected to specified server and port with connection timeout. Defaults
	 * to local miner on standard port
	 *
	 * @param string $server
	 * @param int $port
	 * @param int $timeout
	 */
	public function __construct($server = '127.0.0.1', $port = 4028, $timeout = 5) {
		$this->server = $server;
		$this->port = $port;
		$this->timeout = $timeout;

		$this->stream = false;
	}


	/**
	 * Connects to miner, throws exception if something fails.
	 *
	 * @throws RPCMinerException
	 */
	protected function connect() {
		if ($this->stream)
			return;

		$stream = @fsockopen("tcp://$this->server", $this->port, $code, $message, $this->timeout);
		if ($stream === false)
			throw new RPCMinerException("Error connecting to server - $message ($code)", $code);

		$this->stream = $stream;
	}


	/**
	 * Disconnects from miner. Silently ignores all errors.
	 */
	protected function disconnect() {
		fclose($this->stream);
		$this->stream = false;
	}


	/**
	 * Serializes passed in array to json and sends it to miner.
	 *
	 * @param array $data array of data for sending to miner
	 *
	 * @throws RPCMinerException if connection to miner fails
	 */
	protected function write($data) {
		$json = json_encode($data);
		$expected = strlen($json);

		$written = @fwrite($this->stream, $json);

		if ($written != $expected)
			throw new RPCMinerException("Unable to write $expected bytes, only $written written", -1);
	}


	/**
	 * Reads response from the miner and tries to decode it as json. Returns decoded data.
	 *
	 * @return array decoded data from miner
	 *
	 * @throws RPCMinerException if connection to miner fails or malformed response is received
	 */
	protected function read() {
		$data = false;
		while (!@feof($this->stream)) {
			$buffer = @fread($this->stream, 8192);
			if ($buffer === false)
				throw new RPCMinerException("Read error");

			$data .= $buffer;
		}
		if ($data === false)
			throw new RPCMinerException("No data read, connection closed", -4);

		$json = json_decode(trim($data), true);
		if ($json === null)
			throw new RPCMinerException("Unable to parse response ($data)", -2);

		return $json;
	}


	protected function escape($input) {
		return strtr($input, array('\\' => '\\\\', ',' => '\,'));
	}


	protected function fetch(array $array, $key, $default) {
		return isset($array[$key]) ? $array[$key] : $default;
	}


	/**
	 * Sends command to miner and returns parsed response.
	 *
	 * @param string $command command to send
	 * @param string|array $params if anything else than array is supplied, it is converted to array("parameter"=>$params)
	 * @return array
	 *
	 * @throws RPCMinerException if connection to miner fails or malformed response is received
	 */
	public function api($command, $params = array()) {
		$query = array("command" => $command);

		if (!is_array($params)) {
			$query["parameter"] = (string)$params;
		} else if (count($params) > 0) {
			foreach ($params as &$p)
				$p = $this->escape($p);

			$query["parameter"] = implode(",", $params);
		}

		$this->connect();
		$this->write($query);
		$data = $this->read();
		$this->disconnect();
		return $data;
	}


	public function readStatus($response) {
		if (isset($response['STATUS'][0]))
			$status = $response['STATUS'][0];
		else
			$status = array('STATUS' => 'E', 'Msg' => 'Missing STATUS response', 'Code' => -6);

		if ($this->fetch($status, 'STATUS', 'E') == 'S')
			return $this->fetch($status, 'Msg', "Completed successfully.");


		throw new RPCMinerException("Invalid status: " . $this->fetch($status, 'STATUS', 'E') . " - " . $this->fetch($status, 'Msg', 'Message missing'), $this->fetch($status, 'Code', -10));
	}


	/**
	 * Sends "devs" api command and returns its response (devices list and statistics).
	 *
	 * @return array
	 */
	public function devices() {
		$data = $this->api("devs");
		return isset($data['DEVS']) ? $data['DEVS'] : array();
	}


	/**
	 * Sends "notify" api command and returns its response (device failures and notifications).
	 *
	 * @return array
	 */
	public function notify() {
		$data = $this->api("notify");
		return isset($data['NOTIFY']) ? $data['NOTIFY'] : array();
	}


	/**
	 * Sends "pools" api command and returns its response (mining pools info and stats)
	 *
	 * @return array
	 */
	public function pools() {
		$data = $this->api("pools");
		return isset($data['POOLS']) ? $data['POOLS'] : array();
	}


	public function minerVersion($index = 0) {
		$data = $this->api("version");
		return isset($data['VERSION'][$index]['CGMiner']) ? $data['VERSION'][$index]['CGMiner'] : "unknown";
	}


	public function apiVersion($index = 0) {
		$data = $this->api("version");
		return isset($data['VERSION'][$index]['API']) ? $data['VERSION'][$index]['API'] : "unknown";
	}


	/**
	 * adds new pool with specified credentials
	 *
	 * @param $url
	 * @param $user
	 * @param $pass
	 * @return string success message
	 * @throws RPCMinerException
	 */
	public function addPool($url, $user, $pass) {
		$result = $this->api("addpool", array($url, $user, $pass));
		return $this->readStatus($result);
	}


	public function deletePool($id) {
		$result = $this->api("removepool", $id);
		return $this->readStatus($result);
	}


	public function topPool($id) {
		$result = $this->api("switchpool", $id);
		return $this->readStatus($result);
	}


	public function enablePool($id, $enable) {
		$result = $this->api($enable ? "enablepool" : "disablepool", $id);
		return $this->readStatus($result);
	}


	public function save() {
		return $this->readStatus($this->api('save'));
	}
}

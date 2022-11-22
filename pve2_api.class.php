<?php

/*

Proxmox VE APIv2 (PVE2) Client - PHP Class

Copyright (c) 2012-2014 Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

class PVE2_Exception extends RuntimeException {}

class PVE2_API {
	protected $hostname;
	protected $username;
	protected $realm;
	protected $tokenString;
	protected $port;
	protected $verify_ssl;

	public function __construct ($hostname, $username, $realm, $tokenId, $tokenSecret, $port = 8006, $verify_ssl = false) {
		if (empty($hostname) || empty($username) || empty($realm) || empty($tokenId) || empty($tokenSecret) || empty($port)) {
			throw new PVE2_Exception("Hostname/Username/Realm/API Token ID/API Token Secret required for PVE2_API object constructor.", 1);
		}
		// Check hostname resolves.
		if (gethostbyname($hostname) == $hostname && !filter_var($hostname, FILTER_VALIDATE_IP)) {
			throw new PVE2_Exception("Cannot resolve {$hostname}.", 2);
		}
		// Check port is between 1 and 65535.
		if (!filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
			throw new PVE2_Exception("Port must be an integer between 1 and 65535.", 6);
		}
		// Check that verify_ssl is boolean.
		if (!is_bool($verify_ssl)) {
			throw new PVE2_Exception("verify_ssl must be boolean.", 7);
		}

        // Create API Token String
        $this->tokenString = "PVEAPIToken=" . $username . "@" . $realm . "!" . $tokenId . "=" . $tokenSecret;

		$this->hostname   = $hostname;
		$this->username   = $username;
		$this->realm      = $realm;
		$this->port       = $port;
		$this->verify_ssl = $verify_ssl;
	}


	/*
	 * object action (string action_path, string http_method[, array put_post_parameters])
	 * This method is responsible for the general cURL requests to the JSON API,
	 * and sits behind the abstraction layer methods get/put/post/delete etc.
	 */
	private function action ($action_path, $http_method, $put_post_parameters = null) {
		// Check if we have a prefixed / on the path, if not add one.
		if (substr($action_path, 0, 1) != "/") {
			$action_path = "/".$action_path;
		}

		// Prepare cURL resource.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json{$action_path}");

        $prox_http_headers = array();
        $prox_http_headers[] = "Authorization: " . $this->tokenString;

		// Lets decide what type of action we are taking...
		switch ($http_method) {
			case "GET":
				// Nothing extra to do.
				break;
			case "PUT":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "PUT");

				// Set "POST" data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				break;
			case "POST":
				curl_setopt($prox_ch, CURLOPT_POST, true);

				// Set POST data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				break;
			case "DELETE":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				// No "POST" data required, the delete destination is specified in the URL.

				break;
			default:
				throw new PVE2_Exception("Error - Invalid HTTP Method specified.", 5);
				return false;
		}

		curl_setopt($prox_ch, CURLOPT_HEADER, true);
        curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $prox_http_headers);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, false);

		$action_response = curl_exec($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);

		$split_action_response = explode("\r\n\r\n", $action_response, 2);
		$header_response = $split_action_response[0];
		$body_response = $split_action_response[1];
		$action_response_array = json_decode($body_response, true);

		$action_response_export = var_export($action_response_array, true);
		error_log("----------------------------------------------\n" .
			"FULL RESPONSE:\n\n{$action_response}\n\nEND FULL RESPONSE\n\n" .
			"Headers:\n\n{$header_response}\n\nEnd Headers\n\n" .
			"Data:\n\n{$body_response}\n\nEnd Data\n\n" .
			"RESPONSE ARRAY:\n\n{$action_response_export}\n\nEND RESPONSE ARRAY\n" .
			"----------------------------------------------");

		unset($action_response);
		unset($action_response_export);

		// Parse response, confirm HTTP response code etc.
		$split_headers = explode("\r\n", $header_response);
		if (substr($split_headers[0], 0, 9) == "HTTP/1.1 ") {
			$split_http_response_line = explode(" ", $split_headers[0]);
			if ($split_http_response_line[1] == "200") {
				if ($http_method == "PUT") {
					return true;
				} else {
					return $action_response_array['data'];
				}
			} else {
				error_log("This API Request Failed.\n" .
					"HTTP Response - {$split_http_response_line[1]}\n" .
					"HTTP Error - {$split_headers[0]}");
				return false;
			}
		} else {
			error_log("Error - Invalid HTTP Response.\n" . var_export($split_headers, true));
			return false;
		}

		if (!empty($action_response_array['data'])) {
			return $action_response_array['data'];
		} else {
			error_log("\$action_response_array['data'] is empty. Returning false.\n" .
				var_export($action_response_array['data'], true));
			return false;
		}
	}

	/*
	 * array reload_node_list ()
	 * Returns the list of node names as provided by /api2/json/nodes.
	 * We need this for future get/post/put/delete calls.
	 * ie. $this->get("nodes/XXX/status"); where XXX is one of the values from this return array.
	 */
	public function reload_node_list () {
		$node_list = $this->get("/nodes");
		if (count($node_list) > 0) {
			$nodes_array = array();
			foreach ($node_list as $node) {
				$nodes_array[] = $node['node'];
			}
			$this->cluster_node_list = $nodes_array;
			return true;
		} else {
			error_log(" Empty list of nodes returned in this cluster.");
			return false;
		}
	}

	/*
	 * array get_node_list ()
	 *
	 */
	public function get_node_list () {
		// We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->cluster_node_list;
	}

	/*
	 * bool|int get_next_vmid ()
	 * Get Last VMID from a Cluster or a Node
	 * returns a VMID, or false if not found.
	 */
	public function get_next_vmid () {
		$vmid = $this->get("/cluster/nextid");
		if ($vmid == null) {
			return false;
		} else {
			return $vmid;
		}
	}

	/*
	 * bool|string get_version ()
	 * Return the version and minor revision of Proxmox Server
	 */
	public function get_version () {
		$version = $this->get("/version");
		if ($version == null) {
			return false;
		} else {
			return $version['version'];
		}
	}

	/*
	 * object/array? get (string action_path)
	 */
	public function get ($action_path) {
		return $this->action($action_path, "GET");
	}

	/*
	 * bool put (string action_path, array parameters)
	 */
	public function put ($action_path, $parameters) {
		return $this->action($action_path, "PUT", $parameters);
	}

	/*
	 * bool post (string action_path, array parameters)
	 */
	public function post ($action_path, $parameters) {
		return $this->action($action_path, "POST", $parameters);
	}

	/*
	 * bool delete (string action_path)
	 */
	public function delete ($action_path) {
		return $this->action($action_path, "DELETE");
	}

}

?>

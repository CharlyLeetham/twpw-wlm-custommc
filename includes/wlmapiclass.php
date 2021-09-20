<?php

/**
 * Description of wlmapiclass
 *
 * @author mjglopez
 */
class wlmapiclass {

	var $url;
	var $key;
	var $return_format = 'xml';
	var $authenticated = 0;

	/**
	 * Initailize wlmapi
	 * @param string $url Wordpress URL
	 * @param string $key API Key
	 */
	function __construct($url, $key) {
		$this->url = $url . '?/wlmapi/2.0/';
		$this->key = $key;
	}

	private function _request($method, $resource, $data=NULL) {
		static $cookie_file;

		if (empty($cookie_file)) {
			$cookie_file = tempnam('/tmp', 'wlmapi');
		}

		$data = empty($data) ? '' : http_build_query($data);
		$url = $this->url . $this->return_format . $resource;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
		
		switch ($method) {
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				break;
			case 'PUT':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($data)));
				break;
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($data)));
				break;
			case 'GET':
				if ($data) {
					$url .= '/&' . $data;
				}
				break;
			default:
				die('Invalid Method: ' . $method);
		}
		/* set the curl URL */
		curl_setopt($ch, CURLOPT_URL, $url);

		/* execute and grab the return data */
		return $out = curl_exec($ch);
	}

	/**
	 * Send a POST request to WishList Member API (add new data)
	 * @param string $resource
	 * @param array $data
	 * @return xml|php|json
	 */
	function post($resource, $data) {
		$this->_auth();
		return $this->_request('POST', $this->_resourcefix($resource), $data);
	}

	/**
	 * Send a GET request to WishList Member API (retrieve data)
	 * @param string $resource
	 * @param array (optional) $data
	 * @return xml|php|json
	 */
	function get($resource, $data='') {
		$this->_auth();
		return $this->_request('GET', $this->_resourcefix($resource), $data);
	}

	/**
	 * Send a PUT request to WishList Member API (update existing data)
	 * @param string $resource
	 * @param array $data
	 * @return xml|php|json
	 */
	function put($resource, $data) {
		$this->_auth();
		return $this->_request('PUT', $this->_resourcefix($resource), $data);
	}

	/**
	 * Send a DELETE to WishList Member API (delete the resource)
	 * @param string $resource
	 * @param array (optional) $data
	 * @return xml|php|json
	 */
	function delete($resource, $data='') {
		$this->_auth();
		return $this->_request('DELETE', $this->_resourcefix($resource), $data);
	}

	private function _resourcefix($resource) {
		if (substr($resource, 0, 1) != '/') {
			$resource = '/' . $resource;
		}
		return $resource;
	}

	private function _auth() {
		if (empty($this->authenticated)) {
			$m = $this->return_format;
			$this->return_format = 'php';

			$output = unserialize($this->_request('GET', '/auth'));
			if ($output['success'] != 1 || empty($output['lock'])) {
				die('No auth lock to open');
			}

			$hash = md5($output['lock'] . $this->key);
			$data = array('key' => $hash);
			$output = unserialize($this->_request('POST', '/auth', $data));
			if ($output['success'] == 1) {
				$this->authenticated = 1;
			}

			$this->return_format = $m;
		}
	}

}

?>
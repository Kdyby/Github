<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Github\Api;

use Kdyby\CurlCaBundle\CertificateHelper;
use Kdyby\Github;
use Nette;
use Nette\Diagnostics\Debugger;
use Nette\Http\UrlScript;
use Nette\Utils\Json;
use Nette\Utils\Strings;



if (!defined('CURLE_SSL_CACERT_BADFILE')) {
	define('CURLE_SSL_CACERT_BADFILE', 77);
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onRequest($url, $params)
 * @method onError(Github\Exception $e, array $info)
 * @method onSuccess(array $result, array $info)
 */
class CurlClient extends Nette\Object
{

	/**
	 * Default options for curl.
	 * @var array
	 */
	public static $defaultCurlOptions = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_USERAGENT => 'kdyby-github-php',
		CURLOPT_HTTPHEADER => array(
			'Accept' => 'application/vnd.github.v3+json',
		),
		CURLINFO_HEADER_OUT => TRUE,
		CURLOPT_HEADER => TRUE,
		CURLOPT_AUTOREFERER => TRUE,
	);

	/**
	 * Options for curl.
	 * @var array
	 */
	public $curlOptions = array();

	/**
	 * @var array of function($url, $params)
	 */
	public $onRequest = array();

	/**
	 * @var array of function(Github\Exception $e, array $info)
	 */
	public $onError = array();

	/**
	 * @var array of function(array $result, array $info)
	 */
	public $onSuccess = array();

	/**
	 * @var Github\Client
	 */
	private $github;

	/**
	 * @var Github\Configuration
	 */
	private $config;

	/**
	 * @var array
	 */
	private $memoryCache = array();

	/**
	 * @var array|string
	 */
	private $lastResponse;

	/**
	 * @var array
	 */
	private $lastResponseInfo;



	public function __construct()
	{
		$this->curlOptions = self::$defaultCurlOptions;
	}



	/**
	 * @param Github\Client $github
	 */
	public function setGithub(Github\Client $github)
	{
		$this->github = $github;
		$this->config = $github->getConfig();
	}



	/**
	 * Makes an HTTP request. This method can be overridden by subclasses if
	 * developers want to do fancier things or use something other than curl to
	 * make the request.
	 *
	 * @param Nette\Http\Url $url The URL to make the request to
	 * @param string $method
	 * @param array $post The parameters to use for the POST body
	 * @param array $headers
	 *
	 * @throws Github\ApiException
	 * @return string The response text
	 */
	public function makeRequest(Nette\Http\Url $url, $method = 'GET', array $post = array(), array $headers = array())
	{
		if (isset($this->memoryCache[$cacheKey = md5(serialize(func_get_args()))])) {
			return $this->memoryCache[$cacheKey];
		}

		// json_encode all params values that are not strings
		$post = array_map(function ($value) {
			if ($value instanceof UrlScript) {
				return (string) $value;

			} elseif ($value instanceof \CURLFile) {
				return $value;
			}

			return !is_string($value) ? Json::encode($value) : $value;
		}, $post);

		$ch = $this->buildCurlResource((string) $url, $method, $post, $headers);
		$result = curl_exec($ch);

		// provide certificate if needed
		if (curl_errno($ch) == CURLE_SSL_CACERT || curl_errno($ch) === CURLE_SSL_CACERT_BADFILE) {
			Debugger::log('Invalid or no certificate authority found, using bundled information', 'github');
			$this->curlOptions[CURLOPT_CAINFO] = CertificateHelper::getCaInfoFile();
			curl_setopt($ch, CURLOPT_CAINFO, CertificateHelper::getCaInfoFile());
			$result = curl_exec($ch);
		}

		// With dual stacked DNS responses, it's possible for a server to
		// have IPv6 enabled but not have IPv6 connectivity.  If this is
		// the case, curl will try IPv4 first and if that fails, then it will
		// fall back to IPv6 and the error EHOSTUNREACH is returned by the operating system.
		if ($result === FALSE && empty($opts[CURLOPT_IPRESOLVE])) {
			$matches = array();
			if (preg_match('/Failed to connect to ([^:].*): Network is unreachable/', curl_error($ch), $matches)) {
				if (strlen(@inet_pton($matches[1])) === 16) {
					Debugger::log('Invalid IPv6 configuration on server, Please disable or get native IPv6 on your server.', 'github');
					$this->curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
					curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
					$result = curl_exec($ch);
				}
			}
		}

		$info = curl_getinfo($ch);
		$info['http_code'] = (int) $info['http_code'];
		if (isset($info['request_header'])) {
			list($info['request_header']) = self::parseHeaders($info['request_header']);
		}
		$info['method'] = isset($post['method']) ? $post['method']: 'GET';
		$info['headers'] = self::parseHeaders(substr($result, 0, $info['header_size']));

		$this->lastResponseInfo = $info;
		$this->lastResponse = $result = substr($result, $info['header_size']);

		if (isset($info['headers'][0]['X-RateLimit-Remaining'])) {
			$remaining = $info['headers'][0]['X-RateLimit-Remaining'];
			$limit = isset($info['headers'][0]['X-RateLimit-Limit']) ? $info['headers'][0]['X-RateLimit-Limit'] : 5000;

			if ($remaining <= 0) {
				$e = new Github\ApiLimitExceedException("The api limit of $limit has been exceeded");
				$e->bindRequest($post, $result, $info);
				curl_close($ch);
				$this->onError($e, $info);
				throw $e;
			}
		}

		$response = array();
		try {
			$this->lastResponse = $response = Json::decode($result, Json::FORCE_ARRAY);

		} catch (Nette\Utils\JsonException $jsonException) {
			if (isset($info['headers'][0]['Content-Type']) && preg_match('~^application/json;.*~is', $info['headers'][0]['Content-Type'])) {
				$e = new Github\ApiException($jsonException->getMessage() . (isset($response) ? "\n\n" . $response : ''), $info['http_code']);
				$e->bindRequest($post, $result, $info);
				curl_close($ch);
				$this->onError($e, $info);
				throw $e;
			}
		}

		if ($info['http_code'] >= 300 || $result === FALSE) {
			$e = new Github\RequestFailedException(curl_error($ch), curl_errno($ch));

			if ($result) {
				if ($info['http_code'] === 400) {
					$e = new Github\BadRequestException(isset($response['message']) ? $response['message'] : $result, $info['http_code'], $e);

				} elseif ($info['http_code'] === 422 && isset($response['errors'])) {
					$e = new Github\ValidationFailedException('Validation Failed: ' . self::parseErrors($response), $info['http_code'], $e);

				} elseif (isset($response['message'])) {
					$e = new Github\ApiException($response['message'], $info['http_code'], $e);
				}
			}

			$e->bindRequest($post, $result, $info);
			curl_close($ch);
			$this->onError($e, $info);
			throw $e;
		}

		$this->onSuccess($this->lastResponse, $info);
		curl_close($ch);

		return $this->memoryCache[$cacheKey] = $this->lastResponse;
	}



	/**
	 * @return array|string
	 */
	public function getLastResponse()
	{
		return $this->lastResponse;
	}



	/**
	 * @return int
	 */
	public function getLastResponseHttpCode()
	{
		return $this->lastResponseInfo['http_code'];
	}



	/**
	 * @return array
	 */
	public function getLastResponseHeaders()
	{
		return end($this->lastResponseInfo['headers']);
	}



	/**
	 * @return array
	 */
	public function getLastRequestHeaders()
	{
		return isset($this->lastResponseInfo['request_header']) ? $this->lastResponseInfo['request_header'] : array();
	}



	/**
	 * @param string $url
	 * @param string $method
	 * @param array $post
	 * @param array $headers
	 * @return resource
	 */
	protected function buildCurlResource($url, $method, array $post, array $headers)
	{
		$ch = curl_init($url);
		$options = $this->curlOptions;

		// configuring a POST request
		if ($post || $method === 'POST') {
			$options[CURLOPT_POSTFIELDS] = $post;
			$options[CURLOPT_POST] = TRUE;

		} elseif ($method === 'HEAD') {
			$options[CURLOPT_NOBODY] = TRUE;

		} elseif ($method === 'GET') {
			$options[CURLOPT_HTTPGET] = TRUE;

		} else {
			$options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
		}

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		$options[CURLOPT_HTTPHEADER]['Expect'] = '';
		$tmp = array();
		foreach ($headers + $options[CURLOPT_HTTPHEADER] as $name => $value) {
			$tmp[] = trim("$name: $value");
		}
		$options[CURLOPT_HTTPHEADER] = $tmp;

		// execute request
		curl_setopt_array($ch, $options);

		$this->onRequest($url, $options);

		return $ch;
	}



	private static function parseHeaders($raw)
	{
		$headers = array();

		// Split the string on every "double" new line.
		foreach (explode("\r\n\r\n", $raw) as $index => $block) {

			// Loop of response headers. The "count() -1" is to
			//avoid an empty row for the extra line break before the body of the response.
			foreach (Strings::split(trim($block), '~[\r\n]+~') as $i => $line) {
				if (preg_match('~^([a-z-]+\\:)(.*)$~is', $line)) {
					list($key, $val) = explode(': ', $line, 2);
					$headers[$index][$key] = $val;

				} elseif (!empty($line)) {
					$headers[$index][] = $line;
				}
			}
		}

		return $headers;
	}



	private static function parseErrors(array $response)
	{
		$errors = array();
		foreach ($response['errors'] as $error) {
			switch ($error['code']) {
				case 'missing':
					$errors[] = sprintf('The %s %s does not exist, for resource "%s"', $error['field'], $error['value'], $error['resource']);
					break;

				case 'missing_field':
					$errors[] = sprintf('Field "%s" is missing, for resource "%s"', $error['field'], $error['resource']);
					break;

				case 'invalid':
					$errors[] = sprintf('Field "%s" is invalid, for resource "%s"', $error['field'], $error['resource']);
					break;

				case 'already_exists':
					$errors[] = sprintf('Field "%s" already exists, for resource "%s"', $error['field'], $error['resource']);
					break;

				default:
					$errors[] = $error['message'];
					break;
			}
		}

		return implode(', ', $errors);
	}

}

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
use Tracy\Debugger;
use Nette\Utils\Strings;



if (!defined('CURLE_SSL_CACERT_BADFILE')) {
	define('CURLE_SSL_CACERT_BADFILE', 77);
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onRequest(Request $request, $options)
 * @method onError(Github\Exception $e, Response $response)
 * @method onSuccess(Response $response)
 */
class CurlClient extends Nette\Object implements Github\HttpClient
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
	 * @var array of function(Request $request, $options)
	 */
	public $onRequest = array();

	/**
	 * @var array of function(Github\Exception $e, Response $response)
	 */
	public $onError = array();

	/**
	 * @var array of function(Response $response)
	 */
	public $onSuccess = array();

	/**
	 * @var array
	 */
	private $memoryCache = array();



	public function __construct()
	{
		$this->curlOptions = self::$defaultCurlOptions;
	}



	/**
	 * Makes an HTTP request. This method can be overridden by subclasses if
	 * developers want to do fancier things or use something other than curl to
	 * make the request.
	 *
	 * @param Request $request
	 * @throws Github\ApiException
	 * @return Response
	 */
	public function makeRequest(Request $request)
	{
		if (isset($this->memoryCache[$cacheKey = md5(serialize($request))])) {
			return $this->memoryCache[$cacheKey];
		}

		$ch = $this->buildCurlResource($request);
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
		$info['error'] = $result === FALSE ? array('message' => curl_error($ch), 'code' => curl_errno($ch)) : array();

		$request->setHeaders($info['request_header']);
		$response = new Response($request, substr($result, $info['header_size']), $info['http_code'], end($info['headers']), $info);

		if (!$response->isOk()) {
			$e = $response->toException();
			curl_close($ch);
			$this->onError($e, $response);
			throw $e;
		}

		$this->onSuccess($response);
		curl_close($ch);

		return $this->memoryCache[$cacheKey] = $response;
	}



	/**
	 * @param Request $request
	 * @return resource
	 */
	protected function buildCurlResource(Request $request)
	{
		$ch = curl_init((string) $request->getUrl());
		$options = $this->curlOptions;
		$options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();

		// configuring a POST request
		if ($request->getPost()) {
			$options[CURLOPT_POSTFIELDS] = $request->getPost();
		}

		if ($request->isHead()) {
			$options[CURLOPT_NOBODY] = TRUE;

		} elseif ($request->isGet()) {
			$options[CURLOPT_HTTPGET] = TRUE;
		}

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		$options[CURLOPT_HTTPHEADER]['Expect'] = '';
		$tmp = array();
		foreach ($request->getHeaders() + $options[CURLOPT_HTTPHEADER] as $name => $value) {
			$tmp[] = trim("$name: $value");
		}
		$options[CURLOPT_HTTPHEADER] = $tmp;

		// execute request
		curl_setopt_array($ch, $options);
		$this->onRequest($request, $options);

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

}

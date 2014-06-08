<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\Api;

use Kdyby;
use Kdyby\Github;
use Nette;
use Nette\Utils\Json;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property-read Request $request
 * @property-read string $content
 * @property-read int $httpCode
 * @property-read array $headers
 * @property-read array $debugInfo
 */
class Response extends Nette\Object
{

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var string|array
	 */
	private $content;

	/**
	 * @var string|array
	 */
	private $arrayContent;

	/**
	 * @var int
	 */
	private $httpCode;

	/**
	 * @var array
	 */
	private $headers;

	/**
	 * @var array
	 */
	private $info;



	public function __construct(Request $request, $content, $httpCode, $headers = array(), $info = array())
	{
		$this->request = $request;
		$this->content = $content;
		$this->httpCode = (int) $httpCode;
		$this->headers = $headers;
		$this->info = $info;
	}



	/**
	 * @return Request
	 */
	public function getRequest()
	{
		return $this->request;
	}



	/**
	 * @return array|string
	 */
	public function getContent()
	{
		return $this->content;
	}



	/**
	 * @return bool
	 */
	public function isJson()
	{
		return isset($this->headers['Content-Type'])
			&& preg_match('~^application/json;.*~is', $this->headers['Content-Type']);
	}



	/**
	 * @throws Github\ApiException
	 * @return array
	 */
	public function toArray()
	{
		if ($this->arrayContent !== NULL) {
			return $this->arrayContent;
		}

		if (!$this->isJson()) {
			return NULL;
		}

		try {
			return $this->arrayContent = Json::decode($this->content, Json::FORCE_ARRAY);

		} catch (Nette\Utils\JsonException $jsonException) {
			$e = new Github\ApiException($jsonException->getMessage() . ($this->content ? "\n\n" . $this->content : ''), $this->httpCode, $jsonException);
			$e->bindResponse($this->request, $this);
			throw $e;
		}
	}



	/**
	 * @return bool
	 */
	public function isPaginated()
	{
		return $this->request->isPaginated() || ($this->request->isGet() && isset($this->headers['Link']));
	}



	/**
	 * @return int
	 */
	public function getHttpCode()
	{
		return $this->httpCode;
	}



	/**
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}



	/**
	 * @return bool
	 */
	public function isOk()
	{
		return $this->httpCode >= 200 && $this->httpCode < 300;
	}



	public function toException()
	{
		if ($this->httpCode < 300 && $this->content !== FALSE) {
			return NULL;
		}

		$error = isset($this->info['error']) ? $this->info['error'] : NULL;
		$e = new Github\RequestFailedException(
			$error ? $error['message'] : '',
			$error ? (int) $error['code'] : 0
		);

		if ($this->content && $this->isJson()) {
			$response = $this->toArray();

			if ($this->httpCode === 400) {
				$e = new Github\BadRequestException(isset($response['message']) ? $response['message'] : $this->content, $this->httpCode, $e);

			} elseif ($this->httpCode === 422 && isset($response['errors'])) {
				$e = new Github\ValidationFailedException('Validation Failed: ' . self::parseErrors($response), $this->httpCode, $e);

			} elseif ($this->httpCode === 404 && isset($response['message'])) {
				$e = new Github\UnknownResourceException($response['message'] . ': ' . $this->request->getUrl(), $this->httpCode, $e);

			} elseif (isset($response['message'])) {
				$e = new Github\ApiException($response['message'], $this->httpCode, $e);
			}
		}

		return $e->bindResponse($this->request, $this);
	}



	/**
	 * @return bool
	 */
	public function hasRemainingRateLimit()
	{
		return isset($this->headers['X-RateLimit-Remaining']) ? $this->headers['X-RateLimit-Remaining'] > 0 : TRUE;
	}



	/**
	 * @return int
	 */
	public function getRateLimit()
	{
		return isset($this->headers['X-RateLimit-Limit']) ? $this->headers['X-RateLimit-Limit'] : 5000;
	}



	/**
	 * @see https://developer.github.com/guides/traversing-with-pagination/#navigating-through-the-pages
	 * @param string $rel
	 * @return array
	 */
	public function getPaginationLink($rel = 'next')
	{
		if (!isset($this->headers['Link']) || !preg_match('~<(?P<link>[^>]+)>;\s*rel="' . preg_quote($rel) . '"~i', $this->headers['Link'], $m)) {
			return NULL;
		}

		return new Nette\Http\UrlScript($m['link']);
	}



	/**
	 * @internal
	 * @return array
	 */
	public function getDebugInfo()
	{
		return $this->info;
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

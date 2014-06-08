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
use Nette;
use Nette\Utils\Json;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Request extends Nette\Object
{

	const GET = 'GET';
	const HEAD = 'HEAD';
	const POST = 'POST';
	const PATCH = 'PATCH';
	const PUT = 'PUT';
	const DELETE = 'DELETE';

	/**
	 * @var \Nette\Http\Url
	 */
	private $url;

	/**
	 * @var string
	 */
	private $method;

	/**
	 * @var array|string
	 */
	private $post;

	/**
	 * @var array
	 */
	private $headers;



	public function __construct(Nette\Http\Url $url, $method = self::GET, $post = array(), array $headers = array())
	{
		$this->url = $url;
		$this->method = strtoupper($method);
		$this->headers = $headers;

		if (!is_array($post)) {
			$this->post = $post;

		} elseif ($post) {
			$this->post = array_map(function ($value) {
				if ($value instanceof Nette\Http\UrlScript) {
					return (string) $value;

				} elseif ($value instanceof \CURLFile) {
					return $value;
				}

				return !is_string($value) ? Json::encode($value) : $value;
			}, $post);
		}
	}



	/**
	 * @return Nette\Http\Url
	 */
	public function getUrl()
	{
		return clone $this->url;
	}



	/**
	 * @return array
	 */
	public function getParameters()
	{
		parse_str($this->url->getQuery(), $params);
		return $params;
	}



	/**
	 * @return bool
	 */
	public function isPaginated()
	{
		$params = $this->getParameters();
		return $this->isGet() && (isset($params['per_page']) || isset($params['page']));
	}



	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}



	/**
	 * @return bool
	 */
	public function isGet()
	{
		return $this->method === self::GET;
	}



	/**
	 * @return bool
	 */
	public function isHead()
	{
		return $this->method === self::HEAD;
	}



	/**
	 * @return bool
	 */
	public function isPost()
	{
		return $this->method === self::POST;
	}



	/**
	 * @return bool
	 */
	public function isPut()
	{
		return $this->method === self::PUT;
	}



	/**
	 * @return bool
	 */
	public function isPatch()
	{
		return $this->method === self::PATCH;
	}



	/**
	 * @return bool
	 */
	public function isDelete()
	{
		return $this->method === self::DELETE;
	}



	/**
	 * @return array|string|NULL
	 */
	public function getPost()
	{
		return $this->post;
	}



	/**
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}



	/**
	 * @param array|string $post
	 * @return Request
	 */
	public function setPost($post)
	{
		$this->post = $post;
		return $this;
	}



	/**
	 * @param array $headers
	 * @return Request
	 */
	public function setHeaders(array $headers)
	{
		$this->headers = $headers;
		return $this;
	}



	/**
	 * @param string $header
	 * @param string $value
	 * @return Request
	 */
	public function setHeader($header, $value)
	{
		$this->headers[$header] = $value;
		return $this;
	}



	/**
	 * @param $url
	 * @return Request
	 */
	public function copyWithUrl($url)
	{
		$headers = $this->headers;
		array_shift($headers); // drop info about HTTP version
		return new static(new Nette\Http\Url($url), $this->getMethod(), $this->post, $headers);
	}

}

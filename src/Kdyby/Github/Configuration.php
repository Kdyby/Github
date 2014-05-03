<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github;

use Kdyby;
use Nette;
use Nette\Http\UrlScript;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Configuration extends Nette\Object
{

	/**
	 * @var string
	 */
	public $appId;

	/**
	 * @var string
	 */
	public $appSecret;

	/**
	 * @var array
	 */
	public $permissions = array();

	/**
	 * @var array
	 */
	public $domains = array(
		'oauth' => 'https://github.com/login/oauth/',
		'api' => 'https://api.github.com/',
	);



	public function __construct($appId, $appSecret)
	{
		$this->appId = $appId;
		$this->appSecret = $appSecret;
	}



	/**
	 * Build the URL for given domain alias, path and parameters.
	 *
	 * @param string $name The name of the domain
	 * @param string $path Optional path (without a leading slash)
	 * @param array $params Optional query parameters
	 *
	 * @return UrlScript The URL for the given parameters
	 */
	public function createUrl($name, $path = NULL, $params = array())
	{
		if (preg_match('~^https?://([^.]+\\.)?github\\.com/~', trim($path))) {
			$url = new UrlScript($path);

		} else {
			$url = new UrlScript($this->domains[$name]);
			$url->path .= ltrim($path, '/');
		}

		$url->appendQuery(array_map(function ($param) {
			return $param instanceof UrlScript ? (string) $param : $param;
		}, $params));

		return $url;
	}

}

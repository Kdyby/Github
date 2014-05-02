<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\Api;

use Github;
use Github\HttpClient\Cache\CacheInterface;
use Github\HttpClient\CachedHttpClient;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\ClientInterface;
use Kdyby;
use Nette;
use Symfony\Component\EventDispatcher\EventDispatcher;



/**
 * Modifies the listeners to work with debug panel.
 * Also adds memory cache that remembers request done using single instance of HttpClient.
 * This means that when you request information about authenticated user multiple times,
 * only one request will be called, which means more effective usage of api limit.
 *
 * The memory cache can be disabled by calling
 * <code>
 * $httpClient->useMemoryCache(FALSE);
 * </code>
 *
 * And the Cache from CachedHttpClient can be disabled by passing `NullCache` like this
 * <code>
 * $httpClient->setCache(new \Kdyby\Github\Api\Cache\NullCache());
 * </code>
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class HttpClient extends CachedHttpClient
{

	/**
	 * @var GuzzleClient
	 */
	protected $client;

	/**
	 * @var ErrorListener
	 */
	private $errorListener;

	/**
	 * @var array
	 */
	protected $memoryCache = FALSE;



	public function __construct(Kdyby\Github\SessionStorage $session, array $options = array(), ClientInterface $client = null)
	{
		parent::__construct($options, $client);

		// reset listeners
		$this->client->setEventDispatcher(new EventDispatcher());

		// add modified listeners
		$this->addListener('request.error', array(
			$this->errorListener = new ErrorListener($this->options),
			'onRequestError'
		));
		$this->addListener('request.before_send', array(
			new AccessTokenListener($session),
			'onRequestBeforeSend'
		));
	}



	/**
	 * {@inheritdoc}
	 */
	public function request($path, $body = null, $httpMethod = 'GET', array $headers = array(), array $options = array())
	{
		if ($this->memoryCache !== FALSE && isset($this->memoryCache[$key = md5(serialize(func_get_args()))])) {
			return unserialize($this->memoryCache[$key]);
		}

		$response = parent::request($path, $body, $httpMethod, $headers, $options);

		if (isset($key)) {
			$this->memoryCache[$key] = serialize($response);
		}

		return $response;
	}



	/**
	 * Changing the cache also resets the memory cache
	 *
	 * @param $cache CacheInterface
	 */
	public function setCache(CacheInterface $cache)
	{
		parent::setCache($cache);
		$this->memoryCache = is_array($this->memoryCache) ? array() : FALSE;
	}



	/**
	 * Enables or disabled the memory cache
	 *
	 * @param bool $use
	 */
	public function useMemoryCache($use = TRUE)
	{
		$this->memoryCache = $use ? array() : FALSE;
	}



	/**
	 * @internal
	 * @param Kdyby\Github\Diagnostics\Panel $panel
	 */
	public function setPanel(Kdyby\Github\Diagnostics\Panel $panel)
	{
		$this->addListener('request.before_send', array($panel, 'begin'));
		$this->addListener('request.success', array($panel, 'success'));
		$this->errorListener->setPanel($panel);
	}

}

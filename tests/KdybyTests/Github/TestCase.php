<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace KdybyTests\Github;

use Kdyby;
use Kdyby\Github\Api;
use Kdyby\Github\ApiException;
use Nette;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class TestCase extends \Tester\TestCase
{

	/**
	 * @var ApiClientMock
	 */
	protected $httpClient;

	/**
	 * @var Kdyby\Github\SessionStorage
	 */
	protected $session;

	/**
	 * @var Kdyby\Github\Configuration
	 */
	protected $config;



	protected function buildClient($query = array())
	{
		// please do not abuse this
		$this->config = new Kdyby\Github\Configuration('123', 'abc');

		$httpRequest = new Nette\Http\Request(new Nette\Http\UrlScript('http://www.kdyby.org' . ($query ? '?' . http_build_query($query) : '')), $query, array());

		$session = new Nette\Http\Session($httpRequest, new Nette\Http\Response());
		$session->setStorage(new ArraySessionStorage($session));
		$this->session = new Kdyby\Github\SessionStorage($session, $this->config);

		$this->httpClient = new ApiClientMock();

		return new Kdyby\Github\Client($this->config, $httpRequest, $this->session, $this->httpClient);
	}
}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ApiClientMock extends Nette\Object implements Kdyby\Github\HttpClient
{

	/** @var Api\Request[] */
	public $requests = array();

	/** @var array */
	public $responses = array();



	/**
	 * @param Api\Request $request
	 * @throws ApiException
	 * @return Api\Response
	 */
	public function makeRequest(Api\Request $request)
	{
		$this->requests[] = $request;
		$request->setHeaders($request->getHeaders() + array('Accept' => 'application/vnd.github.v3+json')); // the CurlClient is setting this as a default

		list($content, $httpCode, $headers, $info) = array_shift($this->responses);
		return new Api\Response($request, $content, $httpCode, $headers, $info);
	}



	public function fakeResponse($content, $httpCode, $headers = array(), $info = array())
	{
		$this->responses[] = array($content, $httpCode, $headers, $info);
	}

}



class ArraySessionStorage extends Nette\Object implements Nette\Http\ISessionStorage
{

	/**
	 * @var array
	 */
	private $session;



	public function __construct(Nette\Http\Session $session = NULL)
	{
		if ($session->isStarted()) {
			$session->destroy();
		}

		$session->setOptions(array('cookie_disabled' => TRUE));
	}



	public function open($savePath, $sessionName)
	{
		$this->session = array();
	}



	public function close()
	{
		$this->session = array();
	}



	public function read($id)
	{
		return isset($this->session[$id]) ? $this->session[$id] : NULL;
	}



	public function write($id, $data)
	{
		$this->session[$id] = $data;
	}



	public function remove($id)
	{
		unset($this->session[$id]);
	}



	public function clean($maxlifetime)
	{

	}

}

<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\Api;

use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\ClientInterface;
use Kdyby;
use Nette;
use Symfony\Component\EventDispatcher\EventDispatcher;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class HttpClient extends \Github\HttpClient\HttpClient
{

	/**
	 * @var GuzzleClient
	 */
	protected $client;

	/**
	 * @var ErrorListener
	 */
	private $errorListener;



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



	public function setPanel(Kdyby\Github\Diagnostics\Panel $panel)
	{
		$this->addListener('request.before_send', array($panel, 'begin'));
		$this->addListener('request.success', array($panel, 'success'));
		$this->errorListener->setPanel($panel);
	}

}

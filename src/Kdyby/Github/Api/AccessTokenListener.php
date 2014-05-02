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
use Guzzle\Common\Event;
use Guzzle\Http\Message\Request;
use Kdyby;
use Kdyby\Github\SessionStorage;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class AccessTokenListener extends Nette\Object
{

	/**
	 * @var SessionStorage
	 */
	private $session;



	public function __construct(SessionStorage $session)
	{
		$this->session = $session;
	}



	/**
	 * @param Event|Request[] $event
	 */
	public function onRequestBeforeSend(Event $event)
	{
		// Skip by default
		if ($this->session->access_token === NULL) {
			return;
		}

		$event['request']->setHeader('Authorization', 'token ' . utf8_encode($this->session->access_token));
	}

}

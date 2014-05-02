<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\Api;

use Guzzle\Common\Event;
use Guzzle\Http\Message\Request;
use Kdyby;
use Kdyby\Github\Diagnostics\Panel;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ErrorListener extends \Github\HttpClient\Listener\ErrorListener
{

	/**
	 * @var Panel
	 */
	private $panel;



	/**
	 * @param Event|Request[] $event
	 * @throws \Exception
	 */
	public function onRequestError(Event $event)
	{
		try {
			parent::onRequestError($event);

		} catch (\Exception $e) {
			if ($this->panel) {
				$class = get_class($e);
				$exception = new $class(trim($e->getMessage()) ?: $event['request']->getResponse()->getReasonPhrase(), $e->getCode(), $e);
				$this->panel->failure(new Event(array('exception' => $exception) + $event->toArray()));
			}

			throw $e;
		}
	}



	public function setPanel(Panel $panel)
	{
		$this->panel = $panel;
	}

}

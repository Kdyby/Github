<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\UI;

use Kdyby;
use Kdyby\Github;
use Nette;
use Nette\Http\UrlScript;



/**
 * Component that you can connect to presenter
 * and use as public mediator for Github OAuth redirects communication.
 *
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onResponse(LoginDialog $dialog)
 */
class LoginDialog extends Nette\Application\UI\Control
{

	/**
	 * @var array of function(LoginDialog $dialog)
	 */
	public $onResponse = array();

	/**
	 * @var Github\Client
	 */
	protected $client;

	/**
	 * @var Github\Configuration
	 */
	protected $config;

	/**
	 * @var Github\SessionStorage
	 */
	protected $session;

	/**
	 * @var UrlScript
	 */
	protected $currentUrl;

	/**
	 * @var string
	 */
	protected $scope;



	/**
	 * @param Github\Client $github
	 */
	public function __construct(Github\Client $github)
	{
		$this->client = $github;
		$this->config = $github->getConfig();
		$this->session = $github->getSession();
		$this->currentUrl = $github->getCurrentUrl();

		parent::__construct();
		$this->monitor('Nette\Application\IPresenter');
	}



	/**
	 * @return Github\Client
	 */
	public function getClient()
	{
		return $this->client;
	}



	/**
	 * @param \Nette\ComponentModel\Container $obj
	 */
	protected function attached($obj)
	{
		parent::attached($obj);

		if ($obj instanceof Nette\Application\IPresenter) {
			$this->currentUrl = new UrlScript($this->link('//response!'));
		}
	}



	/**
	 * @param string|array $scope
	 */
	public function setScope($scope)
	{
		$this->scope = implode(',', (array) $scope);
	}



	public function handleCallback()
	{

	}



	/**
	 * Checks, if there is a user in storage and if not, it redirects to login dialog.
	 * If the user is already in session storage, it will behave, as if were redirected from github right now,
	 * this means, it will directly call onResponse event.
	 *
	 * @throws \Nette\Application\AbortException
	 */
	public function handleOpen()
	{
		if (!$this->client->getUser()) { // no user
			$this->open();
		}

		$this->onResponse($this);
		$this->presenter->redirect('this');
	}



	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function open()
	{
		$this->presenter->redirectUrl($this->getUrl());
	}



	/**
	 * @return array
	 */
	public function getQueryParams()
	{
		// CSRF
		$this->client->getSession()->establishCSRFTokenState();

		$params = array(
			'client_id' => $this->config->appId,
			'redirect_uri' => (string) $this->currentUrl,
			'state' => $this->session->state,
			'scope' => $this->scope ?: implode(',', (array) $this->config->permissions),
		);

		return $params;
	}



	/**
	 * @return string
	 */
	public function getUrl()
	{
		return (string) $this->config->createUrl('oauth', 'authorize', $this->getQueryParams());
	}



	public function handleResponse()
	{
		$this->onResponse($this);
		$this->presenter->redirect('this');
	}

}

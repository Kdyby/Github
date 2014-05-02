<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github;

use Github\Api\User;
use Guzzle\Http\Message\Response;
use Kdyby;
use Nette;
use Nette\Utils\Json;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Profile extends Nette\Object
{


	/**
	 * @var Client
	 */
	private $github;

	/**
	 * @var string
	 */
	private $profileId;

	/**
	 * @var \Nette\ArrayHash
	 */
	private $details;



	/**
	 * @param Client $github
	 * @param string $profileId
	 */
	public function __construct(Client $github, $profileId = NULL)
	{
		$this->github = $github;

		if (is_numeric($profileId)) {
			throw new InvalidArgumentException("ProfileId must be a username of the account you're trying to read or NULL, which means actually logged in user.");
		}

		$this->profileId = $profileId;
	}



	/**
	 * @return string
	 */
	public function getId()
	{
		if ($this->profileId === NULL) {
			return $this->github->getUser();
		}

		return $this->profileId;
	}



	/**
	 * @param string $key
	 * @return \Nette\ArrayHash|NULL
	 */
	public function getDetails($key = NULL)
	{
		if ($this->details === NULL) {
			try {

				if ($this->profileId !== NULL) {
					/** @var User $users */
					$users = $this->github->api('users');
					$this->details = $users->show($this->getId());

				} elseif ($this->github->getUser()) {
					/** @var Response $response */
					$response = $this->github->getHttpClient()->get('/user');
					$this->details = Json::decode($response->getBody(TRUE), Json::FORCE_ARRAY);

				} else {
					$this->details = array();
				}

			} catch (\Exception $e) {
			}
		}

		if ($key !== NULL) {
			return isset($this->details[$key]) ? $this->details[$key] : NULL;
		}

		return $this->details;
	}

}

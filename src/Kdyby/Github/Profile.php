<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github;

use Github\Api\CurrentUser;
use Kdyby;
use Nette;
use Nette\Utils\ArrayHash;



if (!class_exists('Nette\Utils\ArrayHash')) {
	class_alias('Nette\ArrayHash', 'Nette\Utils\ArrayHash');
}

/**
 * Simplifies accessing the user profile and it's information.
 *
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
	 * @var ArrayHash
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
	 * @return ArrayHash|NULL
	 */
	public function getDetails($key = NULL)
	{
		if ($this->details === NULL) {
			try {

				if ($this->profileId !== NULL) {
					$this->details = $this->github->api('/users/' . rawurlencode($this->profileId));

				} elseif ($this->github->getUser()) {
					$this->details = $this->github->api('/user');

				} else {
					$this->details = array();
				}

			} catch (\Exception $e) {
				// todo: log?
			}
		}

		if ($key !== NULL) {
			if (empty($this->details[$key])) {
				if ($key === 'email') {
					$this->details['email'] = $this->getPrimaryEmail();
				}
			}

			return isset($this->details[$key]) ? $this->details[$key] : NULL;
		}

		return $this->details;
	}



	/**
	 * @return string|NULL
	 */
	public function getPrimaryEmail()
	{
		if ($this->profileId !== NULL) {
			$this->getDetails();
			return $this->details['email'];
		}

		try {
			$emails = (array) $this->github->api('/user/emails');

		} catch (\Exception $e) {
			return NULL; // todo: log?
		}


		if (!count($emails)) {
			return NULL;
		}

		usort($emails, function ($a, $b) {
			$primary = $b->primary - $a->primary;
			return $primary !== 0 ? $primary : ($b->verified - $a->verified);
		});

		return reset($emails)->email;
	}

}

<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github;

use Github;
use Kdyby;
use Nette;



/**
 * Github api client that serves for communication
 * and handles authorization on the api level.
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class Client extends Nette\Object
{

	/**
	 * @var Api\CurlClient
	 */
	private $httpClient;

	/**
	 * @var Configuration
	 */
	private $config;

	/**
	 * @var \Nette\Http\IRequest
	 */
	private $httpRequest;

	/**
	 * @var SessionStorage
	 */
	private $session;

	/**
	 * The ID of the Github user, or 0 if the user is logged out.
	 * @var integer
	 */
	protected $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization code.
	 * null means the access token has yet to be determined.
	 * @var string
	 */
	protected $accessToken;



	/**
	 * @param Configuration $config
	 * @param Nette\Http\IRequest $httpRequest
	 * @param SessionStorage $session
	 * @param Api\CurlClient $httpClient
	 */
	public function __construct(Configuration $config, Nette\Http\IRequest $httpRequest, SessionStorage $session, Api\CurlClient $httpClient)
	{
		$this->config = $config;
		$this->httpRequest = $httpRequest;
		$this->session = $session;
		$this->httpClient = $httpClient;
	}



	/**
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}



	/**
	 * @return Nette\Http\UrlScript
	 */
	public function getCurrentUrl()
	{
		return clone $this->httpRequest->getUrl();
	}



	/**
	 * @return SessionStorage
	 */
	public function getSession()
	{
		return $this->session;
	}



	/**
	 * Get the UID of the connected user, or 0 if the Github user is not connected.
	 *
	 * @return string the UID if available.
	 */
	public function getUser()
	{
		if ($this->user === NULL) {
			$this->user = $this->getUserFromAvailableData();
		}

		return $this->user;
	}



	/**
	 * @param int|string $profileId
	 * @return Profile
	 */
	public function getProfile($profileId = NULL)
	{
		return new Profile($this, $profileId);
	}



	/**
	 * Simply pass anything starting with a slash and it will call the Api, for example
	 * <code>
	 * $details = $github->api('/user');
	 * </code>
	 *
	 * @param string $path
	 * @param string $method The argument is optional
	 * @param array $params Query parameters
	 * @param array $post Post request parameters or body to send
	 * @param array $headers Http request headers
	 * @throws ApiException
	 * @return Nette\ArrayHash|string
	 */
	public function api($path, $method = 'GET', array $params = array(), array $post = array(), array $headers = array())
	{
		if (is_array($method)) {
			$headers = $post;
			$post = $params;
			$params = $method;
			$method = 'GET';
		}

		if ($token = $this->getAccessToken()) {
			$headers['Authorization'] = 'token ' . $token;
		}

		$result = $this->httpClient->makeRequest(
			$this->config->createUrl('api', $path, $params),
			$method,
			$post,
			$headers
		);

		return is_array($result) ? Nette\ArrayHash::from($result) : $result;
	}



	/**
	 * Sets the access token for api calls.  Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param string $accessToken an access token.
	 * @return Client
	 */
	public function setAccessToken($accessToken)
	{
		$this->accessToken = $accessToken;
		return $this;
	}



	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return string The access token
	 */
	public function getAccessToken()
	{
		if ($this->accessToken !== NULL) {
			return $this->accessToken; // we've done this already and cached it.  Just return.
		}

		if ($accessToken = $this->getUserAccessToken()) {
			$this->setAccessToken($accessToken);
		}

		return $this->accessToken;
	}



	/**
	 * Determines and returns the user access token, first using
	 * the signed request if present, and then falling back on
	 * the authorization code if present.  The intent is to
	 * return a valid user access token, or false if one is determined
	 * to not be available.
	 *
	 * @return string A valid user access token, or false if one could not be determined.
	 */
	protected function getUserAccessToken()
	{
		if (($code = $this->getCode()) && $code != $this->session->code) {
			if ($accessToken = $this->getAccessTokenFromCode($code)) {
				$this->session->code = $code;
				return $this->session->access_token = $accessToken;
			}

			// code was bogus, so everything based on it should be invalidated.
			$this->session->clearAll();
			return FALSE;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return $this->session->access_token;
	}



	/**
	 * Determines the connected user by first examining any signed
	 * requests, then considering an authorization code, and then
	 * falling back to any persistent store storing the user.
	 *
	 * @return integer The id of the connected Github user, or 0 if no such user exists.
	 */
	protected function getUserFromAvailableData()
	{
		$user = $this->session->get('user_id', 0);

		// use access_token to fetch user id if we have a user access_token, or if
		// the cached access token has changed.
		if (($accessToken = $this->getAccessToken()) && !($user && $this->session->access_token === $accessToken)) {
			if (!$user = $this->getUserFromAccessToken()) {
				$this->session->clearAll();

			} else {
				$this->session->user_id = $user;
			}
		}

		return $user;
	}



	/**
	 * Get the authorization code from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization code was
	 * discoverable.
	 *
	 * @return mixed The authorization code, or false if the authorization code could not be determined.
	 */
	protected function getCode()
	{
		$state = $this->getRequest('state');
		if (($code = $this->getRequest('code')) && $state && $this->session->state === $state) {
			$this->session->state = NULL; // CSRF state has done its job, so clear it
			return $code;
		}

		return FALSE;
	}



	/**
	 * Retrieves the UID with the understanding that $this->accessToken has already been set and is seemingly legitimate.
	 * It relies on Github's API to retrieve user information and then extract the user ID.
	 *
	 * @return integer Returns the UID of the Github user, or 0 if the Github user could not be determined.
	 */
	protected function getUserFromAccessToken()
	{
		try {
			$user = $this->api('/user');

			return isset($user['id']) ? $user['id'] : 0;
		} catch (\Exception $e) { }

		return 0;
	}



	/**
	 * Retrieves an access token for the given authorization code
	 * (previously generated from www.github.com on behalf of a specific user).
	 * The authorization code is sent to api.github.com/oauth
	 * and a legitimate access token is generated provided the access token
	 * and the user for which it was generated all match, and the user is
	 * either logged in to Github or has granted an offline access permission.
	 *
	 * @param string $code An authorization code.
	 * @param null $redirectUri
	 * @return mixed An access token exchanged for the authorization code, or false if an access token could not be generated.
	 */
	protected function getAccessTokenFromCode($code, $redirectUri = NULL)
	{
		if (empty($code)) {
			return FALSE;
		}

		if ($redirectUri === NULL) {
			$redirectUri = $this->getCurrentUrl();
		}

		try {
			$response = $this->httpClient->makeRequest(
				$this->config->createUrl('oauth', 'access_token', array(
					'client_id' => $this->config->appId,
					'client_secret' => $this->config->appSecret,
					'code' => $code,
					'redirect_uri' => $redirectUri,
				)),
				'GET',
				array(),
				array('Accept' => 'application/json')
			);

			if (empty($response) || !is_array($response)) {
				return FALSE;
			}

		} catch (\Exception $e) {
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return FALSE;
		}

		return isset($response['access_token']) ? $response['access_token'] : FALSE;
	}



	/**
	 * Destroy the current session
	 */
	public function destroySession()
	{
		$this->accessToken = NULL;
		$this->user = NULL;
		$this->session->clearAll();
	}



	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed|null
	 */
	protected function getRequest($key, $default = NULL)
	{
		if ($value = $this->httpRequest->getPost($key)) {
			return $value;
		}

		if ($value = $this->httpRequest->getQuery($key)) {
			return $value;
		}

		return $default;
	}

}

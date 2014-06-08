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



/**
 * @see https://developer.github.com/v3/#pagination
 * @author Filip Procházka <filip@prochazka.su>
 */
class Paginator extends Nette\Object implements \Iterator
{

	const PER_PAGE_MAX = 100;

	/**
	 * @var Api\CurlClient
	 */
	private $httpClient;

	/**
	 * @var int
	 */
	private $firstPage;

	/**
	 * @var int
	 */
	private $perPage;

	/**
	 * @var int|NULL
	 */
	private $maxResults;

	/**
	 * @var array
	 */
	private $requestHeaders;

	/**
	 * @var array
	 */
	private $responseBody = array();

	/**
	 * @var array
	 */
	private $responseLinkHeader = array();

	/**
	 * @var int
	 */
	private $itemCursor;

	/**
	 * @var int
	 */
	private $pageCursor;



	public function __construct(Client $client, $resource)
	{
		$this->httpClient = $client->getHttpClient();

		$params = $this->httpClient->getLastRequestParameters();
		$this->firstPage = isset($params['page']) ? (int) max($params['page'], 1) : 1;
		$this->perPage = isset($params['per_page']) ? (int) $params['per_page'] : count($resource);

		$responseHeaders = $this->httpClient->getLastResponseHeaders();
		$this->responseLinkHeader[$this->firstPage] = isset($responseHeaders['Link']) ? self::parseLinkHeader($responseHeaders['Link']) : NULL;
		$this->responseBody[$this->firstPage] = $resource;

		$requestHeaders = $this->httpClient->getLastRequestHeaders();
		array_shift($requestHeaders); // drop info about HTTP version
		$this->requestHeaders = $requestHeaders;
	}



	/**
	 * If you setup maximum number of results, the pagination will stop after fetching the desired number.
	 * If you have per_page=50 and wan't to fetch 200 results, it will make 4 requests in total.
	 *
	 * @param int $maxResults
	 * @return Paginator
	 */
	public function limitResults($maxResults)
	{
		$this->maxResults = (int)$maxResults;
		return $this;
	}



	public function rewind()
	{
		$this->itemCursor = 0;
		$this->pageCursor = $this->firstPage;
	}



	public function valid()
	{
		return isset($this->responseBody[$this->pageCursor][$this->itemCursor])
			&& $this->maxResults > ($this->itemCursor + ($this->pageCursor - $this->firstPage) * $this->perPage);
	}



	public function current()
	{
		if (!$this->valid()) {
			return NULL;
		}

		return Nette\Utils\ArrayHash::from($this->responseBody[$this->pageCursor][$this->itemCursor]);
	}



	public function next()
	{
		$this->itemCursor++;

		// if cursor points at result of next page, try to load it
		if ($this->itemCursor < $this->perPage || $this->itemCursor % $this->perPage !== 0) {
			return;
		}

		if (isset($this->responseBody[$this->pageCursor + 1])) { // already loaded
			$this->itemCursor = 0;
			$this->pageCursor++;
			return;
		}

		if (!isset($this->responseLinkHeader[$this->pageCursor]['next'])) {
			return; // end
		}

		$nextPage = new Nette\Http\UrlScript($this->responseLinkHeader[$this->pageCursor]['next']);
		try {
			$response = $this->httpClient->makeRequest($nextPage, 'GET', array(), $this->requestHeaders);
			$responseHeaders = $this->httpClient->getLastResponseHeaders();

			$this->itemCursor = 0;
			$this->pageCursor++;
			$this->responseLinkHeader[$this->pageCursor] = isset($responseHeaders['Link']) ? self::parseLinkHeader($responseHeaders['Link']) : NULL;
			$this->responseBody[$this->pageCursor] = $response;

		} catch (\Exception $e) {
			$this->itemCursor--; // revert back so the user can continue if needed
		}
	}



	public function key()
	{
		return $this->itemCursor + ($this->pageCursor - 1) * $this->perPage;
	}



	/**
	 * @see https://developer.github.com/guides/traversing-with-pagination/#navigating-through-the-pages
	 * @param string $header
	 * @return array
	 */
	protected static function parseLinkHeader($header)
	{
		// <https://api.github.com/user/repos?page=2&per_page=10>; rel="next", <https://api.github.com/user/repos?page=8&per_page=10>; rel="last"
		$links = array();

		foreach (Nette\Utils\Strings::matchAll($header, '~<(?P<link>[^>]+)>;\s*rel="(?P<rel>[\w]+)"~i') as $match) {
			$links[$match['rel']] = $match['link'];
		}


		return $links + array('next' => NULL, 'prev' => NULL);
	}

}

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
	private $resources = array();

	/**
	 * @var Api\Response[]
	 */
	private $responses = array();

	/**
	 * @var int
	 */
	private $itemCursor;

	/**
	 * @var int
	 */
	private $pageCursor;



	public function __construct(Client $client, Api\Response $response)
	{
		$this->httpClient = $client->getHttpClient();
		$resource = $response->toArray();

		$params = $response->request->getParameters();
		$this->firstPage = isset($params['page']) ? (int) max($params['page'], 1) : 1;
		$this->perPage = isset($params['per_page']) ? (int) $params['per_page'] : count($resource);

		$this->responses[$this->firstPage] = $response;
		$this->resources[$this->firstPage] = $resource;
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
		return isset($this->resources[$this->pageCursor][$this->itemCursor])
			&& (
				$this->maxResults === NULL
				|| $this->maxResults > ($this->itemCursor + ($this->pageCursor - $this->firstPage) * $this->perPage)
			);
	}



	public function current()
	{
		if (!$this->valid()) {
			return NULL;
		}

		return Nette\Utils\ArrayHash::from($this->resources[$this->pageCursor][$this->itemCursor]);
	}



	public function next()
	{
		$this->itemCursor++;

		// if cursor points at result of next page, try to load it
		if ($this->itemCursor < $this->perPage || $this->itemCursor % $this->perPage !== 0) {
			return;
		}

		if (isset($this->resources[$this->pageCursor + 1])) { // already loaded
			$this->itemCursor = 0;
			$this->pageCursor++;
			return;
		}

		if (!$nextPage = $this->responses[$this->pageCursor]->getPaginationLink('next')) {
			return; // end
		}

		try {
			$prevRequest = $this->responses[$this->pageCursor]->getRequest();
			$response = $this->httpClient->makeRequest($prevRequest->copyWithUrl($nextPage));

			$this->itemCursor = 0;
			$this->pageCursor++;
			$this->responses[$this->pageCursor] = $response;
			$this->resources[$this->pageCursor] = $response->toArray();

		} catch (\Exception $e) {
			$this->itemCursor--; // revert back so the user can continue if needed
		}
	}



	public function key()
	{
		return $this->itemCursor + ($this->pageCursor - 1) * $this->perPage;
	}

}

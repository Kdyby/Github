<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\Api\Cache;

use Github\HttpClient\Cache\CacheInterface;
use Guzzle\Http\Message\Response;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NullCache extends Nette\Object implements CacheInterface
{

	/**
	 * @param string $id The id of the cached resource
	 *
	 * @return bool if present
	 */
	public function has($id)
	{
		return FALSE;
	}



	/**
	 * @param string $id The id of the cached resource
	 *
	 * @return null|integer The modified since timestamp
	 */
	public function getModifiedSince($id)
	{
		return NULL;
	}



	/**
	 * @param string $id The id of the cached resource
	 *
	 * @return null|string The ETag value
	 */
	public function getETag($id)
	{
		return NULL;
	}



	/**
	 * @param string $id The id of the cached resource
	 *
	 * @return Response The cached response object
	 *
	 * @throws \InvalidArgumentException If cache data don't exists
	 */
	public function get($id)
	{
		throw new \InvalidArgumentException();
	}



	public function set($id, Response $response)
	{

	}
}

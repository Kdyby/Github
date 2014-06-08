<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Github;


/**
 * Common interface for caching github exceptions
 *
 * @author Filip Procházka <email@filip-prochazka.com>
 */
interface Exception
{

}



/**
 * @author Filip Procházka <email@filip-prochazka.com>
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NotSupportedException extends \LogicException implements Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ApiException extends \RuntimeException implements Exception
{

	public $curlInfo = array();
	public $requestBody;
	public $responseBody;



	/**
	 * @param string $request
	 * @param string $response
	 * @param array $info
	 * @return static
	 */
	public function bindRequest($request, $response, $info)
	{
		$this->curlInfo = $info;
		$this->requestBody = $request;
		$this->responseBody = $response;
		return $this;
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ApiLimitExceedException extends ApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RequestFailedException extends ApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ValidationFailedException extends ApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class BadRequestException extends ApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class UnknownResourceException extends ApiException
{

}

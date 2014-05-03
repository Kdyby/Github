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
class GithubApiException extends \RuntimeException implements Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ApiLimitExceedException extends GithubApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RequestFailedException extends GithubApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ValidationFailedException extends GithubApiException
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class BadRequestException extends GithubApiException
{

}

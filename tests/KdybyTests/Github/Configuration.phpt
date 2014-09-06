<?php

/**
 * Test: Kdyby\Github\Configuration.
 *
 * @testCase KdybyTests\Github\ConfigurationTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Github
 */

namespace KdybyTests\Github;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ConfigurationTest extends Tester\TestCase
{

	/**
	 * @var Kdyby\Github\Configuration
	 */
	private $config;



	protected function setUp()
	{
		$this->config = new Kdyby\Github\Configuration('123', 'abc');
	}



	public function testCreateUrl()
	{
		Assert::match('https://api.github.com/users/fprochazka', (string) $this->config->createUrl('api', '/users/fprochazka'));

		Assert::match('https://github.com/login/oauth/access_token?client_id=123&client_secret=abc', (string) $this->config->createUrl('oauth', 'access_token', array(
			'client_id' => $this->config->appId,
			'client_secret' => $this->config->appSecret
		)));

		Assert::match('https://github.com/login/oauth/authorize?client_id=123&client_secret=abc', (string) $this->config->createUrl('oauth', 'authorize', array(
			'client_id' => $this->config->appId,
			'client_secret' => $this->config->appSecret
		)));
	}

}

\run(new ConfigurationTest());

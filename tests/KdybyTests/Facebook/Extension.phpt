<?php

/**
 * Test: Kdyby\Github\Extension.
 *
 * @testCase KdybyTests\Github\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Github
 */

namespace KdybyTests\Github;

use Kdyby;
use KdybyTests;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Github\DI\GithubExtension::register($config);
		$config->addConfig(__DIR__ . '/files/config.neon', $config::NONE);

		return $config->createContainer();
	}



	public function testFunctional()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('github.client') instanceof Kdyby\Github\Client);
		Assert::true($dic->getService('github.config') instanceof Kdyby\Github\Configuration);
	}

}

KdybyTests\run(new ExtensionTest());

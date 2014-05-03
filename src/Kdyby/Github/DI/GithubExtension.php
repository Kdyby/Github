<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Github\DI;

use Kdyby;
use Nette;
use Nette\PhpGenerator as Code;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class GithubExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'appId' => NULL,
		'appSecret' => NULL,
		'permissions' => array(),
		'clearAllWithLogout' => TRUE,
		'curlOptions' => array(),
		'debugger' => '%debugMode%',
	);



	public function __construct()
	{
		$this->defaults['curlOptions'] = Kdyby\Github\Api\CurlClient::$defaultCurlOptions;
	}



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['appId'], 'string', 'Application ID');
		Validators::assert($config['appSecret'], 'string:40', 'Application secret');

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Github\Client');

		$builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Github\Configuration', array(
				$config['appId'],
				$config['appSecret'],
			))
			->addSetup('$permissions', array($config['permissions']));

		foreach ($config['curlOptions'] as $option => $value) {
			if (defined($option)) {
				unset($config['curlOptions'][$option]);
				$config['curlOptions'][constant($option)] = $value;
			}
		}

		$httpClient = $builder->addDefinition($this->prefix('httpClient'))
			->setClass('Kdyby\Github\Api\CurlClient')
			->addSetup('$service->curlOptions = ?;', array($config['curlOptions']));

		$builder->addDefinition($this->prefix('session'))
			->setClass('Kdyby\Github\SessionStorage');

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Github\Diagnostics\Panel');

			$httpClient->addSetup($this->prefix('@panel') . '::register', array('@self'));
		}

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->clearAll(); }', array(
					'@container', '@self', $this->prefix('session')
				));
		}
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('github', new GithubExtension());
		};
	}

}


<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Github\Diagnostics;

use Guzzle\Common\Event;
use Kdyby\Github\Api\CurlClient;
use Kdyby\Github\Api\HttpClient;
use Nette;
use Nette\Utils\Html;
use Tracy\Debugger;
use Tracy\IBarPanel;



if (!class_exists('Tracy\Debugger')) {
	class_alias('Nette\Diagnostics\Debugger', 'Tracy\Debugger');
}

if (!class_exists('Tracy\Bar')) {
	class_alias('Nette\Diagnostics\Bar', 'Tracy\Bar');
	class_alias('Nette\Diagnostics\BlueScreen', 'Tracy\BlueScreen');
	class_alias('Nette\Diagnostics\Helpers', 'Tracy\Helpers');
	class_alias('Nette\Diagnostics\IBarPanel', 'Tracy\IBarPanel');
}

if (!class_exists('Tracy\Dumper')) {
	class_alias('Nette\Diagnostics\Dumper', 'Tracy\Dumper');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property callable $begin
 * @property callable $failure
 * @property callable $success
 */
class Panel extends Nette\Object implements IBarPanel
{

	/**
	 * @var int logged time
	 */
	private $totalTime = 0;

	/**
	 * @var array
	 */
	private $calls = array();

	/**
	 * @var \stdClass
	 */
	private $current;



	/**
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('img', array('height' => '16px'))
			->src('data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/GitHub-Mark-32px.png')));
		$tab = Html::el('span')->title('Github')->add($img);
		$title = Html::el()->setText('Github');
		if ($this->calls) {
			$title->setText(
				count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
			);
		}
		return (string)$tab->add($title);
	}



	/**
	 * @return string
	 */
	public function getPanel()
	{
		if (!$this->calls) {
			return NULL;
		}

		ob_start();
		$esc = callback('Nette\Templating\Helpers::escapeHtml');
		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) { return \Tracy\Dumper::toHtml($o, array('collapse' => $c)); }
			: callback('\Tracy\Helpers::clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		require __DIR__ . '/panel.phtml';
		return ob_get_clean();
	}



	/**
	 * @param string|object $url
	 * @param array $options
	 */
	public function begin($url, array $options)
	{
		if ($this->current) {return;}

		$url = new Nette\Http\Url($url);
		@parse_str($url->getQuery(), $params);
		$url->setQuery('');

		$this->calls[] = $this->current = (object) array(
			'url' => (string) $url,
			'params' => $params,
			'options' => self::toConstantNames($options),
			'result' => NULL,
			'exception' => NULL,
			'info' => array(),
			'time' => 0,
		);
	}



	/**
	 * @param mixed $result
	 * @param array $curlInfo
	 */
	public function success($result, array $curlInfo)
	{
		if (!$this->current) {return;}
		$this->totalTime += $this->current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$this->current->info = $curlInfo;
		$this->current->result = $result;
		$this->current = NULL;
	}



	/**
	 * @param \Exception $exception
	 * @param array $curlInfo
	 */
	public function failure(\Exception $exception, array $curlInfo)
	{
		if (!$this->current) {return;}
		$this->totalTime += $this->current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$this->current->info = $curlInfo;
		$this->current->exception = $exception;

		$this->current = NULL;
	}



	/**
	 * @param CurlClient $client
	 */
	public function register(CurlClient $client)
	{
		$client->onRequest[] = $this->begin;
		$client->onError[] = $this->failure;
		$client->onSuccess[] = $this->success;

		self::getDebuggerBar()->addPanel($this);
	}



	/**
	 * @return \Tracy\Bar
	 */
	private static function getDebuggerBar()
	{
		return method_exists('Tracy\Debugger', 'getBar') ? Debugger::getBar() : Debugger::$bar;
	}



	/**
	 * @param array $options
	 */
	private function toConstantNames(array $options)
	{
		static $map;
		if (!$map) {
			$map = array();
			foreach (get_defined_constants() as $name => $value) {
				if (substr($name, 0, 8) !== 'CURLOPT_') {
					continue;
				}

				$map[$value] = $name;
			}
		}

		$renamed = array();
		foreach ($options as $int => $value) {
			$renamed[isset($map[$int]) ? $map[$int] : $int] = $value;
		}

		return $renamed;
	}

}

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
	 * @param Event|\Guzzle\Http\Message\Request[]|\Guzzle\Http\Message\Response[] $event
	 */
	public function begin(Event $event)
	{
		$request = $event['request'];

		$url = new Nette\Http\UrlScript($request->getUrl());
		@parse_str($url->getQuery(), $params);

		$this->calls[spl_object_hash($request)] = (object)array(
			'url' => $url->getHostUrl() . $url->path,
			'params' => $params,
			'result' => NULL,
			'exception' => NULL,
			'info' => array(),
			'time' => 0,
		);
	}



	/**
	 * @param Event|\Guzzle\Http\Message\Request[]|\Guzzle\Http\Message\Response[] $event
	 */
	public function success(Event $event)
	{
		$response = $event['request']->getResponse();
		$current = $this->calls[spl_object_hash($event['request'])];

		$curlInfo = $response->getInfo();
		$curlInfo['method'] = $event['request']->getMethod();
		$this->totalTime += $current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$current->info = $curlInfo;

		$result = $response->getBody(TRUE);
		try {
			$result = Nette\Utils\Json::decode($result);

		} catch (Nette\Utils\JsonException $e) {
			@parse_str($result, $params);
			$result = !empty($params) ? $params : $result;
		}

		$current->result = $result;
	}



	/**
	 * @param Event|\Guzzle\Http\Message\Request[]|\Guzzle\Http\Message\Response[] $event
	 */
	public function failure(Event $event)
	{
		$response = $event['request']->getResponse();
		$current = $this->calls[spl_object_hash($event['request'])];

		$curlInfo = $response->getInfo();
		$curlInfo['method'] = $event['request']->getMethod();
		$this->totalTime += $current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$current->info = $curlInfo;
		$current->exception = $event['exception'];
	}



	/**
	 * @param HttpClient $client
	 * @return Panel
	 */
	public function register(HttpClient $client)
	{
		$client->setPanel($this);
		self::getDebuggerBar()->addPanel($this);

		return $this;
	}



	/**
	 * @return \Tracy\Bar
	 */
	private static function getDebuggerBar()
	{
		return method_exists('Tracy\Debugger', 'getBar') ? Debugger::getBar() : Debugger::$bar;
	}

}

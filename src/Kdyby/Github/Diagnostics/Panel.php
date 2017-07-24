<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Github\Diagnostics;

use Kdyby\Github\Api;
use Kdyby\Github\ApiException;
use Nette;
use Nette\Utils\Callback;
use Nette\Utils\Html;
use Tracy\Bar;
use Tracy\BlueScreen;
use Tracy\Debugger;
use Tracy\IBarPanel;



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
	 * @return string
	 */
	public function getTab()
	{
		$img = Html::el('img', array('height' => '16px'))
			->src('data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/GitHub-Mark-32px.png')));
		$tab = Html::el('span')->title('Github')->addHtml($img);
		$title = Html::el()->setText('Github');
		if ($this->calls) {
			$title->setText(
				count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
			);
		}
		return (string)$tab->addHtml($title);
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
		$esc = function ($s) {
			return htmlSpecialChars($s, ENT_QUOTES, 'UTF-8');
		};
		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) { return \Tracy\Dumper::toHtml($o, array('collapse' => $c)); }
			: Callback::closure('\Tracy\Helpers::clickableDump');
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		require __DIR__ . '/panel.phtml';
		return ob_get_clean();
	}



	/**
	 * @param Api\Request $request
	 * @param array $options
	 */
	public function begin(Api\Request $request, $options = array())
	{
		$url = $request->getUrl();
		$url->setQuery('');

		$this->calls[spl_object_hash($request)] = (object) array(
			'url' => (string) $url,
			'params' => $request->getParameters(),
			'options' => self::toConstantNames($options),
			'result' => NULL,
			'exception' => NULL,
			'info' => array(),
			'time' => 0,
		);
	}



	/**
	 * @param Api\Response $response
	 */
	public function success(Api\Response $response)
	{
		if (!isset($this->calls[$oid = spl_object_hash($response->getRequest())])) {
			return;
		}

		$debugInfo = $response->debugInfo;

		$current = $this->calls[$oid];
		$this->totalTime += $current->time = $debugInfo['total_time'];
		unset($debugInfo['total_time']);
		$current->info = $debugInfo;
		$current->info['method'] = $response->getRequest()->getMethod();
		$current->result = $response->toArray() ?: $response->getContent();
	}



	/**
	 * @param \Exception|\Throwable $exception
	 * @param \Kdyby\Github\Api\Response $response
	 */
	public function failure($exception, Api\Response $response)
	{
		if (!isset($this->calls[$oid = spl_object_hash($response->getRequest())])) {
			return;
		}

		$debugInfo = $response->debugInfo;

		$current = $this->calls[$oid];
		$this->totalTime += $current->time = $debugInfo['total_time'];
		unset($debugInfo['total_time']);
		$current->info = $debugInfo;
		$current->info['method'] = $response->getRequest()->getMethod();
		$current->exception = $exception;
	}



	/**
	 * @param Api\CurlClient $client
	 */
	public function register(Api\CurlClient $client)
	{
		$client->onRequest[] = $this->begin;
		$client->onError[] = $this->failure;
		$client->onSuccess[] = $this->success;

		self::getDebuggerBar()->addPanel($this);
		self::getDebuggerBlueScreen()->addPanel(array($this, 'renderException'));
	}



	public function renderException($e = NULL)
	{
		if (!$e instanceof ApiException || !$e->response) {
			return NULL;
		}

		$h = 'htmlSpecialChars';
		$serializeHeaders = function ($headers) use ($h) {
			$s = '';
			foreach ($headers as $header => $value) {
				if (!empty($header)) {
					$s .= $h($header) . ': ';
				}
				$s .= $h($value) . "<br>";
			}
			return $s;
		};

		$panel = '';

		$panel .= '<p><b>Request</b></p><div><pre><code>';
		$panel .= $serializeHeaders($e->request->getHeaders());
		if (!in_array($e->request->getMethod(), array('GET', 'HEAD'))) {
			$panel .= '<br>' . $h(is_array($e->request->getPost()) ? json_encode($e->request->getPost()) : $e->request->getPost());
		}
		$panel .= '</code></pre></div>';

		$panel .= '<p><b>Response</b></p><div><pre><code>';
		$panel .= $serializeHeaders($e->response->getHeaders());
		if ($e->response->getContent()) {
			$panel .= '<br>' . $h($e->response->toArray() ?: $e->response->getContent());
		}
		$panel .= '</code></pre></div>';

		return array(
			'tab' => 'Github',
			'panel' => $panel,
		);
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



	/**
	 * @return Bar
	 */
	private static function getDebuggerBar()
	{
		return method_exists('Tracy\Debugger', 'getBar') ? Debugger::getBar() : Debugger::$bar;
	}



	/**
	 * @return BlueScreen
	 */
	private static function getDebuggerBlueScreen()
	{
		return method_exists('Tracy\Debugger', 'getBlueScreen') ? Debugger::getBlueScreen() : Debugger::$blueScreen;
	}

}

<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 7/30/15
 * Time: 12:13 PM
 */

namespace OCA\Proxy;

use OCA\Proxy\Provider\PageKite;
use OCA\Proxy\Provider;
use OCP\IL10N;

/**
 * Class Helper
 *
 * @package OCA\Proxy
 */
class Helper {

	private $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	/**
	 * @return Provider[]
	 */
	public function getRegisteredProviders() {
		// TODO: DI
		return [
			new PageKite(
				$this->l10n,
				\OC::$server->getConfig(),
				\OC::$server->getHTTPClientService()
			),
		];

	}
}
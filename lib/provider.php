<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Proxy;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Class Provider
 *
 * @package OCA\Proxy\Provider
 */
abstract class Provider {
	/** @var IConfig */
	protected $config;
	/** @var IClientService */
	protected $clientService;
	/** @var IL10N */
	protected $l10n;

	/**
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IClientService $clientService
	 */
	public function __construct(IL10N $l10n,
								  IConfig $config,
								  IClientService $clientService) {
		$this->l10n = $l10n;
		$this->config = $config;
		$this->clientService = $clientService;
	}

	/**
	 * Gets the name of the provider that the user can choose from
	 *
	 * @return string
	 */
	abstract function getName();

	abstract function getDescription();

	private function convertHtmlElements(array $elements) {
		$html = '';

		foreach($elements as $key => $config) {
			if($config['type'] === 'input') {
				$html .= '<input type="text" name="'.$key.'" placeholder="'.$config['description'].'">';
				$html .= '<br/>';
			} elseif($config['type'] === 'password') {
				$html .= '<input type="password" name="'.$key.'" placeholder="'.$config['description'].'">';
				$html .= '<br/>';
			} elseif ($config['type'] === 'checkbox') {
				$html .= '<p><input type="checkbox" name="oca_relay_'.$key.'" id="oca_relay'.$key.'" />';
				$html .= '<label for="oca_relay_tos_accepted">'.$config['description'].'</label><br></p>';
			}
		}

		return $html;
	}

	/**
	 * @return string
	 */
	public function buildLoginHtml() {
		return $this->convertHtmlElements($this->requiredLoginInformation());
	}

	/**
	 * @return string
	 */
	public function buildRegisterHtml() {
		return $this->convertHtmlElements($this->requiredInformationRegistration());
	}


	abstract function requiredInformationRegistration();

	abstract function requiredLoginInformation();


	abstract function isRegistered();

	/**
	 * @return mixed
	 */
	abstract function startRelay();

	/**
	 * @return string
	 */
	abstract function getDomain();

	/**
	 * @param array $parameters
	 */
	abstract function storeConfig(array $parameters);


	abstract function createAccount(array $parameters);

	/**
	 * @return bool
	 */
	abstract function stopRelay();

	/**
	 * @return string|null
	 */
	abstract function getPid();

	public function getPidInfo() {
		return exec(sprintf('ps -p %s', $this->getPid()));
	}
}

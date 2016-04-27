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

use GuzzleHttp\Exception\ServerException;
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

	/**
	 * An ID that can be used to reference the
	 *
	 * @return string
	 */
	public function getId() {
		return strtolower(str_replace(' ', '', $this->getName()));
	}

	abstract function getDescription();

	abstract function getDependencies();

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
				$html .= '<label for="oca_relay'.$key.'">'.$config['description'].'</label><br></p>';
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
	 * Starts the persistence helper script
	 */
	protected function startPersistenceHelper() {
		$cmd = sprintf(
			'nohup >/dev/null 2>&1 php %s & echo $!',
			__DIR__ . '/../lib/jobs/standalonepersistence.php'
		);
		$forkedPid = exec($cmd);
		// Stop the old persistence helper after the new one has been started
		$this->stopPersistenceHelper();
		$this->config->setAppValue('proxy', 'persistence.pid', $forkedPid);
	}

	/**
	 * Stops the persistence helper script
	 */
	public function stopPersistenceHelper() {
		$persistencePid = $this->getPersistencePid();
		if($persistencePid !== '') {
			exec('kill -9 '.$persistencePid);
		}
		$this->config->deleteAppValue('proxy', 'persistence.pid');
	}

	/**
	 * Get the pid of the provider
	 *
	 * @return string|null
	 */
	public function getPid() {
		$pid = $this->config->getAppValue('proxy', 'provider.'.$this->getId().'.startedPid', null);
		if($pid !== null) {
			\OC::$server->getLogger()->debug('Checking existence of '.$pid, ['app' => 'proxy']);
			exec("ps -p $pid", $output);
			\OC::$server->getLogger()->debug('Output of '.$pid.': '.print_r($output, true), ['app' => 'proxy']);

			// FIXME: Check on Linux
			if (count($output) > 1) {
				\OC::$server->getLogger()->debug($pid . ' is running', ['app' => 'proxy']);
				return $pid;
			}
		}

		\OC::$server->getLogger()->debug($pid . ' is not running', ['app' => 'proxy']);
		return null;
	}

	/**
	 * Set the PID for the provider
	 *
	 * @param string $pid
	 */
	protected function setPid($pid) {
		$this->config->setAppValue('proxy', 'provider.'.$this->getId().'.startedPid', $pid);
	}

	/**
	 * @param string $pid
	 * @return string
	 */
	private function getInfoByPid($pid) {
		return exec(sprintf('ps %s', $pid));
	}

	/**
	 * @return string
	 */
	public function getPidInfo() {
		return $this->getInfoByPid($this->getPid());
	}

	/**
	 * @return string
	 */
	public function getPersistencePid() {
		return $this->config->getAppValue('proxy', 'persistence.pid', '');
	}

	/**
	 * @return string
	 */
	public function getPersistenceInfo() {
		$persistencePid = $this->getPersistencePid();
		if($persistencePid !== '') {
			return $this->getInfoByPid($this->getPersistencePid());
		}
		return '';
	}

	/**
	 * Checks if the relay at the specified URL is working
	 *
	 * @param string $relayUrl
	 * @return bool
	 */
	public function isRelayWorking($relayUrl) {
		for ($i = 1; $i <= 30; $i++) {
			sleep(5);
			$client = $this->clientService->newClient();
			try {
				\OC::$server->getLogger()->debug('Looking for connection at '.$relayUrl, ['app' => 'proxy']);
				$response = json_decode($client->get($relayUrl, ['verify' => false])->getBody(), true);
				if(is_array($response)) {
					\OC::$server->getLogger()->debug('Connection found at '.$relayUrl, ['app' => 'proxy']);
					return true;
				}
			} catch (\Exception $e) {
			}
			\OC::$server->getLogger()->debug('No connection found at '.$relayUrl, ['app' => 'proxy']);
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function isConnectionStarting() {
		$startingTime = $this->config->getAppValue('proxy', 'connection.starting', '');
		if(($startingTime !== '') && ((time() - $startingTime) < 300)) {
			return true;
		}
		return false;
	}

}

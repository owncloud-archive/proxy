<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 7/29/15
 * Time: 9:01 PM
 */

namespace OCA\Proxy\Provider;

use GuzzleHttp\Exception\ServerException;
use OCA\Proxy\Provider;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Class PageKite
 *
 * @package OCA\Proxy\Provider
 */
class PageKite extends Provider {
	/** @var string */
	private $scriptLocation;
	/** @var string */
	private $rpcUrl = 'http://pagekite.net/xmlrpc/';
	/** @var \Zend\XmlRpc\Client\ */
	private $xmlRpcClient;

	/**
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IClientService $clientService
	 */
	public function __construct(IL10N $l10n,
								IConfig $config,
								IClientService $clientService) {
		parent::__construct($l10n, $config, $clientService);

		$this->scriptLocation = __DIR__ . '/../../scripts/pagekite/pagekite.py';

		// TODO: DI
		$this->xmlRpcClient = new \Zend\XmlRpc\Client($this->rpcUrl);
		$client = new \Zend\Http\Client();
		$client->setAdapter('Zend\Http\Client\Adapter\Proxy');
		$client->setOptions([
			'sslcafile' => \OC::$SERVERROOT . '/config/ca-bundle.crt',
			//'proxy_host' => $config->getSystemValue(),
			//'proxy_port' => 8080,
		]);
		$this->xmlRpcClient->setHttpClient($client);
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Pagekite';
	}

	/**
	 * @return string
	 */
	public function getDomain() {
		return $this->config->getSystemValue('instanceid') . '.pagekite.me';
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return (string)$this->l10n->t('Pagekite is an offering by "The Beanstalks Project". A free trial option offers 1 month of usage including 2.5 GB of transfer data.<br><a href="%s">Read more about the pricing options.</a>', ['https://pagekite.net/signup/?more=bw']);
	}

	/**
	 * @return array
	 */
	public function requiredInformationRegistration() {
		return [
			'mailAddress' => [
				'type' => 'input',
				'description' => $this->l10n->t('Mail address'),
			],
			'firstName' => [
				'type' => 'input',
				'description' => $this->l10n->t('First name (optional)'),
			],
			'lastName' => [
				'type' => 'input',
				'description' => $this->l10n->t('Last name (optional)'),
			],
			'company' => [
				'type' => 'input',
				'description' => $this->l10n->t('Company (optional)'),
			],
			'newsletter' => [
				'type' => 'checkbox',
				'description' => $this->l10n->t('Subscribe to ownCloud newsletter (optional)'),
			],
			'tos' => [
				'type' => 'checkbox',
				'description' => $this->l10n->t('Accept the <a href="%s">Terms of Service</a>', ['https://pagekite.net/support/terms/']),
			],
		];
	}

	/**
	 * @return array
	 */
	public function requiredLoginInformation() {
		return [
			'mailAddress' => [
				'type' => 'input',
				'description' => $this->l10n->t('Mail address'),
			],
			'password' => [
				'type' => 'password',
				'description' => $this->l10n->t('Password'),
			],
		];
	}

	/**
	 * @param string $kitename
	 * @param string $kitesecret
	 */
	private function writeConfigFile($kitename, $kitesecret) {
		$config = "kitename = ".$kitename ."\n";
		$config .= "kitesecret = ".$kitesecret."\n";
		$config .= "defaults\n";
		$config .= "service_on  = http:@kitename : localhost:80 : @kitesecret\n";
		$config .= "END\n";
		file_put_contents(\OC::$SERVERROOT .'/data/pagekite.cfg', $config);
	}

	/**
	 * TODO: Error handling
	 *
	 * @param string $accountIdentifier
	 * @param string $accessCredentials
	 * @return array
	 */
	private function getAccountInfo($accountIdentifier,
									$accessCredentials) {
		$response = $this->xmlRpcClient->call('get_account_info', [$accountIdentifier, $accessCredentials, '']);
		$information = [];

		foreach($response[1]['data']['kites'] as $data) {
			$information['subdomains'][] = $data['domain'];
		}
		$information['account']['sharedSecret'] = $response[1]['data']['_ss'];
		$information['account']['quota']['megabyte'] = $response[1]['data']['quota_mb_left'];
		$information['account']['quota']['days'] = $response[1]['data']['days_left'];
		return $information;
	}

	public function stopRelay() {
		$pid = $this->getPid();
		if($pid !== null) {
			exec("kill -9 $pid");
			return true;
		}

		return false;
	}

	/**
	 * @param array $parameters
	 * @throws \Exception
	 */
	public function storeConfig(array $parameters) {
		$response = $this->xmlRpcClient->call(
			'login',
			[
				$parameters['mailAddress'],
				$parameters['password'],
				'',
			]
		);

		if($response[0] !== 'ok') {
			throw new \Exception('Invalid login credentials');
		}

		$accountIdentifier = $response[1][0];
		$accessCredentials = $response[1][1];

		$response = $this->getAccountInfo($accountIdentifier, $accessCredentials);

		// FIXME: Refactor

		// Register kite if it does not exist
		if(!in_array($this->getDomain(), $response['subdomains'])) {
			$response = $this->xmlRpcClient->call(
				'add_kite',
				[
					$parameters['mailAddress'],
					$parameters['password'],
					$this->getDomain(),
					true,
				]
			);

			if($response[0] === 'domaintaken') {
				throw new \Exception(sprintf('%s is already taken. Please choose another one.', $this->getDomain()));
			}
		}
		$this->config->setAppValue('proxy', 'pagekiteDomain', $this->getDomain());

		$this->writeConfigFile($this->getDomain(), $response['account']['sharedSecret']);
	}


	/**
	 * @return bool
	 */
	public function startRelay() {
	// TODO: Test in longer running environment on Ubuntu
		$logFile = \OC::$SERVERROOT.'/data/pagekite.log';
		// Delete the existing logfile
		unlink($logFile);

		$forkedPid = exec('nohup >/dev/null 2>&1 python '.$this->scriptLocation.' --clean --optfile '.\OC::$SERVERROOT .'/data/pagekite.cfg & echo $!');

		// FIXME: Revoke if not working connection
		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);
		$trustedDomains[] = $this->getDomain();
		$this->config->setSystemValue('trusted_domains', $trustedDomains);
		$relayWorking = $this->isRelayWorking('http://'.$this->getDomain().\OC::$WEBROOT);

		if($relayWorking) {
			$this->config->setAppValue('relay', 'provider.pagekite.startedPid', $forkedPid);
			return true;
		} else {
			exec('kill -9 '.$forkedPid);
			return false;
		}
	}

	/**
	 * Checks if the relay at the specified URL is working
	 *
	 * @param string $relayUrl
	 * @return bool
	 */
	public function isRelayWorking($relayUrl) {
		set_time_limit(60);
		for ($i = 1; $i <= 10; $i++) {
			sleep(5);
			$client = $this->clientService->newClient();
			try {
				$client->get($relayUrl);
			} catch (ServerException $e) {
				if($e->getResponse()->getStatusCode() === 503) {
					continue;
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * @return string|null
	 */
	public function getPid() {
		$pid = $this->config->getAppValue('relay', 'provider.pagekite.startedPid', null);
		if($pid !== null) {
			exec("ps -p $pid", $output);

			// FIXME: Check on Linux
			if (count($output) > 1) {
				return $pid;
			}
		}

		return null;
	}

	/**
	 * @param array $params
	 * @throws \Exception
	 */
	public function createAccount(array $params) {
		$response = $this->xmlRpcClient->call(
			'create_account',
			[
				'',
				'',
				$params['mailAddress'],
				$this->config->getSystemValue('instanceid') . '.pagekite.me',
				isset($params['oca_relay_tos']) && $params['oca_relay_tos'] === 'on',
				true,
				false
			]
		);

		// FIXME: Correct account creation
		if($response[0] !== 'ok') {
			throw new \Exception($response[1]);
		}


	}

	// FIXME
	public function isRegistered() {
		if(file_exists(\OC::$SERVERROOT . '/data/pagekite.cfg')) {
			return true;
		}

		return false;
	}
}

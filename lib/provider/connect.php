<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 7/29/15
 * Time: 9:01 PM
 */

namespace OCA\Proxy\Provider;

use GuzzleHttp\Exception\ServerException;
use OCA\Proxy\Dependencies\Kill;
use OCA\Proxy\Dependencies\Nohup;
use OCA\Proxy\Dependencies\PHP;
use OCA\Proxy\Dependencies\Ps;
use OCA\Proxy\Dependencies\SSH;
use OCA\Proxy\Provider;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Class PageKite
 *
 * @package OCA\Proxy\Provider
 */
class Connect extends Provider {
	/**
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IClientService $clientService
	 */
	public function __construct(IL10N $l10n,
								IConfig $config,
								IClientService $clientService) {
		parent::__construct($l10n, $config, $clientService);
	}

	public function getDependencies() {
		return [
			new SSH(),
			new Nohup(),
			new Kill(),
			new Ps(),
			new PHP(),
		];
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'ownCloud Connect';
	}

	/**
	 * @return string
	 */
	public function getDomain() {
		return $this->config->getAppValue('relay', 'provider.connect.domain', '');
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return (string)$this->l10n->t('ownCloud Connect is a <strong>free service</strong> offered to you by a cooperation by Datto Inc. and the ownCloud community.');
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

	public function stopRelay() {
		$pid = $this->getPid();
		if($pid !== null) {
			exec("kill -9 $pid");
			$this->config->deleteAppValue('proxy', 'provider.'.$this->getId().'.startedPid');
			return true;
		}

		return false;
	}

	/**
	 * @param array $parameters
	 * @throws \Exception
	 */
	public function storeConfig(array $parameters) {
		// TODO: Inject
		$client = \OC::$server->getHTTPClientService()->newClient();
		$response = json_decode($client->post(
			'https://ec2-52-24-200-183.us-west-2.compute.amazonaws.com/datto_read.php',
			[
				'body' => [
					'mailAddress' => $parameters['mailAddress'],
					'password' => $parameters['password'],
				],
				'verify' => __DIR__ . '/../../appinfo/ec2.crt',
			]
		)->getBody(), true);

		if($response === 'false') {
			throw new \Exception('Invalid login credentials');
		}
		file_put_contents($this->getDataDirectory() . '/connect.crt', $response['cert']);
		file_put_contents($this->getDataDirectory(). '/connect.key', $response['key']);
		chmod($this->getDataDirectory() . '/connect.crt', 0600);
		chmod($this->getDataDirectory() . '/connect.key', 0600);
	}

	private function getDataDirectory() {
		return \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT.'/data/');
	}

	/**
	 * @return bool
	 */
	public function startRelay() {
		if($this->isConnectionStarting()) {
			return false;
		}
		$this->config->setAppValue('proxy', 'connection.starting', time());

		// Request the configuration values from the Connect Backend
		$ch = curl_init();
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
			CURLOPT_URL => 'https://api.owncloudconnect.com/cgi-bin/device/connect_params',
			CURLOPT_CAINFO => __DIR__ . '/../../appinfo/chain.pem',
			CURLOPT_SSLCERT => $this->getDataDirectory() . '/connect.crt',
			CURLOPT_SSLKEY => $this->getDataDirectory() . '/connect.key',
		);
		curl_setopt_array($ch , $options);
		$response = json_decode(curl_exec($ch), true);

		// Not a valid decoded JSON object
		if(!is_array($response)) {
			return false;
		}

		// Parse the response
		$servicePort = $response['service_port'];
		$sshPort = $response['ssh_port'];
		$host = $response['host'];
		$ticket = $response['ticket'];
		$url = rtrim($response['url'], '/');
		$this->config->setAppValue('relay', 'provider.connect.domain', parse_url($url, PHP_URL_HOST));

		// Add as trusted domain
		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);
		$trustedDomains[] = $this->getDomain();
		$this->config->setSystemValue('trusted_domains', $trustedDomains);

		// Try to open a connection
		$cmd = sprintf(
			'nohup >/dev/null 2>&1 ssh -qqq -R 0.0.0.0:%d:localhost:80 -i %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o BatchMode=yes -o PreferredAuthentications=publickey -o Port=%d das-forward@%s -- web %s %s & echo $!',
			$servicePort,
			$this->getDataDirectory() . '/connect.key',
			$sshPort,
			$host,
			$ticket,
			''
		);
		$forkedPid = exec($cmd);

		// The path differs in case of the CLI
		$path = \OC::$server->getSystemConfig()->getValue('overwrite.cli.url', \OC::$SERVERROOT);
		$path = rtrim(parse_url($path, PHP_URL_PATH), '/');
		if($path === '') {
			$path = '/';
		}

		$relayWorking = $this->isRelayWorking($url.'/'.$path.'/status.php');
		if($relayWorking) {
			$this->setPid($forkedPid);
			$this->startPersistenceHelper();
			$this->config->deleteAppValue('proxy', 'connection.starting');
			return true;
		} else {
			exec('kill -9 '.$forkedPid);
			$this->config->deleteAppValue('proxy', 'connection.starting');
			return false;
		}
	}

	/**
	 * @param array $params
	 * @throws \Exception
	 */
	public function createAccount(array $params) {
		$client = $this->clientService->newClient();
		$response = $client->post(
			'https://ec2-52-24-200-183.us-west-2.compute.amazonaws.com/datto.php',
			[
				'body' => [
					'mailAddress' => isset($params['mailAddress']) ? $params['mailAddress'] : '',
				],
				'verify' => __DIR__ . '/../../appinfo/ec2.crt',
			]
		)->getBody();

		if($response !== 'done') {
			throw new \Exception($response);
		}
	}

	public function isRegistered() {
		if($this->getDomain() !== '') {
			return true;
		}

		return false;
	}
}

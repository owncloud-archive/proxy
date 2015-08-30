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

namespace OCA\Proxy\Controller;

use OCA\Proxy\Helper;
use OCA\Proxy\Provider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Class Settings
 *
 * @package OCA\Proxy\Controller
 */
class SettingsController extends Controller {
	/** @var Helper */
	private $helper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param Helper $helper
	 */
	public function __construct($appName,
								IRequest $request,
								Helper $helper) {
		parent::__construct($appName, $request);
		$this->helper = $helper;
	}

	/**
	 * @param $providerClass
	 * @return JSONResponse
	 */
	public function startProcess($providerClass) {
		// FIXME: Support different providers
		$providerClass = new Provider\PageKite(
			\OC::$server->getL10N($this->appName),
			\OC::$server->getConfig(),
			\OC::$server->getHTTPClientService()
		);

		$providerClass->startRelay();
		return new JSONResponse();
	}

	/**
	 * @param string $providerClass
	 * @return JSONResponse
	 */
	public function stopProcess($providerClass) {
		$providerClass = $this->request->getParam('providerClass');

		// FIXME: Support different providers
		$providerClass = new Provider\PageKite(
			\OC::$server->getL10N($this->appName),
			\OC::$server->getConfig(),
			\OC::$server->getHTTPClientService()
		);

		$state = $providerClass->stopRelay();
		return new JSONResponse($state);
	}

	/**
	 * @param $provider
	 * @return Http\DataResponse|JSONResponse
	 *
	 * @param string $provider
	 */
	public function register($provider) {
		$requestParams = $this->request->getParams();
		if(!is_subclass_of($provider, '\OCA\Proxy\Provider')) {
			return new Http\DataResponse('Provider is not valid', Http::STATUS_FORBIDDEN);
		}

		/** @var Provider $providerClass */
		$providerClass = new $provider(
		// TODO: DI
			\OC::$server->getL10N($this->appName),
			\OC::$server->getConfig(),
			\OC::$server->getHTTPClientService()
		);

		// FIXME: Subscription to news letter, refactor out of here
		if(isset($requestParams['oca_relay_newsletter']) && $requestParams['oca_relay_newsletter'] === 'on') {
			$client = \OC::$server->getHTTPClientService()->newClient();
			$client->post(
				'https://ec2-52-24-200-183.us-west-2.compute.amazonaws.com/',
				[
					'body' => [
						'mailAddress' => isset($requestParams['mailAddress']) ? $requestParams['mailAddress'] : '',
						'firstName' => isset($requestParams['firstName']) ? $requestParams['firstName'] : '',
						'lastName' => isset($requestParams['lastName']) ? $requestParams['lastName'] : '',
						'company' => isset($requestParams['company']) ? $requestParams['company'] : '',
					],
					'verify' => __DIR__ . '/../appinfo/ec2.crt',
				]
			)->getBody();
		}

		try {
			$providerClass->createAccount($requestParams);
		} catch (\Exception $e) {
			return new JSONResponse($e->getMessage(), Http::STATUS_UNAUTHORIZED);
		}

		return new Http\DataResponse('Registration successful, please check your mail.');
	}

	/**
	 * @param $provider
	 * @return Http\DataResponse|JSONResponse
	 *
	 * @param string $provider
	 */
	public function login($provider) {

		$requestParams = $this->request->getParams();
		if(!is_subclass_of($provider, '\OCA\Proxy\Provider')) {
			return new Http\DataResponse('Provider is not valid', Http::STATUS_FORBIDDEN);
		}

		/** @var Provider $providerClass */
		$providerClass = new $provider(
		// TODO: DI
			\OC::$server->getL10N($this->appName),
			\OC::$server->getConfig(),
			\OC::$server->getHTTPClientService()
		);

		try {
			$providerClass->storeConfig($requestParams);
			$response = $providerClass->startRelay();
		} catch (\Exception $e) {
			return new JSONResponse($e->getMessage(), Http::STATUS_UNAUTHORIZED);
		}

		if($response === true) {
			return new JSONResponse(['state' => $response, 'domain' => 'https://'.$providerClass->getDomain()], Http::STATUS_OK);
		} else {
			return new JSONResponse($response, Http::STATUS_UNAUTHORIZED);
		}
	}

	/**
	 * @return Http\DataResponse
	 */
	public function getState() {
		$providers = $this->helper->getRegisteredProviders();
		$domain = '';
		foreach($providers as $provider) {
			$domain = $provider->getDomain();
		}

		$requestUrl = 'https://ec2-52-24-200-183.us-west-2.compute.amazonaws.com/state.php';
		if($domain !== '') {
			$requestUrl .= '?remote='.$domain.\OC::$WEBROOT.'/';
		}

		$client = \OC::$server->getHTTPClientService()->newClient();
		$response = $client->get(
			$requestUrl,
			[
				'verify' => __DIR__ . '/../appinfo/ec2.crt',
			]
		)->getBody();

		if($response === 'false') {
			return new Http\DataResponse([], Http::STATUS_FORBIDDEN);
		} else {
			return new Http\DataResponse($response);
		}
	}

	/**
	 * @return TemplateResponse
	 */
	public function displayPanel() {
		$providers = $this->helper->getRegisteredProviders();

		return new TemplateResponse(
			$this->appName,
			'settings',
			[
				'providers' => $providers,
			],
			''
		);
	}
}

<?php

namespace OCA\Proxy\Jobs;
use OC\BackgroundJob\TimedJob;
use OCA\Proxy\Helper;

/**
 * Class ForkToCron moves already running jobs started in the httpd process to
 * the cron process. It runs all
 *
 * @package OCA\Proxy\Jobs
 */
class ForkToCron extends TimedJob {

	public function __construct() {
		// Execute all 5 minutes
		$this->setInterval(1);
	}

	public function run($argument) {
		// We only can fork from cron
		if(\OCP\BackgroundJob::getExecutionType() !== 'cron') {
			return;
		}

		// Get the registered providers
		$helper = new Helper(\OC::$server->getL10N('proxy'));
		$providers = $helper->getRegisteredProviders();

		foreach($providers as $provider) {
			// Not registered = We don't care
			if(!$provider->isRegistered()) {
				continue;
			}

			// Stop the existing relay and start a new one if not started via cron
			$cronPid = \OC::$server->getConfig()->getAppValue('proxy', 'oca.proxy.cron.pid', '');
			$pid = $provider->getPid();

			if($cronPid !== $pid) {
				$provider->stopPersistenceHelper();
				$provider->stopRelay();
				$provider->startRelay();
				sleep(1);
				$newPid = $provider->getPid();
				\OC::$server->getConfig()->setAppValue('proxy', 'oca.proxy.cron.pid', $newPid);
			}
		}
	}

}

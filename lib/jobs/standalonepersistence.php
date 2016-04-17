<?php

require_once __DIR__ . '/../../../../lib/base.php';
include_once __DIR__ . '/../../vendor/autoload.php';


if (!OC::$CLI) {
	echo "This script can be run from the command line only" . PHP_EOL;
	exit(0);
}

$helper = new \OCA\Proxy\Helper(\OC::$server->getL10N('proxy'));
$providers = $helper->getRegisteredProviders();
while(1) {
	foreach ($providers as $provider) {
		if ($provider->isRegistered()) {
			// Check if PID is still there
			if($provider->getPid() === null) {
				$provider->startRelay();
				continue;
			}

			$domain = $provider->getDomain();
			// The path differs in case of the CLI
			$path = \OC::$server->getSystemConfig()->getValue('overwrite.cli.url', \OC::$SERVERROOT);
			$path = rtrim(parse_url($path, PHP_URL_PATH), '/');
			if($path === '') {
				$path = '/';
			}

			// Check if maybe just the client is down
			if(!$provider->isRelayWorking('https://'.$domain.'/'.$path.'/status.php')) {
				$provider->stopRelay();
				$provider->startRelay();
				continue;
			}
		}
	}
	sleep(10);
}

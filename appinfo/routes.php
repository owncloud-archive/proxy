<?php

return [
	'routes' =>
		[
			[
				'name' => 'Settings#login',
				'url' => '/api/login',
				'verb' => 'POST',
			],
			[
				'name' => 'Settings#register',
				'url' => '/api/register',
				'verb' => 'POST',
			],
			[
				'name' => 'Settings#stopProcess',
				'url' => '/api/stop',
				'verb' => 'POST',
			],
			[
				'name' => 'Settings#startProcess',
				'url' => '/api/start',
				'verb' => 'POST',
			],
			[
				'name' => 'Settings#getState',
				'url' => '/api/state',
				'verb' => 'GET',
			],
		]
];

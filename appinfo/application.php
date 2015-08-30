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

namespace OCA\Proxy\AppInfo;

use OCA\Proxy\Controller\SettingsController;
use OCA\Proxy\Helper;
use OCP\AppFramework\App;
use OCP\IContainer;

/**
 * Class Application
 *
 * @package OCA\Proxy\AppInfo
 */
class Application extends App {
	/**
	 * @param array $urlParams
	 */
	public function __construct (array $urlParams = []) {
		parent::__construct('proxy', $urlParams);
		$container = $this->getContainer();

		$container->registerService('SettingsController', function(IContainer $c) {
			return new SettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Helper')
			);
		});

		$container->registerService('Helper', function(IContainer $c) {
			return new Helper(
				\OC::$server->getL10N($c->query('AppName'))
			);
		});

	}
}

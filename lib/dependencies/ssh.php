<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 4/18/16
 * Time: 5:56 PM
 */

namespace OCA\Proxy\Dependencies;

use OCA\Proxy\Dependency;

class SSH extends Dependency {
	public function getName() {
		return 'SSH';
	}

	public function isAvailable() {
		$return = shell_exec('command -v ssh');
		return !empty($return);
	}
}

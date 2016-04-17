<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 4/18/16
 * Time: 5:56 PM
 */

namespace OCA\Proxy\Dependencies;

use OCA\Proxy\Dependency;

class Nohup extends Dependency {
	public function getName() {
		return 'nohup';
	}

	public function isAvailable() {
		$return = shell_exec('command -v nohup');
		return !empty($return);
	}
}

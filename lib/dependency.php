<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 4/18/16
 * Time: 5:56 PM
 */

namespace OCA\Proxy;

abstract class Dependency {
	abstract function getName();
	abstract function isAvailable();
}

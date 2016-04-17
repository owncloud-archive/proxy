<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

/** @var array $_ */
/** @var \OCA\Proxy\Provider $registeredProvider */
$registeredProvider = $_['registeredProvider'];
/** @var \OCA\Proxy\Provider[] $providers */
$providers = $_['providers'];
script('proxy', 'js');
style('proxy', 'style');
?>
<div class="section">
	<h2 style="display:inline-block;"><?php p($l->t('ownCloud Connect')) ?></h2>
	<a target="_blank" class="icon-info svg" title="<?php p($l->t('Open documentation'));?>" href="https://owncloud.org/connect/"></a>

	<!-- Connection state !-->
	<div id="oca-connect-check-connection" class="oca-connect-connection-state">
		<img class="oca-proxy-connectivity-check" src="<?php p(image_path('core', 'loading.gif')); ?>" style="height:10px"/>
		<?php p($l->t('Verifying network connection')) ?>
	</div>
	<div id="oca-proxy-no-connection" class="oca-connect-connection-state" style="display:none">
		<span class="oca-proxy-error oca_proxy_inlineblock_title"></span>
		<?php p($l->t('Not online yet')) ?>
	</div>
	<div id="oca-proxy-connection-working" class="oca-connect-connection-state" style="display:none">
		<span class="oca-proxy-success oca_proxy_inlineblock_title"></span>
		<?php p($l->t('Online at ')) ?><div id="oca-proxy-domain-address-replacement" style="display:inline;"></div>
	</div>

	<?php if($_['registeredProvider'] === ''): ?>
		<!-- Step 1: Choose the provider -->
		<div id="oca-proxy-step1">
			<p><?php p($l->t('ownCloud Connect allows you to make your ownCloud easily accessible to the public using an external service. To begin please choose the provider that you want to use:')) ?></p>

			<?php foreach($providers as $provider): ?>
				<h3><?php p($provider->getName()) ?></h3>
				<p><?php print_unescaped($provider->getDescription()) ?></p>
				<button value="<?php p($provider->getId()) ?>"><?php p($l->t('Use %s', [$provider->getName()])) ?></button>
			<?php endforeach; ?>
		</div>
		<!-- Step 1: Choose the provider -->

		<!-- Step 2: Dependency Check -->
		<?php foreach($providers as $provider): ?>
			<div id="oca-proxy-step2-<?php p($provider->getId()) ?>" class="hidden">
				<p><?php p($l->t('You are just a few steps away from making your ownCloud accessible via %s!', [$provider->getName()])) ?></p>

				<h3><?php p($l->t('Dependency Check')) ?></h3>
				<ul>
					<?php
						$missingDeps = false;
						/** @var \OCA\Proxy\Dependency $dependency */
						foreach($provider->getDependencies() as $dependency):
						if(!$dependency->isAvailable()) {
							$missingDeps = true;
						}
						$icon = ($dependency->isAvailable()) ? '&#10004;' : '&#x2717;';
					?>
						<li><?php print_unescaped($icon) ?> <?php p($dependency->getName()) ?></li>
					<?php endforeach; ?>
					<?php if($missingDeps): ?>
						<em><?php p($l->t('You have missing dependencies. Please install the missing dependencies.')) ?></em>
					<?php else: ?>
						<button><?php p($l->t('Proceed')) ?></button>
					<?php endif;?>
				</ul>
			</div>
		<?php endforeach; ?>
		<!-- Step 2: Dependency Check -->

		<!-- Step 3: Register -->
		<?php foreach($providers as $provider): ?>
			<div id="oca-proxy-step3-<?php p($provider->getId()) ?>" class="hidden">
				<p><?php p($l->t('Just some more registration information before we can start.')) ?></p>
				<div class="oca-proxy-box">
					<h3><?php p($l->t('Log in to existing account')) ?></h3>
					<form class="oca_proxy_login_existing">
						<input type="hidden" name="provider" value="<?php p(get_class($provider)) ?>">
						<?php print_unescaped($provider->buildLoginHtml()) ?>
						<img class="oca_proxy_login_spinner hidden" src="<?php p(image_path('core', 'loading.gif')); ?>"/>
						<input class="oca_proxy_login_submit" type="submit" value="Log in">
					</form>
				</div>

				<div class="oca-proxy-box ">
					<h3><?php p($l->t('Register new account')) ?></h3>
					<form class="oca_proxy_register">
						<input type="hidden" name="provider" value="<?php p(get_class($provider)) ?>">
						<?php print_unescaped($provider->buildRegisterHtml()) ?>
						<input class="oca_proxy_register_submit" type="submit" value="Register">
					</form>
					<div class="oca_proxy_registration_successful hidden">
						<?php p($l->t('You have been successfully registered. Please check your email for your user credentials.')) ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<!-- Step 3: Register -->

	<?php else: ?>

		<!-- Rendering of admin template -->
		<div id="oca-proxy-<?php p($registeredProvider->getId()) ?>">
			<p><?php print_unescaped($registeredProvider->getDescription()) ?></p>
			<h3><?php p($l->t('Domain:')) ?> <?php p($registeredProvider->getDomain()) ?></h3>
			<?php if($registeredProvider->isConnectionStarting()): ?>
				<em><?php p($l->t('Trying to connect to the backend. Please be patient…')) ?></em><br/>
			<?php endif;?>
			<button class="oca_proxy_stop" <?php if($registeredProvider->getPid() === null): ?>disabled<?php endif;?>><?php p($l->t('Stop process')) ?></button>
			<button class="oca_proxy_restart" <?php if($registeredProvider->getPid() !== null || $registeredProvider->isConnectionStarting()): ?>disabled<?php endif;?>><?php p($l->t('Start process')) ?></button>
			<?php if($registeredProvider->getPid() !== null): ?>
				<br/>
				<small id="oca-proxy-technical-details-title" style="cursor: pointer;opacity: 0.3"><?php p($l->t('Technical details / Debugging')) ?><div class="icon-triangle-s" style="display: inline-block; padding: 5px;vertical-align: middle;cursor: pointer;"></div></small>
				<div id="oca-proxy-technical-details" class="hidden">
					<p><?php p($l->t('The following details are meant for debugging purposes. In case you encounter a service outage restarting the proxy service might help.')) ?></p>
						<strong><?php p($l->t('Connector PID:')) ?></strong> <?php p($registeredProvider->getPid()) ?><br/>
						<strong><?php p($l->t('Connector PID info:')) ?></strong><br/>
						<code><?php p($registeredProvider->getPidInfo()) ?></code><br/>
						<strong><?php p($l->t('Persistence PID:'))?></strong><?php p($registeredProvider->getPersistencePid()) ?><br/>
						<strong><?php p($l->t('Persistence PID info:')) ?></strong><br/>
						<code><?php p($registeredProvider->getPersistenceInfo()) ?></code><br/>
				</div>
			<?php endif; ?>
			<input type="hidden" id="oca-proxy-active-provider-class" value="<?php p(get_class($registeredProvider)) ?>"/>
		</div>
		<!-- Rendering of admin template -->

	<?php endif; ?>
</div>

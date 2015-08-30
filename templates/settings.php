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

/** @var array $_ */
/** @var \OCA\Proxy\Provider[] $providers */
$providers = $_['providers'];
script('proxy', 'js');
style('proxy', 'style');
?>
<div class="section">
	<h2 style="display:inline-block;"><?php p($l->t('ownCloud Proxy')) ?></h2>
	<a target="_blank" class="icon-info svg" title="<?php p($l->t('Open documentation'));?>" href="<?php p(link_to_docs('admin-sharing-federated')); ?>"></a>

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

	<?php foreach($providers as $provider): ?>
		<p><?php print_unescaped($provider->getDescription()) ?></p>
		<?php if($provider->isRegistered()): ?>
			<h3><?php p($l->t('Domain:')) ?> <?php p($provider->getDomain()) ?></h3>
			<button class="oca_proxy_stop" <?php if($provider->getPid() === null): ?>disabled<?php endif;?>><?php p($l->t('Stop process')) ?></button>
			<button class="oca_proxy_restart" <?php if($provider->getPid() !== null): ?>disabled<?php endif;?>><?php p($l->t('Start process')) ?></button>
			<br/>
			<small id="oca-proxy-technical-details-title" style="cursor: pointer;opacity: 0.3"><?php p($l->t('Technical details / Debugging')) ?><div class="icon-triangle-s" style="display: inline-block; padding: 5px;vertical-align: middle;cursor: pointer;"></div></small>
			<div id="oca-proxy-technical-details" class="hidden">
			<p><?php p($l->t('The following details are meant for debugging purposes. In case you encounter a service outage restarting the proxy service might help.')) ?></p>
				<?php if($provider->getPid() === null): ?>
				<?php else: ?>
					<strong><?php p($l->t('PID:')) ?></strong> <?php p($provider->getPid()) ?><br/>
					<strong><?php p($l->t('PID info:')) ?></strong><br/>
					<code><?php p($provider->getPidInfo()) ?></code><br/>
				<?php endif; ?>
			</div>
		<?php else: ?>
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
		<?php endif; ?>
	<?php endforeach; ?>
</div>

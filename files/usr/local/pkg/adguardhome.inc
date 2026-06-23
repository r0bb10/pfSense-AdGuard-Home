<?php

require_once('functions.inc');
require_once('globals.inc');
require_once('service-utils.inc');
require_once('util.inc');

const ADGUARDHOME_RCCONF = '/usr/local/etc/rc.conf.d/adguardhome';
const ADGUARD_LEGACY_RCCONF = '/usr/local/etc/rc.conf.d/adguard';

function adguardhome_settings() {
	return config_get_path('installedpackages/adguardhome/config/0', []);
}

function adguardhome_enabled() {
	$settings = adguardhome_settings();
	return (($settings['enable'] ?? '') === 'on');
}

function adguardhome_write_rc() {
	if (!is_dir(dirname(ADGUARDHOME_RCCONF))) {
		mkdir(dirname(ADGUARDHOME_RCCONF), 0755, true);
	}
	$enabled = adguardhome_enabled() ? 'YES' : 'NO';
	$rcconf = "adguardhome_enable=\"{$enabled}\"\n";
	file_put_contents(ADGUARDHOME_RCCONF, $rcconf, LOCK_EX);
	@unlink(ADGUARD_LEGACY_RCCONF);
}

function adguardhome_sync() {
	adguardhome_write_rc();
	if (adguardhome_enabled()) {
		if (is_service_running('adguardhome')) {
			restart_service('adguardhome');
		} else {
			start_service('adguardhome');
		}
	} elseif (is_service_running('adguardhome')) {
		stop_service('adguardhome');
	}
}

function adguardhome_install() {
	adguardhome_write_rc();
}

function adguardhome_deinstall() {
	if (is_service_running('adguardhome')) {
		stop_service('adguardhome');
	}
	@unlink(ADGUARDHOME_RCCONF);
	@unlink(ADGUARD_LEGACY_RCCONF);
}

?>

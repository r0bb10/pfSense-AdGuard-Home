<?php

require_once('guiconfig.inc');
require_once('service-utils.inc');
require_once('util.inc');

const ADGUARD_BIN = '/usr/local/bin/AdGuardHome';
const ADGUARD_LOG = '/var/log/adguardhome.log';
const ADGUARD_YAML = '/opt/AdGuardHome/AdGuardHome.yaml';
const ADGUARD_WORKDIR = '/opt/AdGuardHome';

function adguard_status_escape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adguard_command_output($command) {
	$output = [];
	$result = 0;
	exec($command . ' 2>&1', $output, $result);
	return trim(implode("\n", $output));
}

function adguard_default_admin_host() {
	$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '127.0.0.1';
	$host = preg_replace('/:\d+$/', '', $host);
	return ($host !== '') ? $host : '127.0.0.1';
}

function adguard_admin_url() {
	$scheme = 'http';
	$host = adguard_default_admin_host();
	$port = 3000;

	if (!is_readable(ADGUARD_YAML)) {
		return $scheme . '://' . $host . ':' . $port;
	}

	$lines = file(ADGUARD_YAML, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$in_http = false;
	$in_tls = false;

	foreach ($lines as $line) {
		if (preg_match('/^http:$/', $line)) {
			$in_http = true;
			$in_tls = false;
			continue;
		}
		if (preg_match('/^tls:$/', $line)) {
			$in_tls = true;
			$in_http = false;
			continue;
		}
		if (preg_match('/^\S/', $line)) {
			$in_http = false;
			$in_tls = false;
		}
		if ($in_http && preg_match('/^\s+address:\s*(\S+)$/', $line, $m)) {
			$parts = explode(':', $m[1]);
			$host = $parts[0];
			$port = (int)($parts[1] ?? 3000);
			if ($host === '0.0.0.0' || $host === '::' || $host === '') {
				$host = adguard_default_admin_host();
			}
		}
		if ($in_tls && preg_match('/^\s+force_https:\s*true$/', $line)) {
			$scheme = 'https';
		}
		if ($in_tls && preg_match('/^\s+port_https:\s*(\d+)$/', $line, $m)) {
			if ($scheme === 'https') {
				$port = (int)$m[1];
			}
		}
	}

	return $scheme . '://' . $host . ':' . $port;
}

function adguard_read_log($lines = 30) {
	if (!is_readable(ADGUARD_LOG)) {
		return '';
	}
	$file = file(ADGUARD_LOG, FILE_IGNORE_NEW_LINES);
	if ($file === false) {
		return '';
	}
	return implode("\n", array_slice($file, -$lines));
}

function adguard_reset_setup() {
	if (is_service_running('adguardhome')) {
		stop_service('adguardhome');
	}
	mwexec('/bin/rm -rf ' . escapeshellarg(ADGUARD_WORKDIR));
	mwexec('/usr/bin/install -d -m 0700 ' . escapeshellarg(ADGUARD_WORKDIR));
	@unlink(ADGUARD_LOG);
	start_service('adguardhome');
}

if (isset($_POST['resetadguardhome'])) {
	adguard_reset_setup();
	$savemsg = gettext('AdGuard Home setup has been reset and the service has been restarted.');
}

if (isset($_GET['ajax'])) {
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	$status = [
		'running' => is_service_running('adguardhome'),
		'version' => is_executable(ADGUARD_BIN) ? adguard_command_output(ADGUARD_BIN . ' --version') : 'binary missing',
		'admin_url' => adguard_admin_url(),
		'log' => adguard_read_log(30),
	];
	echo json_encode($status);
	exit;
}

$shortcut_section = 'adguardhome';
$pgtitle = [gettext('Status'), gettext('AdGuard Home')];
include('head.inc');
?>

<ul class="nav nav-tabs">
	<li><a href="/pkg_edit.php?xml=adguardhome.xml"><?=gettext('Settings')?></a></li>
	<li class="active"><a href="/status_adguardhome.php"><?=gettext('Status')?></a></li>
</ul>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Service')?></h2></div>
	<div class="panel-body">
		<?php if (!empty($savemsg)): ?>
			<div class="alert alert-success" role="alert"><?=adguard_status_escape($savemsg)?></div>
		<?php endif; ?>
		<table class="table table-striped table-condensed">
			<tbody>
				<tr><th><?=gettext('State')?></th><td id="adguard-state">-</td></tr>
				<tr><th><?=gettext('Version')?></th><td id="adguard-version">-</td></tr>
				<tr><th><?=gettext('Admin UI')?></th><td><a id="adguard-admin-link" href="#" target="_blank"><?=gettext('Open AdGuard Home')?></a></td></tr>
			</tbody>
		</table>
		<form method="post" onsubmit="return confirm(<?=json_encode(gettext('This will permanently delete all AdGuard Home configuration and data, then restart setup. Continue?'))?>);">
			<button type="submit" name="resetadguardhome" value="1" class="btn btn-danger">
				<?=gettext('Reset AdGuard Home Setup')?>
			</button>
		</form>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Recent Log')?></h2></div>
	<div class="panel-body">
		<pre id="adguard-log" style="max-height: 30em; overflow: auto;">-</pre>
	</div>
</div>

<script>
async function refreshAdguard() {
	const response = await fetch('/status_adguardhome.php?ajax=1', {cache: 'no-store'});
	const data = await response.json();
	document.getElementById('adguard-state').textContent = data.running ? 'Running' : 'Stopped';
	document.getElementById('adguard-version').textContent = data.version || '-';
	document.getElementById('adguard-admin-link').href = data.admin_url || '#';
	document.getElementById('adguard-log').textContent = data.log || 'No readable log output.';
}
refreshAdguard();
setInterval(refreshAdguard, 5000);
</script>

<?php include('foot.inc'); ?>

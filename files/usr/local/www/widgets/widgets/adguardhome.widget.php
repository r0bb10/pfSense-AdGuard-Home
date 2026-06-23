<?php

require_once('guiconfig.inc');
require_once('service-utils.inc');
require_once('util.inc');

if (isset($_POST['widgetkey']) || isset($_GET['widgetkey'])) {
	$requested_widgetkey = $_POST['widgetkey'] ?? $_GET['widgetkey'];
	[$widget_name, $widget_id] = array_pad(explode('-', $requested_widgetkey, 2), 2, null);
	if ($widget_name === basename(__FILE__, '.widget.php') && is_numericint($widget_id)) {
		$widgetkey = $requested_widgetkey;
	} else {
		print gettext('Invalid Widget Key');
		exit;
	}
}

if (!isset($widgetkey)) {
	print gettext('Missing Widget Key');
	exit;
}

const ADGUARD_BIN = '/usr/local/bin/AdGuardHome';
const ADGUARD_YAML = '/opt/AdGuardHome/AdGuardHome.yaml';

function adguard_widget_escape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adguard_widget_default_admin_host() {
	$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '127.0.0.1';
	$host = preg_replace('/:\d+$/', '', $host);
	return ($host !== '') ? $host : '127.0.0.1';
}

function adguard_widget_admin_url() {
	$scheme = 'http';
	$host = adguard_widget_default_admin_host();
	$port = 3000;

	if (!is_readable(ADGUARD_YAML)) {
		return $scheme . '://' . $host . ':' . $port;
	}

	$lines = file(ADGUARD_YAML, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$in_http = false;
	$in_tls = false;

	foreach ($lines as $line) {
		if (preg_match('/^http:$/', $line)) { $in_http = true; $in_tls = false; continue; }
		if (preg_match('/^tls:$/', $line)) { $in_tls = true; $in_http = false; continue; }
		if (preg_match('/^\S/', $line)) { $in_http = false; $in_tls = false; }
		if ($in_http && preg_match('/^\s+address:\s*(\S+)$/', $line, $m)) {
			$parts = explode(':', $m[1]);
			$host = $parts[0];
			$port = (int)($parts[1] ?? 3000);
			if ($host === '0.0.0.0' || $host === '::' || $host === '') {
				$host = adguard_widget_default_admin_host();
			}
		}
		if ($in_tls && preg_match('/^\s+force_https:\s*true$/', $line)) { $scheme = 'https'; }
		if ($in_tls && preg_match('/^\s+port_https:\s*(\d+)$/', $line, $m)) {
			if ($scheme === 'https') { $port = (int)$m[1]; }
		}
	}

	return $scheme . '://' . $host . ':' . $port;
}

function adguard_widget_body() {
	$running = is_service_running('adguardhome');
	$service_icon = $running ? 'fa-arrow-up text-success' : 'fa-arrow-down text-danger';
	$service_label = $running ? gettext('Running') : gettext('Stopped');
	$version = is_executable(ADGUARD_BIN) ? trim(shell_exec(ADGUARD_BIN . ' --version 2>&1')) : gettext('Missing binary');
	$html = '';
	$html .= '<tr><th>' . adguard_widget_escape(gettext('Service')) . '</th><td><i class="fa-solid ' . $service_icon . '"></i> ' . adguard_widget_escape($service_label) . '</td></tr>';
	$html .= '<tr><th>' . adguard_widget_escape(gettext('Version')) . '</th><td>' . adguard_widget_escape($version) . '</td></tr>';
	return $html;
}

if (isset($_POST['ajax'])) {
	print adguard_widget_body();
	exit;
}

?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<tbody id="<?=adguard_widget_escape($widgetkey)?>">
			<?=adguard_widget_body()?>
		</tbody>
	</table>
</div>
<div class="text-right">
	<a href="/status_adguardhome.php"><?=gettext('Full status')?></a>
	&middot;
	<a href="<?=adguard_widget_admin_url()?>" target="_blank"><?=gettext('Admin UI')?></a>
</div>

<script type="text/javascript">
events.push(function() {
	function adguardCallback(response) {
		$(<?=json_encode('#' . $widgetkey)?>).html(response);
	}

	var refreshObject = new Object();
	refreshObject.name = 'adguardhome';
	refreshObject.url = '/widgets/widgets/adguardhome.widget.php';
	refreshObject.callback = adguardCallback;
	refreshObject.parms = {
		ajax: 'ajax',
		widgetkey: <?=json_encode($widgetkey)?>
	};
	refreshObject.freq = 5;
	register_ajax(refreshObject);
});
</script>

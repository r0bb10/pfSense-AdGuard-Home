<?php

$priv_list['page-services-adguardhome'] = [];
$priv_list['page-services-adguardhome']['name'] = 'WebCfg - Services: AdGuard Home';
$priv_list['page-services-adguardhome']['descr'] = 'Allow access to AdGuard Home settings and status';
$priv_list['page-services-adguardhome']['match'] = [];
$priv_list['page-services-adguardhome']['match'][] = 'pkg_edit.php?xml=adguardhome.xml*';
$priv_list['page-services-adguardhome']['match'][] = 'status_adguardhome.php*';

?>

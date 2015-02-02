<?php

$aconfig = @parse_ini_file("/etc/asterisk/manager.conf",true);
$general = $aconfig['general'];
unset($aconfig['general']);
$user = current($aconfig);
$user['username'] = key($aconfig);

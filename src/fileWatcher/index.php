<?php

ini_set("display_errors", 1);
error_reporting(E_ALL);
set_time_limit(60);
date_default_timezone_set('UTC');

require_once 'FileWatcher.php';

$filesMonitor = new FileWatcher("../", "sqlite.db");
$filesMonitor->check();
$filesMonitor->sendNotification();

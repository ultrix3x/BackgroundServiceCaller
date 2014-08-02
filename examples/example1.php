<?php
include_once('../bgcaller.php');
BackgroundServiceCaller::AssignIni('./example.ini');
header('Content-Type: application/javascript');
echo BackgroundServiceCaller::TCPCall('coffee', file_get_contents('./example1.coffee'));
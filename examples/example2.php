<?php
include_once('../bgcaller.php');
BackgroundServiceCaller::AssignIni('./example.ini');

$id = BackgroundServiceCaller::TCPAddQueue('coffee', file_get_contents('./example1.coffee'));

header('Content-Type: application/javascript');
while(BackgroundServiceCaller::TCPCheckQueue($id) == 1) {
  echo "Service is not ready yet. Going to sleep!!!!!!!!!!!!";
  sleep(1);
}
echo BackgroundServiceCaller::TCPGetQueue($id);

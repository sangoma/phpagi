#!/usr/local/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');
  error_reporting(E_ALL);

  $agi = new AGI();
  $agi->answer();

  $cid = $agi->parse_callerid();
  $agi->text2wav("Hello, {$cid['name']}.");
  do
  {
    $agi->text2wav('Enter some numbers and then press the pound key. Press 1 1 1 followed by the pound key to quit.');
    $result = $agi->get_data('beep', 3000, 20);
    $keys = $result['result'];
    $agi->text2wav("You entered $keys");
  } while($keys != '111');
  $agi->text2wav('Goodbye');
  $agi->hangup();
?>

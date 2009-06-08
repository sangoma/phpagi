#!/usr/local/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');

  $agi = new AGI();
  $agi->answer();
  $cid = $agi->parse_callerid();
  $agi->text2wav("Hello, {$cid['name']}.  Let's enter some text.");
  $text = $agi->text_input('UPPERCASE');
  $agi->text2wav("You entered $text");
  $agi->text2wav('Goodbye');
  $agi->hangup();
?>

#!/usr/local/bin/php -q
<?php
  set_time_limit(0);
  require('phpagi.php');

  $agi = new AGI();

  $agi->answer();


  // Play the "Enter the host you wish to ping, followed by the pound sign" prompt
  // and then play the beep.   
  $agi->stream_file('ping');
  $result = $agi->get_data('beep', 3000, 20);
  $ip = str_replace('*', '.', $result['result']);

  /* Danger Will Robinson!  This does NOT properly escape the ping command!
   * Someone could subvert your system if you don't fix this! - NO WARRANTY :P */
  $execstr = "/bin/ping -c 5 -q -w 9 $ip|grep transmitted";
    
  // be polite.
  $agi->stream_file('thanks', '#');
    
  $p = popen($execstr, 'r');
  if($p == FALSE)
  {
    $agi->text2wav("Failed to ping $ip");
    $agi->conlog("Failed to ping $execstr");
  }
  else
  {
    $str = '';
    while(!feof($p))
    {
      $r = fgets($p, 1024);
      if(!$r) break;
      $str .= $r;
    }

    // a minor hack.
    $str = str_replace('ms', 'milli-seconds', $str);
    
    // have festival read back the ping results.
    $agi->text2wav("$ip - $str");
  }

  $agi->hangup();
?>

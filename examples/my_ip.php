<?php

  function my_ip(&$agi, $peer)
  {
    $ip = 'unknown';
    $asm = $agi->new_AsteriskManager();
    if($asm->connect())
    {
      $peer = $asm->command("sip show peer $peer");
      $asm->disconnect();

      if(!strpos($peer['data'], ':'))
        echo $peer['data'];
      else
      {
        $data = array();
        foreach(explode("\n", $peer['data']) as $line)
        {
          $a = strpos('z'.$line, ':') - 1;
          if($a >= 0) $data[trim(substr($line, 0, $a))] = trim(substr($line, $a + 1));
        }
      }

      if(isset($data['Addr->IP']))
      {
        $ip = explode(' ', trim($data['Addr->IP']));
        $ip = $ip[0];
      }
    }
    $agi->text2wav("Your IP address is $ip");
  }
?>

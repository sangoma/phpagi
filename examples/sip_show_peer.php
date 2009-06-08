<?php
  require_once('../phpagi-asmanager.php');

  if(!isset($_SERVER['argv'][1]))
  {
    echo "Usage:\t{$_SERVER['_']} {$_SERVER['argv'][0]} peer\n\n";
    exit;
  }

  $asm = new AGI_AsteriskManager();
  if($asm->connect())
  {
    $peer = $asm->command("sip show peer {$_SERVER['argv'][1]}");
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
      print_r($data);
    }

    $asm->disconnect();
  }
?>

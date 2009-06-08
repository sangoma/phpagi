<?php
  require_once('../phpagi-asmanager.php');

  $number = '1234';

  $asm = new AGI_AsteriskManager();
  if($asm->connect())
  {
    $call = $asm->send_request('Originate',
            array('Channel'=>"SIP/$number",
                  'Context'=>'default',
                  'Priority'=>1,
                  'Callerid'=>$number));
    $asm->disconnect();
  }
?>

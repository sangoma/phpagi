<?php
/**
  * phpagi-asmanager.php : PHP Asterisk Manager functions
  * Website: http://phpagi.sourceforge.net
  *
  * $Id: phpagi_1.php,v 1.1 2005/05/27 00:03:18 masham Exp $
  *
  * Copyright (c) 2004, 2005 Matthew Asham <matthewa@bcwireless.net>, David Eder <david@eder.us>
  * All Rights Reserved.
  *
  * This software is released under the terms of the GNU Lesser General Public License v2.1
  *  A copy of which is available from http://www.gnu.org/copyleft/lesser.html
  *
  * We would be happy to list your phpagi based application on the phpagi
  * website.  Drop me an Email if you'd like us to list your program.
  *
  * @package phpAGI
  * @version 2.0
  */


 /**
  * Written for PHP 4.3.4, should work with older PHP 4.x versions.
  * Please submit bug reports, patches, etc to http://sourceforge.net/projects/phpagi/
  * Gracias. :)
  *
  */

  if(!class_exists('AGI'))
  {
    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phpagi.php');
  }

 /**
  * Asterisk Manager class
  *
  * @link http://www.voip-info.org/wiki-Asterisk+config+manager.conf
  * @link http://www.voip-info.org/wiki-Asterisk+manager+API
  * @example examples/sip_show_peer.php Get information about a sip peer
  * @package phpAGI
  */
  class AGI_1 extends AGI
  {
   /**
    * Response structure
    *
    * array('code'=>$code, 'result'=>$result, 'data'=>$data)
    *
    * @var array
    * @access public
    */
    var $response;

   /**
    * Constructor
    *
    * @param string $config is the name of the config file to parse
    * @param array $optconfig is an array of configuration vars and vals, stuffed into $this->config['phpagi']
    */
    function AGI_1($config=false, $optconfig=false)
    {
      if(!$config) $config = NULL;
      if(!$optconfig) $optconfig = array();
      parent::AGI($config, $optconfig);
    }

   /**
    * Evaluate an AGI command
    *
    * @access private
    * @param string $command
    * @return array ('code'=>$code, 'result'=>$result, 'data'=>$data)
    */
    function evalutate($command)
    {
      $this->response = parent::evalute($command);
      return $this->response;
    }

   /**
    * Execute an AGI command
    *
    * @access private
    * @param string $str
    * @return array ('code'=>$code, 'result'=>$result, 'data'=>$data)
    */
    function agi_exec($str)
    {
      return $this->evaluate($str);
    }

   /**
    * Check for error in result structure
    *
    * @param array $retarr
    * @return boolean true on error
    */
    function agi_is_error($retarr)
    {
      // Returns TRUE if the command returned an error.

      if($retarr['code'] != AGIRES_OK)
      {
        $this->conlog("DEBUG:  Bad command?  Returned code is {$retarr['code']} result={$retarr['result']}");
        return true;
      }

      if(!isset($retarr['result']))
      {
        $this->conlog("DEBUG:  No 'result' value returned from asterisk!  Eww!");
        return true;
      }

      if($retarr['result'] == -1)
        return true;

      return false;
    }

   /**
    * Read the result from Asterisk
    *
    * @return array
    */
    function agi_readresult()
    {
      return $this->response;
    }

   /**
    * Sends $message to the Asterisk console via the 'verbose' message system.
    *
    * If the Asterisk verbosity level is $vbl or greater, send $str to the console.
    *
    * The Asterisk verbosity system works as follows. The Asterisk user gets to set the desired verbosity at startup time or later
    * using the console 'set verbose' command. Messages are displayed on the console if their verbose level is less than or equal
    * to desired verbosity set by the user. More important messages should have a low verbose level; less important messages
    * should have a high verbose level.
    *
    * @link http://www.voip-info.org/wiki-verbose
    * @param string $str
    * @param integer $vbl from 1 to 4
    */
    function agi_verbose($str, $vbl=1)
    {
      $this->verbose($str, $vbl);
    }

   /**
    * Get the response code from the last command
    *
    * @return integer
    */
    function agi_response_code()
    {
      return $this->response['code'];
    }

   /**
    * Get the result code from the last command
    *
    * @return integer
    */
    function agi_response_result()
    {
      $this->conlog("result is {$this->response['result']}");
      return $this->response['result'];
    }

   /**
    * Get the response data from the last command
    *
    * @return string
    */
    function agi_response_data()
    {
      return $this->response['data'];
    }

   /**
    * Get the response variable from the last command
    *
    * @param string $var
    * @return mixed
    */
    function agi_response_var($var)
    {
      if(!isset($this->response[$var]))
        return false;
      return $this->response[$var];
    }

   /**
    * Check for error in response
    *
    * @return boolean true on error
    */
    function agi_response_is_error()
    {
      return $this->agi_is_error($this->response);
    }

   /**
    * Log to console if debug mode
    *
    * @param array $arr to print
    * @param string $label
    * @param integer $vbl verbose level
    */
    function con_print_r($arr, $label='', $lvl=0)
    {
      if($lvl == 0 && $label != '')
        $this->conlog("debug: $label");

      $this->conlog(print_r($arr, true), $lvl);
    }


   /**
    * Plays the given file and receives DTMF data.
    *
    * This is similar to STREAM FILE, but this command can accept and return many DTMF digits,
    * while STREAM FILE returns immediately after the first DTMF digit is detected.
    *
    * Asterisk looks for the file to play in /var/lib/asterisk/sounds by default.
    *
    * If the user doesn't press any keys when the message plays, there is $timeout milliseconds
    * of silence then the command ends.
    *
    * The user has the opportunity to press a key at any time during the message or the
    * post-message silence. If the user presses a key while the message is playing, the
    * message stops playing. When the first key is pressed a timer starts counting for
    * $timeout milliseconds. Every time the user presses another key the timer is restarted.
    * The command ends when the counter goes to zero or the maximum number of digits is entered,
    * whichever happens first.
    *
    * Pressing the # key has the same effect as the timer running out: the command ends and
    * any previously keyed digits are returned. A side effect of this is that there is no
    * way to read a # key using this command.
    *
    * @link http://www.voip-info.org/wiki-get+data
    * @param integer $len number of digits to read
    * @param integer $timeout milliseconds
    * @param string $terminator character on which to quit
    * @param string $prompt file to play. Do not include file extension.
    * @return array of characters
    */
    function agi_getdtmf($len, $timeout, $terminator=false, $prompt=false)
    {
      if($prompt)
      {
        if(!is_array($prompt)) $prompt = array($prompt);

        foreach($prompt as $p)
        {
          if($p[0] == '$')
          {
            $this->text2wav(substr($p, 1));
          }
          else
          {
            $this->stream_file($p, '#');
          }				
        }
      }

      $ret = array();
      for($i = 0; $i < $len; $i++)
      {
        $res = $this->wait_for_digit($timeout);
        $this->con_print_r($res);
        if($this->agi_response_is_error())
        {
          $this->conlog('error?');
	  return false;
        }
        $ch = chr($res['result']);
        $this->conlog("got $ch");
        if($terminator && $ch == $terminator)
	  return $ret;

        $ret[$i] = $ch;
      }
      return($ret);
    }

   /**
    * Read $len characters as DTMF codes
    *
    * @param integer $len
    * @return string
    */
    function agi_dtmf2text($len)
    {
      $numbers=array('1'=>'1', '2'=>'2abc', '3'=>'3def', '4'=>'4ghi', '5'=>'5jkl', '6'=>'6mno',
                     '7'=>'7pqrs', '8'=>'8tuv', '9'=>'9wxyz', '0'=>'0');

      $last = false;
      $i = $times = 0;
      $abort = 0;

      $char = '';
      do
      {
        $res = $this->agi_getdtmf(1, 4000);
        $res = $res[0];

        if($res == false) break;

        if($last == false)
          $last = $res;
        elseif($last != $res || $res == false)
        {
          $ret[$i] = $char;
          $this->conlog("Character $i is $char");
//          $this->text2wav($char);					
          $times = 0;
          $i++;
        }

        $char = $numbers[$res][$times++];
        $this->conlog("Number $res is '$char'");

        if(strlen($numbers[$res]) == $times) $times = 0;

        $last = $res;
      } while($i < $len && !$abort);

      $str = '';
      foreach($ret as $k) $str .= $k . ' ';

      $this->text2wav($str);
      return($str);
    }	

   /**
    * Alias of PHP join function
    *
    * @param array $arr
    * @return string
    */
    function arr2str($arr)
    {
      return trim(join(' ', $arr));
    }

   /**
    * Retrieves an entry in the Asterisk database for a given family and key.
    *
    * @link http://www.voip-info.org/wiki-database+get
    * @param string $family
    * @param string $key
    * @return string
    */
    function db_get($family, $key)
    {
      $res = $this->database_get($family, $key);
      if($res['code'] != AGIRES_OK || $res['result'] == 0) return false;
      return $res['data'];
    }

   /**
    * Adds or updates an entry in the Asterisk database for a given family, key, and value.
    *
    * @param string $family
    * @param string $key
    * @param string $val
    * @return integer result code
    */
    function db_put($family, $key, $val)
    {
      $res = $this->database_put($family, $key, $val);
      return $res['code'];
    }

   /**
    * Deletes an entry in the Asterisk database for a given family and key.
    *
    * @link http://www.voip-info.org/wiki-database+del
    * @param string $family
    * @param string $key
    * @return integer result code
    */
    function db_del($family, $key)
    {
      $res = $this->database_del($family, $key);
      return $res['code'];
    }

   /**
    * Fetch the value of a variable.
    *
    * Does not work with global variables. Does not work with some variables that are generated by modules.
    *
    * @link http://www.voip-info.org/wiki-get+variable
    * @link http://www.voip-info.org/wiki-Asterisk+variables
    * @param string $var variable name
    * @return string
    */
    function get_var($var)
    {
      $res = $this->get_variable($var);
      if($res['code'] != AGIRES_OK || $res['result'] == 0) return false;
      return $res['data'];
    }

   /**
    * Sets a variable to the specified value. The variables so created can later be used by later using ${<variablename>}
    * in the dialplan.
    *
    * These variables live in the channel Asterisk creates when you pickup a phone and as such they are both local and temporary.
    * Variables created in one channel can not be accessed by another channel. When you hang up the phone, the channel is deleted
    * and any variables in that channel are deleted as well.
    *
    * @link http://www.voip-info.org/wiki-set+variable
    * @param string $var is case sensitive
    * @param string $val
    * @return integer result code
    */
    function set_var($var, $val)
    {
      $res = $this->set_variable($var, $val);
      return $res['code'];
    }

   /**
    * Hangup the current channel.
    *
    * @link http://www.voip-info.org/wiki-hangup
    * @param string $channel
    */
    function agi_hangup()
    {
      $this->hangup();
    }

   /**
    * Get the status of the specified channel.
    *
    * @link http://www.voip-info.org/wiki-channel+status
    * @param string $channel
    * @return array, ('status'=>$res['result'], 'description'=>$res['data'])
    */
    function agi_channel_status($channel)
    {
      $res = $this->channel_status($channel);
      return array('status'=>$res['result'], 'description'=>$res['data']);
    }

   /**
    * Record sound to a file until an acceptable DTMF digit is received or a specified amount of
    * time has passed. Optionally the file BEEP is played before recording begins.
    *
    * @link http://www.voip-info.org/wiki-record+file
    * @param string $file to record, without extension, often created in /var/lib/asterisk/sounds
    * @param string $format of the file. GSM and WAV are commonly used formats. MP3 is read-only and thus cannot be used.
    * @param integer $timeout is the maximum record time in milliseconds, or -1 for no timeout.
    * @param string $prompt to play
    */
    function agi_recordfile($file, $format, $timeout=5000, $prompt=FALSE)
    {
      if($prompt) $this->stream_file($prompt);
      $this->record_file($file, $format, '#', $timeout, true);
    }

   /**
    * Play the given audio file, allowing playback to be interrupted by a #. This command is similar to the GET DATA
    * command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of
    * digits before returning.
    *
    * @link http://www.voip-info.org/wiki-stream+file
    * @param string $file filename without extension, often in /var/lib/asterisk/sounds
    */
    function agi_play($file)
    {
      $this->stream_file($file, '#');
    }

   /**
    * Goto - Set context, extension and priority
    *
    * @param string $con context
    * @param string $ext extension
    * @param string $pri priority
    */
    function agi_goto($con,$ext='s',$pri=1)
    {
      $this->goto($con, $ext, $pri);
    }

   /**
    * Say the given digit string, returning early if # is received on the channel.
    *
    * @link http://www.voip-info.org/wiki-say+digits
    * @param integer $digits
    */
    function agi_saydigits($digits)
    {
      $this->say_digits($digits, '#');
    }

   /**
    * Say the given number, returning early if # is received on the channel.
    *
    * @link http://www.voip-info.org/wiki-say+number
    * @param integer $number
    */
    function agi_saynumber($number)
    {
      $this->say_number($number, '#');
    }

   /**
    * Say a given time, returning early if # is received on the channel.
    *
    * @link http://www.voip-info.org/wiki-say+time
    * @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
    */
    function agi_saytime($time='')
    {
      if($time == '') $time = time();
      $this->say_time($time, '#');
    }

   /**
    * Set Language
    *
    * @param string $language code
    */
    function agi_setlanguage($language='en')
    {
      $this->exec_setlanguage($language);
    }

   /**
    * Perform enum lookup
    *
    * @param string $telnumber
    * @param string $rDNS
    * @return array
    */
    function enum_lookup($telnumber, $rDNS='e164.org')
    {
      // returns a sorted array of enum records 

      if($telnumber[0] == '+')
        $telnumber = substr($telnumber, 1);

      for($i = 0; $i < strlen($telnumber); $i++)
        $rDNS = $telnumber[$i] . '.' . $rDNS;

      if(!isset($this->config['phpagi']['dig'])) $this->config['phpagi']['dig'] = $this->which('dig');
      $dig=trim($this->config['phpagi']['dig']);

      $execstr= $dig . " +short " . escapeshellarg($rDNS) . " NAPTR";
      $lines = trim(`$execstr`);

      $lines = explode("\n", $lines);
      $arr = array();
      foreach($lines as $line)
      {
        $line = trim($line);
        $line = str_replace("\t", ' ', $line);
        while(strstr($line, '  '))
          $line = str_replace('  ', ' ', $line);
        $line = str_replace("\"", '', $line);
        $line = str_replace("\'", '', $line);
        $line = str_replace(' ', '|', $line);
        $bits = explode('|', $line);
        $bit = explode('!', stripslashes($bits[4]));
        $URI = @ereg_replace($bit[1], $bit[2], '+' . $telnumber);
        if($URI[3] == ':') $URI[3] = '/';
        if($URI[4] == ':') $URI[4] = '/';
        $arr[] = array('order'=>$bits[0], 'prio'=>$bits[1], 'tech'=>$bits[3], 'URI'=>$URI);
      }

      foreach($arr as $key=>$row)
      {
        $order[$key] = $row['order'];
        $prio[$key] = $row['prio'];
      }

      array_multisort($order, SORT_ASC, $prio, SORT_ASC, $arr);
      return($arr);
    }

   /**
    * Perform enum txt lookup
    *
    * @param string $telnumber
    * @param string $rDNS
    * @return string
    */
    function enum_txtlookup($telnumber, $rDNS='e164.org')
    {
      // returns the contents of a TXT record associated with an ENUM dns record.
      // ala reverse caller ID lookup.
      if($telnumber[0] == '+')
        $telnumber = substr($telnumber, 1);

      for($i = 0; $i < strlen($telnumber); $i++)
        $rDNS = $telnumber[$i] . '.' . $rDNS;

      if(!isset($this->config['phpagi']['dig'])) $this->config['phpagi']['dig'] = $this->which('dig');
      $dig=trim($this->config['phpagi']['dig']);

      $execstr = $dig . ' +short ' . escapeshellarg($rDNS) . ' TXT';
      $lines = trim(`$execstr`);

      $lines = explode("\n", $lines);
      foreach($lines as $line)
      {
        $line = str_replace("\t", ' ', trim($line));
        while(strstr($line, '  ')) $line = str_replace('  ', ' ', $line);
        $line = str_replace("\"", '', $line);
        $line = str_replace("\'", '', $line);
        $ret .= $line;
      }
      $ret = trim($ret);
      if($ret == '') return false;
      return $ret;
    }

   /**
    * Send the given text to the connected channel.
    *
    * Most channels do not support transmission of text.
    *
    * @link http://www.voip-info.org/wiki-send+text
    * @param $text
    * @return boolean true on success
    */
    function send_text($txt)
    {
      $res = parent::send_text($txt);
      if($res['code'] != AGIRES_OK || $res['result'] == -1) return false;
      return true;
    }

   /**
    * Send the specified image on a channel.
    *
    * Most channels do not support the transmission of images.
    *
    * @link http://www.voip-info.org/wiki-send+image
    * @param string $image without extension, often in /var/lib/asterisk/images
    * @return boolean true on success
    */
    function send_image($image)
    {
      $res = parent::send_image($image);
      if($res['code'] != AGIRES_OK || $res['result'] == -1) return false;
      return true;
    }	
  }
?>

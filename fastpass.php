<?php
/**
 * phpagi-asmanager.php : PHP Asterisk Manager functions
 * Website: http://phpagi.sourceforge.net
 *
 * Copyright (c) 2004 - 2010 Matthew Asham <matthew@ochrelabs.com>, David Eder <david@eder.us>
 * Copyright (c) 2005 - 2015 Schmooze Com, Inc
 * All Rights Reserved.
 *
 * This software is released under the terms of the GNU Public License v2
 * A copy of which is available from http://www.fsf.org/licenses/gpl.txt
 *
 * @package phpAGI
 */
namespace Asterisk\AMI;
if(!class_exists('AGI')) {
	require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phpagi.php');
}


/**
 * AGI class
 *
 * @package phpAGI
 * @link http://www.voip-info.org/wiki-Asterisk+agi
 * @example examples/dtmf.php Get DTMF tones from the user and say the digits
 * @example examples/input.php Get text input from the user and say it back
 * @example examples/ping.php Ping an IP address
 */
class fastpass extends AGI {
	/**
	 * Say the given digit string, returning early if any of the given DTMF escape digits are received on the channel.
	 * Return early if $buffer is adequate for request.
	 *
	 * @link http://www.voip-info.org/wiki-say+digits
	 * @param string $buffer
	 * @param integer $digits
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function fastpass_say_digits(&$buffer, $digits, $escape_digits='') {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1}))
			$proceed = true;
		}
		if($buffer == '' || $proceed) {
			$res = $this->say_digits($digits, $escape_digits);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}));
	}

	/**
	 * Say the given number, returning early if any of the given DTMF escape digits are received on the channel.
	 * Return early if $buffer is adequate for request.
	 *
	 * @link http://www.voip-info.org/wiki-say+number
	 * @param string $buffer
	 * @param integer $number
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function fastpass_say_number(&$buffer, $number, $escape_digits='') {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1})) {
				$proceed = true;
			}
		}
		if($buffer == '' || $proceed) {
			$res = $this->say_number($number, $escape_digits);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}));
	}

	/**
	 * Say the given character string, returning early if any of the given DTMF escape digits are received on the channel.
	 * Return early if $buffer is adequate for request.
	 *
	 * @link http://www.voip-info.org/wiki-say+phonetic
	 * @param string $buffer
	 * @param string $text
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function fastpass_say_phonetic(&$buffer, $text, $escape_digits='') {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1})) {
				$proceed = true;
			}
		}
		if($buffer == '' || $proceed) {
			$res = $this->say_phonetic($text, $escape_digits);
			if($res['code'] == AGIRES_OK && $res['result'] > 0)
			$buffer .= chr($res['result']);
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}));
	}

	/**
	* Say a given time, returning early if any of the given DTMF escape digits are received on the channel.
	* Return early if $buffer is adequate for request.
	*
	* @link http://www.voip-info.org/wiki-say+time
	* @param string $buffer
	* @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
	* @param string $escape_digits
	* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	*/
	function fastpass_say_time(&$buffer, $time=NULL, $escape_digits='') {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1})) {
				$proceed = true;
			}
		}
		if($buffer == '' || $proceed) {
			$res = $this->say_time($time, $escape_digits);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}));
	}

	/**
	* Play the given audio file, allowing playback to be interrupted by a DTMF digit. This command is similar to the GET DATA
	* command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of
	* digits before returning.
	* Return early if $buffer is adequate for request.
	*
	* @link http://www.voip-info.org/wiki-stream+file
	* @param string $buffer
	* @param string $filename without extension, often in /var/lib/asterisk/sounds
	* @param string $escape_digits
	* @param integer $offset
	* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	*/
	function fastpass_stream_file(&$buffer, $filename, $escape_digits='', $offset=0) {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1})) {
				$proceed = true;
			}
		}
		if($buffer == '' || $proceed) {
			$res = $this->stream_file($filename, $escape_digits, $offset);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}), 'endpos'=>0);
	}

	/**
	* Use festival to read text.
	* Return early if $buffer is adequate for request.
	*
	* @link http://www.cstr.ed.ac.uk/projects/festival/
	* @param string $buffer
	* @param string $text
	* @param string $escape_digits
	* @param integer $frequency
	* @return array, see evaluate for return information.
	*/
	function fastpass_text2wav(&$buffer, $text, $escape_digits='', $frequency=8000) {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1})) {
				$proceed = true;
			}
		}
		if($buffer == '' || $proceed) {
			$res = $this->text2wav($text, $escape_digits, $frequency);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}), 'endpos'=>0);
	}

	/**
	* Use Cepstral Swift to read text.
	* Return early if $buffer is adequate for request.
	*
	* @link http://www.cepstral.com/
	* @param string $buffer
	* @param string $text
	* @param string $escape_digits
	* @param integer $frequency
	* @return array, see evaluate for return information.
	*/
	function fastpass_swift(&$buffer, $text, $escape_digits='', $frequency=8000, $voice=NULL) {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1}))
			$proceed = true;
		}
		if($buffer == '' || $proceed) {
			$res = $this->swift($text, $escape_digits, $frequency, $voice);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}), 'endpos'=>0);
	}

	/**
	* Say Puncutation in a string.
	* Return early if $buffer is adequate for request.
	*
	* @param string $buffer
	* @param string $text
	* @param string $escape_digits
	* @param integer $frequency
	* @return array, see evaluate for return information.
	*/
	function fastpass_say_punctuation(&$buffer, $text, $escape_digits='', $frequency=8000) {
		$proceed = false;
		if($escape_digits != '' && $buffer != '') {
			if(!strpos(chr(255) . $escape_digits, $buffer{strlen($buffer)-1}))
			$proceed = true;
		}
		if($buffer == '' || $proceed) {
			$res = $this->say_punctuation($text, $escape_digits, $frequency);
			if($res['code'] == AGIRES_OK && $res['result'] > 0) {
				$buffer .= chr($res['result']);
			}
			return $res;
		}
		return array('code'=>AGIRES_OK, 'result'=>ord($buffer{strlen($buffer)-1}));
	}

	/**
	* Plays the given file and receives DTMF data.
	* Return early if $buffer is adequate for request.
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
	* If you don't specify a time out then a default timeout of 2000 is used following a pressed
	* digit. If no digits are pressed then 6 seconds of silence follow the message.
	*
	* If you don't specify $max_digits then the user can enter as many digits as they want.
	*
	* Pressing the # key has the same effect as the timer running out: the command ends and
	* any previously keyed digits are returned. A side effect of this is that there is no
	* way to read a # key using this command.
	*
	* @link http://www.voip-info.org/wiki-get+data
	* @param string $buffer
	* @param string $filename file to play. Do not include file extension.
	* @param integer $timeout milliseconds
	* @param integer $max_digits
	* @return array, see evaluate for return information. ['result'] holds the digits and ['data'] holds the timeout if present.
	*
	* This differs from other commands with return DTMF as numbers representing ASCII characters.
	*/
	function fastpass_get_data(&$buffer, $filename, $timeout=NULL, $max_digits=NULL) {
		if(is_null($max_digits) || strlen($buffer) < $max_digits) {
			if($buffer == '') {
				$res = $this->get_data($filename, $timeout, $max_digits);
				if($res['code'] == AGIRES_OK)
				$buffer .= $res['result'];
				return $res;
			} else {
				while(is_null($max_digits) || strlen($buffer) < $max_digits) {
					$res = $this->wait_for_digit();
					if($res['code'] != AGIRES_OK) {
						return $res;
					}
					if($res['result'] == ord('#')) {
						break;
					}
					$buffer .= chr($res['result']);
				}
			}
		}
		return array('code'=>AGIRES_OK, 'result'=>$buffer);
	}
}

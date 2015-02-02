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
class speech extends AGI {

	/**
	 * Menu.
	 *
	 * This function presents the user with a menu and reads the response
	 *
	 * @param array $choices has the following structure:
	 *   array('1'=>'*Press 1 for this', // festival reads if prompt starts with *
	 *         '2'=>'some-gsm-without-extension',
	 *         '*'=>'*Press star for help');
	 * @return mixed key pressed on sucess, -1 on failure
	 */
	function menu($choices, $timeout=2000) {
		$keys = join('', array_keys($choices));
		$choice = NULL;
		while(is_null($choice)) {
			foreach($choices as $prompt) {
				if($prompt{0} == '*') {
					$ret = $this->text2wav(substr($prompt, 1), $keys);
				} else {
					$ret = $this->stream_file($prompt, $keys);
				}

				if($ret['code'] != AGIRES_OK || $ret['result'] == -1) {
					$choice = -1;
					break;
				}

				if($ret['result'] != 0) {
					$choice = chr($ret['result']);
					break;
				}
			}

			if(is_null($choice)) {
				$ret = $this->get_data('beep', $timeout, 1);
				if($ret['code'] != AGIRES_OK || $ret['result'] == -1) {
					$choice = -1;
				} elseif($ret['result'] != '' && strpos(' '.$keys, $ret['result'])) {
					$choice = $ret['result'];
				}
			}
		}
		return $choice;
	}

	/**
	 * Use festival to read text.
	 *
	 * @example examples/dtmf.php Get DTMF tones from the user and say the digits
	 * @example examples/input.php Get text input from the user and say it back
	 * @example examples/ping.php Ping an IP address
	 *
	 * @link http://www.cstr.ed.ac.uk/projects/festival/
	 * @param string $text
	 * @param string $escape_digits
	 * @param integer $frequency
	 * @return array, see evaluate for return information.
	 */
	function text2wav($text, $escape_digits='', $frequency=8000) {
		// festival TTS config
		if(!isset($this->config['festival']['text2wave'])) $this->config['festival']['text2wave'] = $this->which('text2wave');

		$text = trim($text);
		if($text == '') {
			return true;
		}

		$hash = md5($text);
		$fname = $this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR;
		$fname .= 'text2wav_' . $hash;

		// create wave file
		if(!file_exists("$fname.wav")) {
			// write text file
			if(!file_exists("$fname.txt")) {
				$fp = fopen("$fname.txt", 'w');
				fputs($fp, $text);
				fclose($fp);
			}

			shell_exec("{$this->config['festival']['text2wave']} -F $frequency -o $fname.wav $fname.txt");
		} else {
			touch("$fname.txt");
			touch("$fname.wav");
		}

		// stream it
		$ret = $this->stream_file($fname, $escape_digits);

		// clean up old files
		$delete = time() - 2592000; // 1 month
		foreach(glob($this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . 'text2wav_*') as $file)
		if(filemtime($file) < $delete) {
			unlink($file);
		}

		return $ret;
	}

	/**
	* Use Cepstral Swift to read text.
	*
	* @link http://www.cepstral.com/
	* @param string $text
	* @param string $escape_digits
	* @param integer $frequency
	* @return array, see evaluate for return information.
	*/
	function swift($text, $escape_digits='', $frequency=8000, $voice=NULL) {
		// swift TTS config
		if(!isset($this->config['cepstral']['swift'])) $this->config['cepstral']['swift'] = $this->which('swift');

		if(!is_null($voice)) {
			$voice = "-n $voice";
		} elseif(isset($this->config['cepstral']['voice'])) {
			$voice = "-n {$this->config['cepstral']['voice']}";
		}

		$text = trim($text);
		if($text == '') {
			return true;
		}

		$hash = md5($text);
		$fname = $this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR;
		$fname .= 'swift_' . $hash;

		// create wave file
		if(!file_exists("$fname.wav")) {
			// write text file
			if(!file_exists("$fname.txt")) {
				$fp = fopen("$fname.txt", 'w');
				fputs($fp, $text);
				fclose($fp);
			}

			shell_exec("{$this->config['cepstral']['swift']} -p audio/channels=1,audio/sampling-rate=$frequency $voice -o $fname.wav -f $fname.txt");
		}

		// stream it
		$ret = $this->stream_file($fname, $escape_digits);

		// clean up old files
		$delete = time() - 2592000; // 1 month
		foreach(glob($this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . 'swift_*') as $file)
		if(filemtime($file) < $delete) {
			unlink($file);
		}

		return $ret;
	}

	/**
	 * Text Input.
	 *
	 * Based on ideas found at http://www.voip-info.org/wiki-Asterisk+cmd+DTMFToText
	 *
	 * Example:
	 *              UC   H     LC   i      ,     SP   h     o      w    SP   a    r      e     SP   y      o      u     ?
	 *   $string = '*8'.'44*'.'*5'.'444*'.'00*'.'0*'.'44*'.'666*'.'9*'.'0*'.'2*'.'777*'.'33*'.'0*'.'999*'.'666*'.'88*'.'0000*';
	 *
	 * @link http://www.voip-info.org/wiki-Asterisk+cmd+DTMFToText
	 * @example examples/input.php Get text input from the user and say it back
	 *
	 * @return string
	 */
	function text_input($mode='NUMERIC') {
		$alpha = array(
			'k0'=>' ', 'k00'=>',', 'k000'=>'.', 'k0000'=>'?', 'k00000'=>'0',
			'k1'=>'!', 'k11'=>':', 'k111'=>';', 'k1111'=>'#', 'k11111'=>'1',
			'k2'=>'A', 'k22'=>'B', 'k222'=>'C', 'k2222'=>'2',
			'k3'=>'D', 'k33'=>'E', 'k333'=>'F', 'k3333'=>'3',
			'k4'=>'G', 'k44'=>'H', 'k444'=>'I', 'k4444'=>'4',
			'k5'=>'J', 'k55'=>'K', 'k555'=>'L', 'k5555'=>'5',
			'k6'=>'M', 'k66'=>'N', 'k666'=>'O', 'k6666'=>'6',
			'k7'=>'P', 'k77'=>'Q', 'k777'=>'R', 'k7777'=>'S', 'k77777'=>'7',
			'k8'=>'T', 'k88'=>'U', 'k888'=>'V', 'k8888'=>'8',
			'k9'=>'W', 'k99'=>'X', 'k999'=>'Y', 'k9999'=>'Z', 'k99999'=>'9'
		);
		$symbol = array(
			'k0'=>'=',
			'k1'=>'<', 'k11'=>'(', 'k111'=>'[', 'k1111'=>'{', 'k11111'=>'1',
			'k2'=>'@', 'k22'=>'$', 'k222'=>'&', 'k2222'=>'%', 'k22222'=>'2',
			'k3'=>'>', 'k33'=>')', 'k333'=>']', 'k3333'=>'}', 'k33333'=>'3',
			'k4'=>'+', 'k44'=>'-', 'k444'=>'*', 'k4444'=>'/', 'k44444'=>'4',
			'k5'=>"'", 'k55'=>'`', 'k555'=>'5',
			'k6'=>'"', 'k66'=>'6',
			'k7'=>'^', 'k77'=>'7',
			'k8'=>"\\",'k88'=>'|', 'k888'=>'8',
			'k9'=>'_', 'k99'=>'~', 'k999'=>'9'
		);
		$text = '';
		do {
			$command = false;
			$result = $this->get_data('beep');
			foreach(explode('*', $result['result']) as $code) {
				if($command) {
					switch($code{0}) {
						case '2': $text = substr($text, 0, strlen($text) - 1); break; // backspace
						case '5': $mode = 'LOWERCASE'; break;
						case '6': $mode = 'NUMERIC'; break;
						case '7': $mode = 'SYMBOL'; break;
						case '8': $mode = 'UPPERCASE'; break;
						case '9': $text = explode(' ', $text); unset($text[count($text)-1]); $text = join(' ', $text); break; // backspace a word
					}
					$code = substr($code, 1);
					$command = false;
				}
				if($code == '')
				$command = true;
				elseif($mode == 'NUMERIC')
				$text .= $code;
				elseif($mode == 'UPPERCASE' && isset($alpha['k'.$code]))
				$text .= $alpha['k'.$code];
				elseif($mode == 'LOWERCASE' && isset($alpha['k'.$code]))
				$text .= strtolower($alpha['k'.$code]);
				elseif($mode == 'SYMBOL' && isset($symbol['k'.$code]))
				$text .= $symbol['k'.$code];
			}
			$this->say_punctuation($text);
		} while(substr($result['result'], -2) == '**');
		return $text;
	}

	/**
	 * Say Puncutation in a string.
	 *
	 * @param string $text
	 * @param string $escape_digits
	 * @param integer $frequency
	 * @return array, see evaluate for return information.
	 */
	function say_punctuation($text, $escape_digits='', $frequency=8000) {
		for($i = 0; $i < strlen($text); $i++) {
			switch($text{$i}) {
				case ' ': $ret .= 'SPACE ';
				case ',': $ret .= 'COMMA '; break;
				case '.': $ret .= 'PERIOD '; break;
				case '?': $ret .= 'QUESTION MARK '; break;
				case '!': $ret .= 'EXPLANATION POINT '; break;
				case ':': $ret .= 'COLON '; break;
				case ';': $ret .= 'SEMICOLON '; break;
				case '#': $ret .= 'POUND '; break;
				case '=': $ret .= 'EQUALS '; break;
				case '<': $ret .= 'LESS THAN '; break;
				case '(': $ret .= 'LEFT PARENTHESIS '; break;
				case '[': $ret .= 'LEFT BRACKET '; break;
				case '{': $ret .= 'LEFT BRACE '; break;
				case '@': $ret .= 'AT '; break;
				case '$': $ret .= 'DOLLAR SIGN '; break;
				case '&': $ret .= 'AMPERSAND '; break;
				case '%': $ret .= 'PERCENT '; break;
				case '>': $ret .= 'GREATER THAN '; break;
				case ')': $ret .= 'RIGHT PARENTHESIS '; break;
				case ']': $ret .= 'RIGHT BRACKET '; break;
				case '}': $ret .= 'RIGHT BRACE '; break;
				case '+': $ret .= 'PLUS '; break;
				case '-': $ret .= 'MINUS '; break;
				case '*': $ret .= 'ASTERISK '; break;
				case '/': $ret .= 'SLASH '; break;
				case "'": $ret .= 'SINGLE QUOTE '; break;
				case '`': $ret .= 'BACK TICK '; break;
				case '"': $ret .= 'QUOTE '; break;
				case '^': $ret .= 'CAROT '; break;
				case "\\": $ret .= 'BACK SLASH '; break;
				case '|': $ret .= 'BAR '; break;
				case '_': $ret .= 'UNDERSCORE '; break;
				case '~': $ret .= 'TILDE '; break;
				default: $ret .= $text{$i} . ' '; break;
			}
		}
		return $this->text2wav($ret, $escape_digits, $frequency);
	}

	/**
	 * Find an execuable in the path.
	 *
	 * @param string $cmd command to find
	 * @param string $checkpath path to check
	 * @return string the path to the command
	 */
	private function which($cmd, $checkpath=NULL) {
		global $_ENV;
		$chpath = is_null($checkpath) ? $_ENV['PATH'] : $checkpath;

		foreach(explode(PATH_SEPERATOR, $chpath) as $path)
		if(!function_exists('is_executable') || is_executable($path . DIRECTORY_SEPERATOR . $cmd))
		return $path . DIRECTORY_SEPERATOR . $cmd;

		if(is_null($checkpath))
		{
			if(substr(strtoupper(PHP_OS, 0, 3)) != 'WIN')
			return $this->which($cmd, '/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:'.
			'/usr/X11R6/bin:/usr/local/apache/bin:/usr/local/mysql/bin');
		}
		return false;
	}
}

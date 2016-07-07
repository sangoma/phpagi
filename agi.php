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
error_reporting(0);
@ini_set('display_errors', 0);

define('DEFAULT_PHPAGI_CONFIG', '/etc/asterisk/phpagi.conf');

define('AST_DIGIT_ANY', '0123456789#*');

define('AGIRES_OK', 200);

define('AST_STATE_DOWN', 0);
define('AST_STATE_RESERVED', 1);
define('AST_STATE_OFFHOOK', 2);
define('AST_STATE_DIALING', 3);
define('AST_STATE_RING', 4);
define('AST_STATE_RINGING', 5);
define('AST_STATE_UP', 6);
define('AST_STATE_BUSY', 7);
define('AST_STATE_DIALING_OFFHOOK', 8);
define('AST_STATE_PRERING', 9);
define('AST_STATE_MUTE', 10);

define('AUDIO_FILENO', 3); // STDERR_FILENO + 1

/**
 * AGI class
 *
 * @package phpAGI
 * @link http://www.voip-info.org/wiki-Asterisk+agi
 * @example examples/dtmf.php Get DTMF tones from the user and say the digits
 * @example examples/input.php Get text input from the user and say it back
 * @example examples/ping.php Ping an IP address
 */

class AGI {
	/**
	 * Request variables read in on initialization.
	 *
	 * Often contains any/all of the following:
	 *   agi_request - name of agi script
	 *   agi_channel - current channel
	 *   agi_language - current language
	 *   agi_type - channel type (SIP, ZAP, IAX, ...)
	 *   agi_uniqueid - unique id based on unix time
	 *   agi_callerid - callerID string
	 *   agi_dnid - dialed number id
	 *   agi_rdnis - referring DNIS number
	 *   agi_context - current context
	 *   agi_extension - extension dialed
	 *   agi_priority - current priority
	 *   agi_enhanced - value is 1.0 if started as an EAGI script
	 *   agi_accountcode - set by SetAccount in the dialplan
	 *   agi_network - value is yes if this is a fastagi
	 *   agi_network_script - name of the script to execute
	 *
	 * NOTE: program arguments are still in $_SERVER['argv'].
	 *
	 * @var array
	 */
	public $request;

	/**
	 * Config variables
	 *
	 * @var array
	 */
	public $config;

	/**
	 * Input Stream
	 */
	private $in = NULL;

	/**
	 * Output Stream
	 */
	private $out = NULL;

	/**
	 * FastAGI socket
	 */
	private $socket = NULL;

	/**
	 * Audio Stream
	 */
	public $audio = NULL;

	/**
	 * Constructor
	 *
	 * @param string $config is the name of the config file to parse
	 * @param array $optconfig is an array of configuration vars and vals, stuffed into $this->config['phpagi']
	 * @param object $socket The Connection socket.
	 */
	public function __construct($config=NULL, $optconfig=array(), $socket=NULL) {
		// load config
		if(!is_null($config) && file_exists($config)) {
			$this->config = parse_ini_file($config, true);
		} elseif(file_exists(DEFAULT_PHPAGI_CONFIG)) {
			$this->config = parse_ini_file(DEFAULT_PHPAGI_CONFIG, true);
		}

		// If optconfig is specified, stuff vals and vars into 'phpagi' config array.
		foreach($optconfig as $var=>$val) {
			$this->config['phpagi'][$var] = $val;
		}

		// add default values to config for uninitialized values
		if(!isset($this->config['phpagi']['debug'])) $this->config['phpagi']['debug'] = false;
		if(!isset($this->config['phpagi']['admin'])) $this->config['phpagi']['admin'] = NULL;
		$temp = sys_get_temp_dir();
		if(!isset($this->config['phpagi']['tempdir'])) $this->config['phpagi']['tempdir'] = (!empty($temp) ? $temp : '/tmp');

		ob_implicit_flush(true);

		// open input & output
		if(is_null($socket)) {
			$this->in  = defined('STDIN')  ? STDIN  : fopen('php://stdin',  'r');
			$this->out = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
		} else {
			$this->socket = $socket;
		}

		// make sure temp folder exists
		if(!file_exists($this->config['phpagi']['tempdir'])) {
			mkdir($this->config['phpagi']['tempdir'], 0755, true);
		}

		// read the request
		$str = is_null($this->socket) ? fgets($this->in) : socket_read($this->socket, 4096, PHP_NORMAL_READ);
		while($str != "\n") {
			$this->request[substr($str, 0, strpos($str, ':'))] = trim(substr($str, strpos($str, ':') + 1));
			$str = is_null($this->socket) ? fgets($this->in) : socket_read($this->socket, 4096, PHP_NORMAL_READ);
		}

		// open audio if eagi detected
		if($this->request['agi_enhanced'] == '1.0') {
			if(file_exists('/proc/' . getmypid() . '/fd/3')) {
				// this should work on linux
				$this->audio = fopen('/proc/' . getmypid() . '/fd/3', 'r');
			} elseif(file_exists('/dev/fd/3')) {
				// this should work on BSD. may need to mount fdescfs if this fails
				$this->audio = fopen('/dev/fd/3', 'r');
			} else {
				$this->conlog('Unable to open audio stream');
			}

			if($this->audio) stream_set_blocking($this->audio, 0);
		}
	}

	// *********************************************************************************************************
	// **                       COMMANDS                                                                      **
	// *********************************************************************************************************

	/**
	 * Answer channel if not already in answer state.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_answer
	 *
	 * @return array, see evaluate for return information.  ['result'] is 0 on success, -1 on failure.
	 */
	public function answer() {
		return $this->evaluate('ANSWER');
	}

	/**
	 * Get the status of the specified channel. If no channel name is specified, return the status of the current channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_channel+status
	 * @param string $channel
	 * @return array, see evaluate for return information. ['data'] contains description.
	*/
	public function channel_status($channel='') {
		$ret = $this->evaluate("CHANNEL STATUS $channel");
		switch($ret['result']) {
			case -1: $ret['data'] = trim("There is no channel that matches $channel"); break;
			case AST_STATE_DOWN: $ret['data'] = 'Channel is down and available'; break;
			case AST_STATE_RESERVED: $ret['data'] = 'Channel is down, but reserved'; break;
			case AST_STATE_OFFHOOK: $ret['data'] = 'Channel is off hook'; break;
			case AST_STATE_DIALING: $ret['data'] = 'Digits (or equivalent) have been dialed'; break;
			case AST_STATE_RING: $ret['data'] = 'Line is ringing'; break;
			case AST_STATE_RINGING: $ret['data'] = 'Remote end is ringing'; break;
			case AST_STATE_UP: $ret['data'] = 'Line is up'; break;
			case AST_STATE_BUSY: $ret['data'] = 'Line is busy'; break;
			case AST_STATE_DIALING_OFFHOOK: $ret['data'] = 'Digits (or equivalent) have been dialed while offhook'; break;
			case AST_STATE_PRERING: $ret['data'] = 'Channel has detected an incoming call and is waiting for ring'; break;
			case AST_STATE_MUTE: $ret['data'] = 'Do not transmit voice data'; break;
			default: $ret['data'] = "Unknown ({$ret['result']})"; break;
		}
		return $ret;
	}

	/**
	 * Deletes an entry in the Asterisk database for a given family and key.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_database+del
	 * @param string $family
	 * @param string $key
	 * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
	 */
	public function database_del($family, $key) {
		return $this->evaluate("DATABASE DEL \"$family\" \"$key\"");
	}

	/**
	 * Deletes a family or specific keytree within a family in the Asterisk database.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_database+deltree
	 * @param string $family
	 * @param string $keytree
	 * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
	 */
	public function database_deltree($family, $keytree='') {
		$cmd = "DATABASE DELTREE \"$family\"";
		if($keytree != '') $cmd .= " \"$keytree\"";
		return $this->evaluate($cmd);
	}

	/**
	 * Retrieves an entry in the Asterisk database for a given family and key.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_database+get
	 * @param string $family
	 * @param string $key
	 * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 failure. ['data'] holds the value
	 */
	public function database_get($family, $key) {
		return $this->evaluate("DATABASE GET \"$family\" \"$key\"");
	}

	/**
	 * Adds or updates an entry in the Asterisk database for a given family, key, and value.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_database+put
	 * @param string $family
	 * @param string $key
	 * @param string $value
	 * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
	 */
	public function database_put($family, $key, $value) {
		$value = str_replace("\n", '\n', addslashes($value));
		return $this->evaluate("DATABASE PUT \"$family\" \"$key\" \"$value\"");
	}

	/**
	 * Executes the specified Asterisk application with given options.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_exec
	 * @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
	 * @param string $application
	 * @param mixed $options
	 * @return array, see evaluate for return information. ['result'] is whatever the application returns, or -2 on failure to find application
	 */
	public function exec($application, $options) {
		if(is_array($options)) $options = join(',', $options);
		return $this->evaluate("EXEC $application $options");
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
	 * If you don't specify a time out then a default timeout of 2000 is used following a pressed
	 * digit. If no digits are pressed then 6 seconds of silence follow the message.
	 *
	 * If you don't specify $max_digits then the user can enter as many digits as they want.
	 *
	 * Pressing the # key has the same effect as the timer running out: the command ends and
	 * any previously keyed digits are returned. A side effect of this is that there is no
	 * way to read a # key using this command.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_get+data
	 * @param string $filename file to play. Do not include file extension.
	 * @param integer $timeout milliseconds
	 * @param integer $max_digits
	 * @return array, see evaluate for return information. ['result'] holds the digits and ['data'] holds the timeout if present.
	 *
	 * This differs from other commands with return DTMF as numbers representing ASCII characters.
	 */
	public function get_data($filename, $timeout=NULL, $max_digits=NULL) {
		return $this->evaluate(rtrim("GET DATA $filename $timeout $max_digits"));
	}

	/**
	 * Fetch the value of a variable.
	 *
	 * Does not work with global variables. Does not work with some variables that are generated by modules.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_get+variable
	 * @param string $variable name
	 * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value.
	 */
	public function get_variable($variable) {
		return $this->evaluate("GET VARIABLE $variable");
	}

	/**
	 * https://wiki.asterisk.org/wiki/display/AST/AGICommand_get+full+variable
	 * @param string $variable name
	 * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value.
	 */
	function get_full_variable($variable) {
		return $this->evaluate("GET FULL VARIABLE $variable");
	}

	/**
	 * Hangup the specified channel. If no channel name is given, hang up the current channel.
	 *
	 * With power comes responsibility. Hanging up channels other than your own isn't something
	 * that is done routinely. If you are not sure why you are doing so, then don't.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_hangup
	 * @param string $channel
	 * @return array, see evaluate for return information. ['result'] is 1 on success, -1 on failure.
	 */
	public function hangup($channel='') {
		return $this->evaluate("HANGUP $channel");
	}

	/**
	 * Does nothing.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_noop
	 * @return array, see evaluate for return information.
	 */
	public function noop() {
		return $this->evaluate('NOOP');
	}

	/**
	 * Receive a character of text from a connected channel. Waits up to $timeout milliseconds for
	 * a character to arrive, or infinitely if $timeout is zero.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_receive+char
	 * @param integer $timeout milliseconds
	 * @return array, see evaluate for return information. ['result'] is 0 on timeout or not supported, -1 on failure. Otherwise
	 * it is the decimal value of the DTMF tone. Use chr() to convert to ASCII.
	 */
	public function receive_char($timeout=-1) {
		return $this->evaluate("RECEIVE CHAR $timeout");
	}

	/**
	 * Record sound to a file until an acceptable DTMF digit is received or a specified amount of
	 * time has passed. Optionally the file BEEP is played before recording begins.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_record+file
	 * @param string $file to record, without extension, often created in /var/lib/asterisk/sounds
	 * @param string $format of the file. GSM and WAV are commonly used formats. MP3 is read-only and thus cannot be used.
	 * @param string $escape_digits
	 * @param integer $timeout is the maximum record time in milliseconds, or -1 for no timeout.
	 * @param integer $offset to seek to without exceeding the end of the file.
	 * @param boolean $beep
	 * @param integer $silence number of seconds of silence allowed before the function returns despite the
	 * lack of dtmf digits or reaching timeout.
	 * @return array, see evaluate for return information. ['result'] is -1 on error, 0 on hangup, otherwise a decimal value of the
	 * DTMF tone. Use chr() to convert to ASCII.
	 */
	public function record_file($file, $format, $escape_digits='', $timeout=-1, $offset=NULL, $beep=false, $silence=NULL) {
		$cmd = trim("RECORD FILE $file $format \"$escape_digits\" $timeout $offset");
		if($beep) $cmd .= ' BEEP';
		if(!is_null($silence)) $cmd .= " s=$silence";
		return $this->evaluate($cmd);
	}

	/**
	 * Say the given digit string, returning early if any of the given DTMF escape digits are received on the channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_say+digits
	 * @param integer $digits
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function say_digits($digits, $escape_digits='') {
		return $this->evaluate("SAY DIGITS $digits \"$escape_digits\"");
	}

	/**
	 * Say the given number, returning early if any of the given DTMF escape digits are received on the channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_say+number
	 * @param integer $number
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function say_number($number, $escape_digits='') {
		return $this->evaluate("SAY NUMBER $number \"$escape_digits\"");
	}

	/**
	 * Say the given character string, returning early if any of the given DTMF escape digits are received on the channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_say+phonetic
	 * @param string $text
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function say_phonetic($text, $escape_digits='') {
		return $this->evaluate("SAY PHONETIC $text \"$escape_digits\"");
	}

	/**
	 * Say a given time, returning early if any of the given DTMF escape digits are received on the channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_say+time
	 * @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
	 * @param string $escape_digits
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function say_time($time=NULL, $escape_digits='') {
		if(is_null($time)) $time = time();
		return $this->evaluate("SAY TIME $time \"$escape_digits\"");
	}

	/**
	 * Send the specified image on a channel.
	 *
	 * Most channels do not support the transmission of images.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_send+image
	 * @param string $image without extension, often in /var/lib/asterisk/images
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the image is sent or
	 * channel does not support image transmission.
	 */
	public function send_image($image) {
		return $this->evaluate("SEND IMAGE $image");
	}

	/**
	 * Send the given text to the connected channel.
	 *
	 * Most channels do not support transmission of text.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_send+text
	 * @param $text
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the text is sent or
	 * channel does not support text transmission.
	 */
	public function send_text($text) {
		return $this->evaluate("SEND TEXT \"$text\"");
	}

	/**
	 * Cause the channel to automatically hangup at $time seconds in the future.
	 * If $time is 0 then the autohangup feature is disabled on this channel.
	 *
	 * If the channel is hungup prior to $time seconds, this setting has no effect.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+autohangup
	 * @param integer $time until automatic hangup
	 * @return array, see evaluate for return information.
	 */
	public function set_autohangup($time=0) {
		return $this->evaluate("SET AUTOHANGUP $time");
	}

	/**
	 * Changes the caller ID of the current channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+callerid
	 * @param string $cid example: "John Smith"<1234567>
	 * This command will let you take liberties with the <caller ID specification> but the format shown in the example above works
	 * well: the name enclosed in double quotes followed immediately by the number inside angle brackets. If there is no name then
	 * you can omit it. If the name contains no spaces you can omit the double quotes around it. The number must follow the name
	 * immediately; don't put a space between them. The angle brackets around the number are necessary; if you omit them the
	 * number will be considered to be part of the name.
	 * @return array, see evaluate for return information.
	 */
	function set_callerid($cid) {
		return $this->evaluate("SET CALLERID $cid");
	}

	/**
	 * Sets the context for continuation upon exiting the application.
	 *
	 * Setting the context does NOT automatically reset the extension and the priority; if you want to start at the top of the new
	 * context you should set extension and priority yourself.
	 *
	 * If you specify a non-existent context you receive no error indication (['result'] is still 0) but you do get a
	 * warning message on the Asterisk console.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+context
	 * @param string $context
	 * @return array, see evaluate for return information.
	 */
	public function set_context($context) {
		return $this->evaluate("SET CONTEXT $context");
	}

	/**
	 * Set the extension to be used for continuation upon exiting the application.
	 *
	 * Setting the extension does NOT automatically reset the priority. If you want to start with the first priority of the
	 * extension you should set the priority yourself.
	 *
	 * If you specify a non-existent extension you receive no error indication (['result'] is still 0) but you do
	 * get a warning message on the Asterisk console.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+extension
	 * @param string $extension
	 * @return array, see evaluate for return information.
	 */
	public function set_extension($extension) {
		return $this->evaluate("SET EXTENSION $extension");
	}

	/**
	 * Enable/Disable Music on hold generator.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+music
	 * @param boolean $enabled
	 * @param string $class
	 * @return array, see evaluate for return information.
	 */
	public function set_music($enabled=true, $class='') {
		$enabled = ($enabled) ? 'ON' : 'OFF';
		return $this->evaluate("SET MUSIC $enabled $class");
	}

	/**
	 * Set the priority to be used for continuation upon exiting the application.
	 *
	 * If you specify a non-existent priority you receive no error indication (['result'] is still 0)
	 * and no warning is issued on the Asterisk console.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+priority
	 * @param integer $priority
	 * @return array, see evaluate for return information.
	 */
	public function set_priority($priority) {
		return $this->evaluate("SET PRIORITY $priority");
	}

	/**
	 * Sets a variable to the specified value. The variables so created can later be used by later using ${<variablename>}
	 * in the dialplan.
	 *
	 * These variables live in the channel Asterisk creates when you pickup a phone and as such they are both local and temporary.
	 * Variables created in one channel can not be accessed by another channel. When you hang up the phone, the channel is deleted
	 * and any variables in that channel are deleted as well.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_set+variable
	 * @param string $variable is case sensitive
	 * @param string $value
	 * @return array, see evaluate for return information.
	 */
	public function set_variable($variable, $value) {
		$value = str_replace("\n", '\n', addslashes($value));
		return $this->evaluate("SET VARIABLE $variable \"$value\"");
	}

	/**
	 * Play the given audio file, allowing playback to be interrupted by a DTMF digit. This command is similar to the GET DATA
	 * command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of
	 * digits before returning.
	 *
	 * @example examples/ping.php Ping an IP address
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_stream+file
	 * @param string $filename without extension, often in /var/lib/asterisk/sounds
	 * @param string $escape_digits
	 * @param integer $offset
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function stream_file($filename, $escape_digits='', $offset=0) {
		return $this->evaluate("STREAM FILE $filename \"$escape_digits\" $offset");
	}

	/**
	 * Enable or disable TDD transmission/reception on the current channel.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_tdd+mode
	 * @param string $setting can be on, off or mate
	 * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 if the channel is not TDD capable.
	 */
	public function tdd_mode($setting) {
		return $this->evaluate("TDD MODE $setting");
	}

	/**
	 * Sends $message to the Asterisk console via the 'verbose' message system.
	 *
	 * If the Asterisk verbosity level is $level or greater, send $message to the console.
	 *
	 * The Asterisk verbosity system works as follows. The Asterisk user gets to set the desired verbosity at startup time or later
	 * using the console 'set verbose' command. Messages are displayed on the console if their verbose level is less than or equal
	 * to desired verbosity set by the user. More important messages should have a low verbose level; less important messages
	 * should have a high verbose level.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_verbose
	 * @param string $message
	 * @param integer $level from 1 to 4
	 * @return array, see evaluate for return information.
	 */
	public function verbose($message, $level=1) {
		foreach(explode("\n", str_replace("\r\n", "\n", print_r($message, true))) as $msg) {
			// Enable for extra logging.
			//        @syslog(LOG_WARNING, $msg);
			$ret = $this->evaluate("VERBOSE \"$msg\" $level");
		}
		return $ret;
	}

	/**
	 * Waits up to $timeout milliseconds for channel to receive a DTMF digit.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AGICommand_wait+for+digit
	 * @param integer $timeout in millisecons. Use -1 for the timeout value if you want the call to wait indefinitely.
	 * @return array, see evaluate for return information. ['result'] is 0 if wait completes with no
	 * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
	 */
	public function wait_for_digit($timeout=-1) {
		return $this->evaluate("WAIT FOR DIGIT $timeout");
	}


	// *********************************************************************************************************
	// **                       APPLICATIONS                                                                  **
	// *********************************************************************************************************

	/**
	 * Executes an AGI compliant application.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+Application_AGI
	 * @param string $command
	 * @param string $args
	 * @return array, see evaluate for return information. ['result'] is -1 on hangup or if application requested hangup, or 0 on non-hangup exit.
	 */
	public function exec_agi($command, $args) {
		return $this->exec("AGI $command", $args);
	}

	/**
	 * Set Account Code
	 *
	 * Set the channel account code for billing purposes.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/CDR+Applications
	 * @param string $accountcode
	 * @return array, see evaluate for return information.
	 */
	public function exec_setaccountcode($accountcode) {
		return $this->exec('SetAccount', $accountcode);
	}

	/**
	 * SIPAddHeader
	 *
	 * Adds a header to a SIP call placed with DIAL.
	 * Remember to use the X-header if you are adding non-standard SIP headers, like X-Asterisk-Accountcode:. Use this with care.
	 * Adding the wrong headers may jeopardize the SIP dialog.
	 * Always returns 0.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+Application_SIPAddHeader
	 * @param string $header SIP Header
	 * @param string $value SIP Header Value
	* @return array, see evaluate for return information.
	*/
	public function exec_sipaddheader($header, $value) {
		return $this->exec('SIPAddHeader', '"'.$header.":".$value.'"');
	}

	/**
	 * Alertinfo
	 *
	 * @param string $value SIP Alertinfo to set
	 * @return array, see evaluate for return information.
	 * @TODO this needs to be in a higher level class
	 */
	public function set_alertinfo($value) {
		return $this->exec_sipaddheader('Alert-Info',$value);
	}

	/**
	 * Dial.
	 *
	 * Dial takes input from ${VXML_URL} to send XML Url to Cisco 7960
	 * Dial takes input from ${ALERT_INFO} to set ring cadence for Cisco phones
	 * Dial returns ${CAUSECODE}: If the dial failed, this is the errormessage.
	 * Dial returns ${DIALSTATUS}: Text code returning status of last dial attempt.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+Application_Dial
	 * @param string $type
	 * @param string $identifier
	 * @param integer $timeout
	 * @param string $options
	 * @param string $url
	 * @return array, see evaluate for return information.
	 */
	public function exec_dial($type, $identifier, $timeout=NULL, $options=NULL, $url=NULL) {
		return $this->exec('Dial', trim("$type/$identifier,$timeout,$options,$url", ','));
	}

	/**
	 * Goto.
	 *
	 * This function takes three arguments: context,extension, and priority, but the leading arguments
	 * are optional, not the trailing arguments.  Thuse goto($z) sets the priority to $z.
	 *
	 * @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+Application_Goto
	 * @param string $a
	 * @param string $b;
	 * @param string $c;
	 * @return array, see evaluate for return information.
	 */
	public function exec_goto($a, $b=NULL, $c=NULL) {
		return $this->exec('Goto', trim("$a,$b,$c", ','));
	}

	/**
	 * Evaluate an AGI command.
	 *
	 * @param string $command
	 * @return array ('code'=>$code, 'result'=>$result, 'data'=>$data)
	 */
	private function evaluate($command) {
		$broken = array('code'=>500, 'result'=>-1, 'data'=>'');

		// FREEPBX-7204 - Discard any cruft, errors, etc, that may have been
		// produced by Asterisk on startup. This is single threaded here, so
		// dropping anything pending hopefully shouldn't cause issues.
		stream_set_blocking($this->in, 0);
		while (fgets($this->in) !== false) { } // Discard
		stream_set_blocking($this->in, 1);

		// write command
		if(is_null($this->socket)) {
			if(!@fwrite($this->out, trim($command) . "\n")) {
				return $broken;
			}
			fflush($this->out);
		} elseif(socket_write($this->socket, trim($command) . "\n") === false) {
			return $broken;
		}

		// Read result.  Occasionally, a command returns a string followed by an extra new line.
		// When this happens, our script will ignore the new line, but it will still be in the
		// buffer.  So, if we get a blank line, it is probably the result of a previous
		// command.  We read until we get a valid result or asterisk hangs up.  One offending
		// command is SEND TEXT.
		$count = 0;
		do {
			$str = is_null($this->socket) ? @fgets($this->in, 4096) : @socket_read($this->socket, 4096, PHP_NORMAL_READ);
		} while($str == '' && $count++ < 5);

		if($count >= 5) {
			//        $this->conlog("evaluate error on read for $command");
			return $broken;
		}

		// parse result
		$ret['code'] = substr($str, 0, 3);
		$str = trim(substr($str, 3));

		// we have a multiline response!
		if($str{0} == '-') {
			$count = 0;
			$str = substr($str, 1) . "\n";

			$line = is_null($this->socket) ? @fgets($this->in, 4096) : @socket_read($this->socket, 4096, PHP_NORMAL_READ);
			while(substr($line, 0, 3) != $ret['code'] && $count < 5) {
				$str .= $line;
				$line = is_null($this->socket) ? @fgets($this->in, 4096) : @socket_read($this->socket, 4096, PHP_NORMAL_READ);
				$count = (trim($line) == '') ? $count + 1 : 0;
			}
			if($count >= 5) {
				//          $this->conlog("evaluate error on multiline read for $command");
				return $broken;
			}
		}

		$ret['result'] = NULL;
		$ret['data'] = '';
		// some sort of error
		if($ret['code'] != AGIRES_OK) {
			$ret['data'] = $str;
			$this->conlog(print_r($ret, true));
		// normal AGIRES_OK response
		} else {
			$parse = explode(' ', trim($str));
			$in_token = false;
			foreach($parse as $token) {
				// we previously hit a token starting with '(' but not ending in ')'
				if($in_token) {
					$tmp = trim($token);
					$tmp = $tmp{0} == '(' ? substr($tmp,1):$tmp;
					$tmp = substr($tmp,-1) == ')' ? substr($tmp,0,strlen($tmp)-1):$tmp;
					$ret['data'] .= ' ' . trim($tmp);
					if($token{strlen($token)-1} == ')') {
						$in_token = false;
					}
				} elseif($token{0} == '(') {
					if($token{strlen($token)-1} != ')') {
						$in_token = true;
					}
					$tmp = trim(substr($token,1));
					$tmp = $in_token ? $tmp : substr($tmp,0,strlen($tmp)-1);
					$ret['data'] .= ' ' . trim($tmp);
				} elseif(strpos($token, '=')) {
					$token = explode('=', $token);
					$ret[$token[0]] = $token[1];
				} elseif($token != '') {
					$ret['data'] .= ' ' . $token;
				}
			}
			$ret['data'] = trim($ret['data']);
		}

		// log some errors
		if($ret['result'] < 0) {
			$this->conlog("$command returned {$ret['result']}");
		}

		return $ret;
	}

	/**
	 * Log to console if debug mode.
	 *
	 * @example examples/ping.php Ping an IP address
	 *
	 * @param string $str
	 * @param integer $vbl verbose level
	 */
	protected function conlog($str, $vbl=1) {
		static $busy = false;

		if(isset($this->config['phpagi'], $this->config['phpagi']['debug']) && $this->config['phpagi']['debug'] != false) {
			if(!$busy) { // no conlogs inside conlog!!!
				$busy = true;
				$this->verbose($str, $vbl);
				$busy = false;
			}
		}
	}
}

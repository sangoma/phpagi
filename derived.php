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
class derived extends AGI {
	/**
	 * Goto_dest - Set context, extension and priority.
	 *
	 * @param string $context
	 * @param string $extension
	 * @param string $priority
	 */
	function goto_dest($context, $extension='s', $priority=1) {
		$this->set_context($context);
		$this->set_extension($extension);
		$this->set_priority($priority);
	}

	/**
	 * Parse caller id.
	 *
	 * @example examples/dtmf.php Get DTMF tones from the user and say the digits
	 * @example examples/input.php Get text input from the user and say it back
	 *
	 * "name" <proto:user@server:port>
	 *
	 * @param string $callerid
	 * @return array('Name'=>$name, 'Number'=>$number)
	 */
	function parse_callerid($callerid=NULL) {
		if(is_null($callerid)) {
			$callerid = $this->request['agi_callerid'];
		}

		$ret = array('name'=>'', 'protocol'=>'', 'username'=>'', 'host'=>'', 'port'=>'');
		$callerid = trim($callerid);

		if($callerid{0} == '"' || $callerid{0} == "'") {
			$d = $callerid{0};
			$callerid = explode($d, substr($callerid, 1));
			$ret['name'] = array_shift($callerid);
			$callerid = join($d, $callerid);
		}

		$callerid = explode('@', trim($callerid, '<> '));
		$username  = explode(':', array_shift($callerid));
		if(count($username) == 1) {
			$ret['username'] = $username[0];
		} else {
			$ret['protocol'] = array_shift($username);
			$ret['username'] = join(':', $username);
		}

		$callerid = join('@', $callerid);
		$host = explode(':', $callerid);
		if(count($host) == 1) {
			$ret['host'] =  $host[0];
		} else {
			$ret['host'] = array_shift($host);
			$ret['port'] = join(':', $host);
		}

		return $ret;
	}
}

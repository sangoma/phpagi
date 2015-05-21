<?php

class ManagerTest extends PHPUnit_Framework_TestCase {

	protected static $manager;

	public static function setUpBeforeClass() {
		include("config.php");
		include(dirname(__DIR__)."/manager.php");
		$config = array(
			"username" => $user['username'],
			"secret" => $user['secret']
		);
		$manager = new Asterisk\AMI\Manager($config);
		$manager->connect();

		self::$manager = $manager;
	}

	public function testCoreSettings() {
		$settings = self::$manager->CoreSettings();

		$this->assertNotEmpty($settings);
		$this->assertArrayHasKey("AMIversion",$settings);
		$this->assertArrayHasKey("AsteriskVersion",$settings);
	}

	/*
	public function testOriginate() {
		$user = $this->getValidUser();
		$this->assertNotEmpty($user);
		print_r($user);
		$request = self::$manager->Originate("SIP/1002@from-internal","*43","default",1,1,"Asterisk Automatic Wardial","","","","");
		print_r($request);
	}
	*/

	public function testSendRequest() {
		$request = self::$manager->send_request("ListCommands");
		$this->assertEquals("Success", $request['Response']);
	}

	public function testAbsoluteTimeout() {
		$request = self::$manager->AbsoluteTimeout("SIP/1000","30");
		$this->assertEquals("No such channel", $request['Message']);
	}

	/*
	public function testAgentLogoff() {
		$request = self::$manager->AgentLogoff("1","false");
		print_r($request);
		$request = self::$manager->AgentLogoff("1","true");
	}

	public function testAgents() {
		$agents = self::$manager->Agents();
		print_r($agents);
	}
	*/

	public function testCommand() {
		$help = self::$manager->Command("core show help");

		$this->assertNotEmpty($help);
		$this->assertArrayHasKey("data",$help);
		$this->assertEquals("Follows", $help['Response']);
	}

	public function testCodecs() {
		$codecs = self::$manager->Codecs();
		$this->assertNotEmpty($codecs);
	}

	private function getValidUser() {
		$peers = self::$manager->Command("sip show users");
		$lines = explode("\n",$peers['data']);
		foreach($lines as $line) {
			if(preg_match("/^(\d*)\s/",$line,$matches)) {
				return "SIP/".$matches[1];
			}
		}
		return null;
	}
}

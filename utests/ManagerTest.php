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
}

<?php
	/*
	 * release.php : $Id: release.php,v 1.1 2005/03/14 19:36:45 masham Exp $
	 *
	 * makes a release tarball.  snags the version number out of
	 * the Id tag in phpagi.php.
	 */	
	 
	$version=`grep '\$Id:' phpagi.php`;
	$version=str_replace("  ","",$version);
	$foo=explode(" ",$version);
	$version=$foo[3];
	
	$dist="phpagi-".$version;
	if(file_exists($dist)){
		echo "Erp.  $dist already exists.  Remove it first.\n";
		exit;
	}
	
	mkdir($dist);
	if(!file_exists($dist)){
		echo "$dist wasn't created??\n";
		exit;
	}	
	echo "Releasing for version $version. \nPress CTRL-C *NOW* if this is wrong.\n\n";
	for($i=5;$i>0;$i--) {
		echo "$i...\n";
		sleep(1);
	}
	
	echo "poof!\n";
	


	
	
	$files=array(
		"README",
		"COPYING",	
		"phpagi.php",
		"phpagi-asmanager.php",
		"phpagi-fastagi.php",
		"mkdocs.php",
	);
	
	$examples=array(
		"examples/README",
		"examples/beep.gsm",
		"examples/dtmf.php",
		"examples/input.php",
		"examples/my_ip.php", 
		"examples/ping.gsm",
		"examples/ping.php",
		"examples/ring.php",
		"examples/sip_show_peer.php",
		"examples/weather.php",
		"examples/weather.txt.gz",
		"examples/thanks.gsm"
	);
	$docs=array(
		"CHANGELOG",
		"fastagi.xinetd",
		"phpagi.example.conf",
		"README.phpagi",
		"README.phpagi-asmanager",
		"README.phpagi-fastagi"
		
		
		
	);
	
	foreach($files as $file){
		$e="cp $file $dist";
		echo $e."\n";
		system($e);
	}

	echo "Documentation..\n";
	mkdir("$dist/docs");
	foreach($docs as $doc){
		$e="cp docs/$doc $dist/docs";
		echo $e."\n";
		system($e);
	}
	$e="cp -R api-docs $dist";
	echo $e;
	system($e);
	$ball="$dist".".tgz";
	$e="tar czf $ball $dist";
	system($e);
	system("md5sum $ball > $ball.md5");
	system("ls -al $ball*");
	echo "Done!\n";
	
	// now do samples.
	$dist="phpagi-examples-$version";
	mkdir($dist);
	
	foreach($examples as $file){
		$e="cp $file $dist";
		echo $e."\n";
		system($e);
	}
	
	$ball="phpagi-examples-".$version.".tgz";
	$e="tar czpf $ball $dist";
	system($e);
	system("md5sum $ball > $ball.md5");
	system("ls -al $ball*");
?>
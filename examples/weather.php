#!/usr/local/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');

  $agi = new AGI();
  $agi->answer();

  $agi->text2wav('Please enter your zip code.');
  $result = $agi->get_data('beep', 3000, 5);
  $search = $result['result'];

  $db = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'weather.txt';
  $min = 0; $max = filesize($db);
  $fp = fopen($db, 'r');
  do
  {
    $mid = floor(($min + $max) / 2);
    fseek($fp, $mid);
    fgets($fp, 4096);
    list($zip, $city, $state, $latitude, $longitude, $station) = explode("\t", trim(fgets($fp, 4096)));
    if($search < $zip)
      $max = $mid;
    elseif($search > $zip)
      $min = $mid;
  } while($max - $min > 1 && $zip != $search);
  fclose($fp);

  if($search != $zip)
    $text = "I could not find the weather station for $zip";
  else
  {
    $xml = join('', file("http://www.nws.noaa.gov/data/current_obs/$station.xml"));
    $vals = $index = NULL;
    $p = xml_parser_create();
    xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($p, $xml, $vals, $index);
    xml_parser_free($p);
    $i = 0;
    $data = get_children($vals, $i);

    $text = "The closes weather station to $city is at {$data['LOCATION']}. ";
    $text .= "It is currently {$data['WEATHER']}. The temperature is {$data['TEMP_F']} degrees";
    if($data['WIND_MPH'] > 0)
      $text .= " with wind from the {$data['WIND_DIR']} at {$data['WIND_MPH']} miles per hour";
    if($data['WINDCHILL_F'] != 'Not Applicable')
      $text .= ". The wind chill is {$data['WINDCHILL_F']} degrees";
    if($data['HEAT_INDEX_F'] != 'Not Applicable')
      $text .= ". The heat index is {$data['HEAT_INDEX_F']}";
    $text .= '.';
  }

  $agi->text2wav($text);

  $agi->text2wav('Goodbye');
  $agi->hangup();


  function get_children($vals, &$i)
  {
    $ret = array();
    for(++$i; $i < count($vals); $i++)
    {
      if(isset($vals[$i]['attributes']['NAME']))
        $name = $vals[$i]['attributes']['NAME'];
      else
        $name = $vals[$i]['tag'];

      if($name != '' && $vals[$i]['type'] == 'open')
        $rt[$name][] = get_children($vals, $i);
      elseif($vals[$i]['type'] == 'close')
      {
        if(isset($rt)) foreach($rt as $key=>$val)
        {
          if(count($val) == 1)
            $ret[$key] = $val[0];
          else
            $ret[$key] = $val;
        }
        return $ret;
      }
      elseif($name != '')
      {
        if(isset($vals[$i]['attributes']['VALUE']))
          $rt[$name][] = $vals[$i]['attributes']['VALUE'];
        elseif(isset($vals[$i]['value']))
          $rt[$name][] = $vals[$i]['value'];
      }
    }
    return $ret;
  }
?>

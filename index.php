<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="apple-touch-icon" sizes="180x180" href="/traindomiser/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/traindomiser/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/traindomiser/favicon-16x16.png">
<link rel="manifest" href="/traindomiser/manifest.json">
<link rel="mask-icon" href="/traindomiser/safari-pinned-tab.svg" color="#5bbad5">
<link rel="shortcut icon" href="/traindomiser/favicon.ico">
<meta name="msapplication-config" content="/traindomiser/browserconfig.xml">
<meta name="theme-color" content="#ffffff">

 
  <title>Traindomiser</title>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
  <script>
  function getLocation() {
      if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(savePosition, positionError, {timeout:10000});
      } else {
          //Geolocation is not supported by this browser
      }
  }

  // handle the error here
  function positionError(error) {
      var errorCode = error.code;
      var message = error.message;

      alert(message);
  }

  function savePosition(position) {
       $("#lat").val(position.coords.latitude);
       $("#lon").val(position.coords.longitude);
  }
  </script>
  
  <style type='text/css'>
	  body {
	  	  -webkit-font-smoothing: antialiased;
		  padding: 1% 5%;
		  font-family: system-ui, -apple-system, helvetica, arial, sans-serif;
		  font-size: 20px;
		  background: #f3f3f3 url('gplaypattern.png');
	  }
	  .arthurresult {
		  background: rgba(200,200,200,0.5);
		  padding: 30px;
		  border-radius: 10px;
	  }
	  .otherresult {
		  background: rgba(150,150,150,0.5);
		  padding: 30px;
		  border-radius: 10px;
	  }
	  .otherresult a , .arthurresult a {
	  	display: block;
	  }
	  form input {
  		  font-size: 24px !important;
	  }
	  h1 a {
		  text-decoration: none;
	  }
	  .bigbutton {
		  font-size: 150% !important;
	  }
	  .smaller {
		  font-size: 16px;
	  }
	  .appicon {
		  width: 48px;
		  height: 48px;
		  position: relative;
		  top: 10px;
		  border-radius: 5px;
	  }
	  .destination {
		  color: #eee;
		  text-transform: uppercase;
		  font-size: 3em;
		  font-weight: bold;
		  width: 80%;
		  border-radius: 10px;
		  background: #333;
		  padding: 20px;
		  display: block;
		  text-decoration: none;
		  text-align: center;
		  margin: 20px auto;
		  box-shadow: 0px 0px 5px #666;
		  overflow: hidden;
	  }
	  .revealbox {
		  display: none;
	  }
	  .hidelatlon {
		  display: none;
	  }
	  .showlatlon {
		  display: block;
	  }
	  @media(max-width:767px) {
	  	  .destination {
		  	font-size: 1.3em;
		  	width: 85%;
		  }
	  }
  </style>
</head>
<body>
<h1><a href='/traindomiser/'><img src='apple-touch-icon.png' class='appicon' alt='' /> The Traindomiser</a></h1>
<p>Helps you pick a random UK destination and plan your train route there. <a href="/traindomiser/trip/">Follow our family's trip.</a></p>
<?php

function arthur_distance($lat1, $lon1, $lat2, $lon2, $unit) {

  $theta = $lon1 - $lon2;
  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
  $dist = acos($dist);
  $dist = rad2deg($dist);
  $miles = $dist * 60 * 1.1515;
  $unit = strtoupper($unit);

  if ($unit == "K") {
      return ($miles * 1.609344);
  } else if ($unit == "N") {
      return ($miles * 0.8684);
  } else {
	  return $miles;
  }
}


function arthur_findSomewhereWithinXMiles($lat,$lon,$max,$override="") {
	global $key;
	global $appid;
	
	// avoid infinite loops 
	$maxiterations = ($override) ? 1 : 50;
	$i = 0;
		
	$distance = NULL;
	
	do {
		// pick a random place
		$placescsv = array_map('str_getcsv', file('places.csv'));
		shuffle($placescsv);
		
		if (strlen($override)>0) {
			$loc = ucfirst($override);
		} else {
			$loc = $placescsv[0][0];
		}
	
		$locs[] = $loc; // store in a list so we can animate results
	
		// geocode it (via lookup or cache)
		if (file_exists("cache/settlement/".$loc.".json")) {
			$placeresult = file_get_contents("cache/settlement/".$loc.".json");
		} else {
			$place = "http://transportapi.com/v3/uk/places.json?query=".urlencode($loc)."&type=settlement&app_id=".$appid."&app_key=".$key;
			$placeresult = file_get_contents($place);
			if (stristr($endpointresult,"error")) {
				die("Error matching place to stations: ".print_r(json_decode($placeresult)));
			}
			$savecached = file_put_contents("cache/settlement/".$loc.".json",$placeresult);
		}
		
		$result = json_decode($placeresult);
		
		// get the nearest station to that location
		$nearest_station = arthur_findMyNearestStation($result->member[0]->latitude,$result->member[0]->longitude);

		if (strlen($override)>0) {
			$distance = arthur_distance($result->member[0]->latitude,$result->member[0]->longitude,$result->member[0]->latitude,$result->member[0]->longitude);
		} else {
			$distance = arthur_distance($lat,$lon,$result->member[0]->latitude,$result->member[0]->longitude);		
		}

		$i++;
		sleep(0.01);
		
	} while ( ($distance > $max || $distance < $max*0.1) && $i <= $maxiterations); // can't be further than max or less than 10% of max
	
	do {
		$extralocs[] = $placescsv[$i][0];
		$i++;
	} while ((count($extralocs)+count($locs))<20); // add some more destinations so animation looks good
	
	$locs = array_merge($extralocs,$locs);
	//$locs = array_slice(array_merge($extralocs,$locs),24);
			
	return array(
		"loc" => $loc, 
		"lat" => $result->member[0]->latitude,
		"lon" => $result->member[0]->longitude,
		"distance" => $distance,
		"result" => $result->member[0],
		"station" => $nearest_station->stations[0],
		"locs" => $locs,
	);
	
}

function arthur_findMyNearestStation($lat,$lon) {
	global $key;
	global $appid;
	
	$hash = md5($lat.$lon);
	
	if (file_exists("cache/station/".$hash.".json")) {
		$endpointresult = file_get_contents("cache/station/".$hash.".json");
	} else {
		$endpoint = "http://transportapi.com/v3/uk/train/stations/near.json?app_key=".$key."&app_id=".$appid."&lat=".$lat."&lon=".$lon."&rpp=10";
		$endpointresult = file_get_contents($endpoint);
		if (stristr($endpointresult,"error")) {
			die("Error looking up nearby stations: ".print_r(json_decode($endpointresult)));
		}
		$saveendpointresultcached = file_put_contents("cache/station/".$hash.".json",$endpointresult);
	}
	$result = json_decode($endpointresult);
			
	return $result;
	
}

$key = "YOURKEYHERE"; //https://developer.transportapi.com/
$appid = "YOURAPPIDHERE"; //https://developer.transportapi.com/

$lat = filter_var($_REQUEST['lat'],FILTER_SANITIZE_STRING);
$lon = filter_var($_REQUEST['lon'],FILTER_SANITIZE_STRING);
$override = filter_var($_REQUEST['override'],FILTER_SANITIZE_STRING);
$max = (strlen($_REQUEST['max'])>0) ? filter_var($_REQUEST['max'],FILTER_SANITIZE_STRING) : 200;
$adults = filter_var($_REQUEST['adults'],FILTER_SANITIZE_STRING);
$children = filter_var($_REQUEST['children'],FILTER_SANITIZE_STRING);


if ( ($_REQUEST['lat'] && $_REQUEST['lon'] ) || $_REQUEST['override']) {
	$magicresult = arthur_findSomewhereWithinXMiles($lat,$lon,$max,$override);
	
	if ($lat && $lon) {
		$nearest = arthur_findMyNearestStation($lat,$lon);			
	} else { // maybe override
		$nearest = arthur_findMyNearestStation($magicresult['lat'],$magicresult['lon']);	
	}

	// other places, for animation
	
	$counter = 1;
	
	foreach((array)$magicresult['locs'] as $dest) {
		if ($dest == $magicresult['loc']) { // actual result
			$destinations .= "<a id='dest-{$counter}' class='destination' href='https://www.google.co.uk/maps?q={$magicresult['station']->latitude},{$magicresult['station']->longitude}' target='_blank'>".$magicresult['station']->name."</a>\r";	
		} else {
			$destinations .= "<a id='dest-{$counter}' class='destination' href='#'>".$dest."</a>\r";	
		}
		$counter++;
	}
	
	echo "<p id='destinations'></p>";
	
	echo "<p class='arthurresult' style='text-align: center'>You are going to";
	
	echo $destinations;
	
	echo "<span class='reveal'>station which is ".round($magicresult['distance'],1)." miles away.</span></p><p class='arthurresult revealbox'>It's near to ".$magicresult['loc']." (".number_format($magicresult['station']->distance)."m) and your nearest station right now is ".$nearest->stations[0]->name." (".number_format($nearest->stations[0]->distance)."m)";
	
	echo "<br /><br />
		<a href='https://traintimes.org.uk/" . $nearest->stations[0]->station_code . "/" . $magicresult['station']->station_code ."' target='_blank'>Train times from ".$nearest->stations[0]->name."</a><br/ > 
		<a href='https://traintimes.org.uk/" . $nearest->stations[1]->station_code . "/" . $magicresult['station']->station_code ."' target='_blank'>Train times from ".$nearest->stations[1]->name."</a><br/ > 
		<a href='https://traintimes.org.uk/" . $nearest->stations[2]->station_code . "/" . $magicresult['station']->station_code ."' target='_blank'>Train times from ".$nearest->stations[2]->name."</a></p>";
		
	echo "<p class='otherresult revealbox'>
	<a class='btn btn-lg' href='https://www.airbnb.co.uk/s/".urlencode($magicresult['station']->name).",UK/homes?adults={$adults}&children={$children}&&checkin=".date("Y-m-d")."' target='_blank'>Search Airbnb for ".$magicresult['station']->name."</a><br />
	<a class='btn btn-lg' href='https://www.booking.com/search.html?group_adults=2;group_children=2;no_rooms=1;sb_price_type=total;ss=".urlencode($magicresult['station']->name).",UK;' target='_blank'>Search Booking.com for ".$magicresult['station']->name."</a><br />
	<a class='btn btn-lg' href='https://www.google.co.uk/search?safe=strict&source=hp&q=things+to+do+in+".urlencode($magicresult['station']->name).", UK' target='_blank'>Search Google for things to do in ".$magicresult['station']->name."</a>
	</p>";
	
}

?>

<form id='latlon' action='index.php'>

	<?php if (($_REQUEST['lat'] && $_REQUEST['lon']) || $_REQUEST['override'] ) : ?>
		<p class='revealbox'><a href='#latlon' onclick='setLocToDestination();'>Set my location to <?= $magicresult['station']->name; ?></a></p>
		
	<?php endif; ?>
	<?php 
		if (!isset($_REQUEST['adults'])) {
			$_REQUEST['adults'] = "2"; //set default
			$_REQUEST['children'] = "2"; //set default
		}
	?>

	<!-- show a map if lat/lon are set, otherwise fields -->
	
	<?php if ($_REQUEST['lat'] && $_REQUEST['lon']) : ?>
	
	
		<iframe id="googlemap" width="100%" height="300" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=<?= $_REQUEST['lat']; ?>,<?= $_REQUEST['lon']; ?>&hl=es;z=14&amp;output=embed"
		 ></iframe><p id="googlemaplink" class='smaller'><a href="https://maps.google.com/maps?q=<?= $_REQUEST['lat']; ?>,<?= $_REQUEST['lon']; ?>&hl=es;z=14&amp;output=embed" target="_blank">See map bigger</a></p>
		 
		<p class='hidelatlon'>Your current latitude:<br /><input type='text' size='15' id='lat' name='lat' placeholder="Locating..." value="<?= $_REQUEST['lat'];?>" /><input type='hidden' id='latval' name='latval' value="<?= $magicresult['lat']; ?>" /></p>
		<p class='hidelatlon'>Your current longitude:<br /><input type='text' size='15' id='lon' name='lon' placeholder="Locating..."  value="<?= $_REQUEST['lon'];?>"  /> <input type='hidden' id='lonval' name='lonval' value="<?= $magicresult['lon']; ?>" /></p>

		 <input type='hidden' id='latval' name='latval' value="<?= $magicresult['lat']; ?>" />
		 <input type='hidden' id='lonval' name='lonval' value="<?= $magicresult['lon']; ?>" />
		 
	<?php else : ?>

		<p>Your current latitude:<br /><input type='text' size='15' id='lat' name='lat' placeholder="Locating..." value="<?= $_REQUEST['lat'];?>" /><input type='hidden' id='latval' name='latval' value="<?= $magicresult['lat']; ?>" /></p>
		<p>Your current longitude:<br /><input type='text' size='15' id='lon' name='lon' placeholder="Locating..."  value="<?= $_REQUEST['lon'];?>"  /> <input type='hidden' id='lonval' name='lonval' value="<?= $magicresult['lon']; ?>" /></p>
	
	<?php endif; ?>
	
	<p>Adults travelling:<br /><input type='text' size='5' id='adults' name='adults' value="<?= $_REQUEST['adults'];?>"  /></p>
	<p>Children travelling:<br /><input type='text' size='5' id='children' name='children' value="<?= $_REQUEST['children'];?>"  /></p>	
	<p>You want to travel up to:<br /><input type='text' size='5' id='max' name='max' value="<?= $max;?>" /> miles</p>
	<input type='submit' value='Find somewhere' class='bigbutton' />
</form>

<script>
	if($("#lat").val() == "" || $("#lon").val() == "") {
		getLocation();
	}
	   
	$( document ).ready(function() {
		$(".destination").hide();
		$(".reveal").css('color','transparent');
	});

	var dests = $(".destination");

	i = 0;
	
	(function cycle() { 
	  var delay = 100+(i*5/dests.length)*100; // get slower, for 'suspense'
      dests.eq(i).fadeIn(30).delay(delay);
      if (i < dests.length-1) {
		  dests.eq(i).fadeOut(30, cycle);
		  i++;	    
	  } else {
		  $(".reveal").css('color','inherit');
		  $(".revealbox").css('display','block');
	  }
	})();
	
	function setLocToDestination() {
		event.preventDefault;
		var destlat = $("#latval").val();
		$("#lat").val(destlat);
		var destlon = $("#lonval").val();
		$("#lon").val(destlon); 
		
		$("#googlemap").hide();
		$("#googlemaplink").hide();
		$(".hidelatlon").attr("class","showlatlon");
		return false;
	}
	
</script>

<hr />
<p class='smaller'><em>An Arthur &amp; Steph creation based on APIs from <a href='http://www.transportapi.com'>Transport API</a> and place list from <a href='http://www.paulstenning.com/uk-towns-and-counties-list/'>Paul Stenning</a></em></p>
</body>
</html>
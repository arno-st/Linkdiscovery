<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_map_install () {
	api_plugin_register_hook('map', 'top_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'top_graph_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'draw_navigation_text', 'map_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('map', 'config_settings', 'map_config_settings', 'setup.php'); // personl settings info
	api_plugin_register_hook('map', 'api_device_new', 'map_api_device_new', 'setup.php');
	api_plugin_register_hook('map', 'device_remove', 'map_device_remove', 'setup.php');
	api_plugin_register_hook('map', 'utilities_action', 'map_utilities_action', 'setup.php');
	api_plugin_register_hook('map', 'utilities_list', 'map_utilities_list', 'setup.php');

	api_plugin_register_realm('map', 'map.php', 'Plugin -> Map', 1);

	map_setup_table();
}

function plugin_map_uninstall () {
	// Do any extra Uninstall stuff here
	db_execute("DROP TABLE IF EXISTS `plugin_map_coordinate`;");
	db_execute("DROP TABLE IF EXISTS `plugin_map_host`;");
}

function plugin_map_check_config () {
	$ret = true;
	// Here we will check to ensure everything is configured
	map_check_upgrade ();
	$ret = map_check_dependencies();
	
	return $ret;
}

function plugin_map_upgrade () {
	// Here we will upgrade to the newest version
	map_check_upgrade ();
	return false;
}

function map_version () {
	return plugin_map_version();
}

function map_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'map.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = plugin_map_version ();
	$current = $version['version'];
	$old = read_config_option('plugin_map_version');
	if ($current != $old) {

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='map'");
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

function map_check_dependencies() {
	global $plugins, $config;
	if ((!in_array('settings', $plugins)) &&
		(db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='settings' AND status=1") == "")) {
		return false;
	}

	if (!function_exists('settings_version')) {
		if (file_exists($config['base_path'] . '/plugins/settings/setup.php')) {
			include_once($config['base_path'] . '/plugins/settings/setup.php');
			if (!function_exists('settings_version')) {
				return false;
			}
		}
	}
	$v = settings_version();
	if (!isset($v['version']) || $v['version'] < 0.3) {
		return false;
	}

	return true;
}

function plugin_map_version () {
	return array(
		'name'     => 'Map',
		'version'  => '0.31',
		'longname' => 'Map Viewer',
		'author'   => 'Arno Streuli',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'astreuli@gmail.com',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function map_config_settings () {
	global $tabs, $settings;
	$tabs["misc"] = "Misc";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['misc'] = 'Misc';
	$temp = array(
		"map_general_header" => array(
			"friendly_name" => "Map",
			"method" => "spacer",
			),
		"map_tools" => array(
			"friendly_name" => "Which maping tools",
			"description" => "Define the mapping tools used.",
			"method" => "drop_array",
//			'array' => array("0" => "GoogleMap", "1" => "OpenStreetMap"),
			'array' => array("0" => "GoogleMap"),
			"default" => "20"
			),
		"map_api_key" => array(
			"friendly_name" => "API Key",
			"description" => "This is key for google map usage.",
			"method" => "textbox",
			"max_length" => 80,
			"default" => ""
			),
		'map_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages during map file creation',
			'method' => 'checkbox',
			'default' => 'off'
			),
	);
	
	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;

}

function map_show_tab () {
	global $config;
	include_once($config["library_path"] . "/database.php");

	if (api_user_realm_auth('map.php')) {
		if (!substr_count($_SERVER["REQUEST_URI"], "map.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/map/map.php"><img src="' . $config['url_path'] . 'plugins/map/images/tab_gpsmap.gif" alt="Map" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/map/map.php"><img src="' . $config['url_path'] . 'plugins/map/images/tab_gpsmap_down.gif" alt="Map" align="absmiddle" border="0"></a>';
		}
	}
}

function map_draw_navigation_text ($nav) {
	$nav["map.php:"] = array("title" => "map", "mapping" => "", "url" => "map.php", "level" => "0");

	$nav['map.php:'] = array('title' => 'map', 'mapping' => 'index.php:', 'url' => 'map.php', 'level' => '1');
	return $nav;
}

function map_utilities_action ($action) {
	// get device list,  where snmp is active
	$dbquery = db_fetch_assoc("SELECT id, hostname, snmp_community, 
	snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
	ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
	snmp_priv_protocol, snmp_context FROM host WHERE snmp_version > '0' ORDER BY id");

	if ( (sizeof($dbquery) > 0) && $action == 'map_rebuild' || $action == 'coordinate_rebuild'){
		if ($action == 'map_rebuild') {
		// rebuild the map address table
			db_execute("TRUNCATE TABLE `plugin_map_coordinate`;");
			db_execute("TRUNCATE TABLE `plugin_map_host`;");

			foreach ($dbquery as $host) {
				// snmp_get syslocation, and geocodit
				$snmp_location = query_location ( $host );

				// geocod it, many Google query but the address is the geocoded one
				$gpslocation = geocod_address ( $snmp_location );
				if( $gpslocation == false) 
					continue;

				// check if this adress is present into the plugin_map_coordinate
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				if( $address_id == 0) // record does not exist
				{
					// save to new table with id and location
					$ret = db_execute("INSERT INTO plugin_map_coordinate (address, lat, lon) VALUES ('"
					. sql_sanitize($gpslocation[2]) ."','"
					. sql_sanitize($gpslocation[0]) . "', '"
					. sql_sanitize($gpslocation[1]) . "')");
				} 

				   // and add  host to plugin_map_host_table
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				db_execute("REPLACE INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");

				map_log("lati: ". $gpslocation[0]." longi: ".$gpslocation[1] );
			}
		} else if ($action == 'coordinate_rebuild') {
			// Empty the table first
			db_execute("TRUNCATE TABLE `plugin_map_host`;");

			foreach ($dbquery as $host) {
				// snmp_get syslocation
				$snmp_location = query_location ( $host );
				// geocod it, many Google query but the address is the geocoded one
				$gpslocation = geocod_address( $snmp_location );
				if( $gpslocation == false) 
					continue;

				// Find the address on the coordinate table
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				if( $address_id != 0 )
					db_execute("INSERT INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");

				map_log("host: " .$host['id']. " address: " .$address_id );
			}
		}
		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	} 
	return $action;
}

// query the snmp location from the host, host is an array with:
/* id, hostname, snmp_community, 
		snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
		ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
		snmp_priv_protocol, snmp_context */
function query_location( $host ) {
	global $config;
	$snmpsyslocation		 = ".1.3.6.1.2.1.1.6.0"; // system location
	include_once($config["library_path"] . '/snmp.php');

	$snmp_location = cacti_snmp_get( $host['hostname'], $host['snmp_community'], $snmpsyslocation, 
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], 
		$host['snmp_context'] ); 

map_log("\n\ndevice: ".$host['hostname']." ".$snmp_location );

	return $snmp_location;
}

// return the full address, lat, lon
function geocod_address( $snmp_location ) {
	$maptools = read_config_option('map_tools');

	// parse google map for geocoding
	$snmp_location = str_replace( ",", ";", $snmp_location );
	$address = explode( ';', $snmp_location ); // Suisse;Lausanne;Chemin de Pierre-de-Plan 4;-1;Local Telecom
	if( count($address) >= 3 ) {
		$location = $address[2]. "," .$address[1]. "," . $address[0];
		$location = str_replace(' ', '+', $location );
		$gpslocation = array();
		if( $maptools == '0' ) {
			$gpslocation = GoogleGeocode($location);
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetGeocode($location);
		}
		if($gpslocation != false ){
			$gpslocation[2] = str_replace ("'", " ", $gpslocation[2]); // replace ' by space
map_log("adresse: ".$gpslocation[2] );
		} 
	} else if( count($address) == 2 ) { 
		// gps coordinate
		$gpslocation['2'] = GoogleReverGeocode( $address[0], $address[1] );
		$gpslocation['1'] = $address[1]; // Longitude
		$gpslocation['0'] = $address[0]; // Latitude
	} else {
		map_log("Snmp location error: ".$snmp_location );
		$gpslocation = false;
	}
	return $gpslocation;
}

function map_utilities_list () {
	global $colors;

	html_header(array("Map Plugin"), 4);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=map_rebuild'>Rebuild mapping table</a>
		</td>
		<td class="textArea">
			This will rebuild the mapping coordinate table from the device list, clear of the location table.
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=coordinate_rebuild'>Rebuild host link to coordinate table</a>
		</td>
		<td class="textArea">
			This will rebuild the link from device to the coordinate (after a backup/restore of the coordinate table).
		</td>
	</tr>
	<?php
}

function map_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'address', 'type' => 'varchar(150)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'lat', 'type' => 'float(10,6)', 'NULL' => false , 'default' => '0');
	$data['columns'][] = array('name' => 'lon', 'type' => 'float(10,6)', 'NULL' => false, 'default' => '0');
	$data['type'] = 'MyISAM';
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'id', 'columns' => 'id');
	$data['comment'] = 'Plugin map - Table of map hosts coordinate';
	api_plugin_db_table_create('map', 'plugin_map_coordinate', $data);

	$data = array();
	$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'address_id', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['type'] = 'MyISAM';
	$data['primary'] = "host_id`,`address_id";
	$data['comment'] = 'Plugin map - Table of GPS coordinate';
	api_plugin_db_table_create('map', 'plugin_map_host', $data);
	
}

function map_api_device_new( $host ) {
	// device is saved, take the snmplocation to check with database
	$snmp_location = query_location ( $host );

	// geocod it, many Google query but the address is the geocoded one
	$gpslocation = geocod_address ( $snmp_location );
	if( $gpslocation == false) 
		return $host;

	// check if this adress is present into the plugin_map_coordinate
	$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
	if( $address_id == 0) // record does not exist
	{
		// save to new table with id and location
		$ret = db_execute("INSERT INTO plugin_map_coordinate (address, lat, lon) VALUES ('"
		. sql_sanitize($gpslocation[2]) ."','"
		. sql_sanitize($gpslocation[0]) . "', '"
		. sql_sanitize($gpslocation[1]) . "')");
	} 

   // and add  host to plugin_map_host_table
	$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
	db_execute("REPLACE INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");
	
	return $host;
}

function map_device_remove ($host) {
	map_log( "remove host: " . var_dump($host) );
	
}


// return formatted_address
function GoogleReverGeocode ($lat, $lng ) {
	global $config;
	$mapapikey = read_config_option('map_api_key');

    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$lng."&key={$mapapikey}&sensor=true";
	else 
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$lng."&sensor=true";

	//https://maps.googleapis.com/maps/api/geocode/json?latlng=46.51157,6.62179&amp;key=AIzaSyCpw0hNO2ZzIxKb9cTyrSPEN3ADvUTc5Xc&amp;sensor=true
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true, 512 );

    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
         // get the important data
        $formatted_address = str_replace ("'", " ", utf8_decode($resp['results'][0]['formatted_address']) );
		map_log("Google ReverseGeocoding: ". $formatted_address );
    } else{
		map_log("Google Geocoding error: ".$resp['status'] );
        $formatted_address = false;
    }

	return $formatted_address;
}

function GoogleGeocode($address){
	global $config;
	$mapapikey = read_config_option('map_api_key');
	//https://maps.googleapis.com/maps/api/geocode/json?address=4+chemin+pierre+de+plan,+Lausanne,+Suisse&key=AIzaSyAr0rad39hJtQLiRoPqsTstFW9u8kl6PYA
    // url encode the address
     
    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}&key={$mapapikey}";
	else 
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}";
 
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true, 512 );

    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
 
        // get the important data
        $lati = $resp['results'][0]['geometry']['location']['lat'];
        $longi = $resp['results'][0]['geometry']['location']['lng'];
        $formatted_address = utf8_decode($resp['results'][0]['formatted_address']);
         
        // verify if data is complete
        if($lati && $longi && $formatted_address){
         
            // put the data in the array
            $data_arr = array();            
             
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $formatted_address
                );
             
            return $data_arr;
             
        }else{
            return false;
        }
         
    }else{
		map_log("Google Geocoding error: ".$resp['status'] );
        return false;
    }
}

function OpenStreetGeocode($address){
    // url encode the address
    $address = urlencode($address);
map_log("Open Street Address: ".$address );

}

function map_log( $text ){
	$dolog = read_config_option('map_log_debug');
	if( $dolog ) cacti_log( $text, false, "MAP" );

}
?>

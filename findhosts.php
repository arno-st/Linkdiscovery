#!/usr/bin/php -q
<?php
/* Original Copyright:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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

/* We are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL ^ E_DEPRECATED);

include(dirname(__FILE__).'/../../include/global.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');
include_once($config['base_path'].'/lib/utility.php');
include_once($config['base_path'].'/lib/api_data_source.php');
include_once($config['base_path'].'/lib/api_graph.php');
include_once($config['base_path'].'/lib/snmp.php');
include_once($config['base_path'].'/lib/data_query.php');
include_once($config['base_path'].'/lib/api_device.php');
include_once($config['base_path'] . '/plugins/linkdiscovery/snmp.php');
include_once($config["base_path"] . '/lib/ping.php');
include_once($config["base_path"] . '/lib/api_tree.php');
include_once($config["base_path"] . "/lib/api_automation.php");

include_once($config["base_path"] . '/lib/sort.php');
include_once($config["base_path"] . '/lib/html_form_template.php');
include_once($config["base_path"] . '/lib/template.php');
include_once($config["base_path"] . "/plugins/thold/thold_functions.php");
include_once($config["base_path"] . "/plugins/thold/setup.php");
include_once($config['base_path'] . "/plugins/linkdiscovery/parse-url.php");

set_default_action('link_Discovery');
linkdiscovery_check_upgrade();

// snmp info
$cdpinterfacename    = ".1.3.6.1.4.1.9.9.23.1.1.1.1.6";
$cdpdeviceip         = ".1.3.6.1.4.1.9.9.23.1.2.1.1.4"; // hex value: 0A 55 00 0B -> 10 85 00 11
$cdpdevicename       = ".1.3.6.1.4.1.9.9.23.1.2.1.1.6";
$cdpremoteitfname    = ".1.3.6.1.4.1.9.9.23.1.2.1.1.7";
$cdpremotetype		 = ".1.3.6.1.4.1.9.9.23.1.2.1.1.8";
$cdpdevicecapacities = ".1.3.6.1.4.1.9.9.23.1.2.1.1.9";
// LLDP info
$lldpShortLocPortId  = ".1.0.8802.1.1.2.1.3.7.1.3";
$lldpLongLocPortId 	 = ".1.0.8802.1.1.2.1.3.7.1.4";

$lldpRemmgmt 	 	 = ".1.0.8802.1.1.2.1.4.1.1.5.0"; // mac address interface, or IP
$lldpRemCapa  		 = ".1.0.8802.1.1.2.1.4.1.1.6.0"; // 7 = B, R / 5 = B
$lldpRemShortPortId  = ".1.0.8802.1.1.2.1.4.1.1.7.0";
$lldpRemLongPortId   = ".1.0.8802.1.1.2.1.4.1.1.8.0";
$lldpRemSysName      = ".1.0.8802.1.1.2.1.4.1.1.9.0";
$lldpRemOsName       = ".1.0.8802.1.1.2.1.4.1.1.10.0";

$snmpifdescr		 = ".1.3.6.1.2.1.2.2.1.2";
$snmpsysname		 = ".1.3.6.1.2.1.1.5.0"; // system name
$snmpsysdescr		 = ".1.3.6.1.2.1.1.1.0"; // system description
$snmpserialno		= ".1.3.6.1.2.1.47.1.1.1.1.11.1001";

$isRouter = 0x01;
$isSRBridge = 0x04;
$isSwitch = 0x08;
$isHost = 0x10;
$isNexus = 0x200;
$isWifi = 0x02; // 2      000010
$isPhone = 0x80; //0x90 et équivalent isHost; // 144 10010000 


$current_time = strtotime("now");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

	switch ($arg) {
	case "-r":
		linkdiscovery_recreate_tables();
		break;
	case "-d":
		$debug = TRUE;
		break;
	case "-h":
		display_help();
		exit;
	case "-f":
		$forcerun = TRUE;
		break;
	case "-v":
		display_help();
		exit;
	case "--version":
		display_help();
		exit;
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

if (read_config_option("linkdiscovery_log_debug") == "on") $debug = TRUE;

if (read_config_option("linkdiscovery_collection_timing") == "disabled") {
	linkdiscovery_debug("Link Discovery Polling is set to disabled.\n");
	if(!isset($debug)) {
		exit;
	}
}

linkdiscovery_debug("Checking to determine if it's time to run.\n");
$poller_interval = read_config_option("poller_interval");

$seconds_offset = read_config_option("linkdiscovery_collection_timing");
$seconds_offset = $seconds_offset * 60;
$base_start_time = read_config_option("linkdiscovery_base_time");
$last_run_time = read_config_option("linkdiscovery_last_run_time");
$previous_base_start_time = read_config_option("linkdiscovery_prev_base_time");

if ($base_start_time == '') {
	linkdiscovery_debug("Base Starting Time is blank, using '12:00am'\n");
	$base_start_time = '12:00am';
}

$minutes = date("i", strtotime($base_start_time));
$hourdate = date("Y-m-d H:$minutes:00");
$hourtime = strtotime($hourdate);
linkdiscovery_debug($hourdate . " " . $hourtime . "\n");

/* see if the user desires a new start time */
linkdiscovery_debug("Checking if user changed the start time\n");
if (!empty($previous_base_start_time)) {
	if ($base_start_time <> $previous_base_start_time) {
		linkdiscovery_debug("User changed the start time from '$previous_base_start_time' to '$base_start_time'\n");
		unset($last_run_time);
		db_execute("DELETE FROM settings WHERE name='linkdiscovery_last_run_time'");
	}
}

/* set to detect if the user cleared the time between polling cycles */
db_execute("REPLACE INTO settings (name, value) VALUES ('linkdiscovery_prev_base_time', '$base_start_time')");

/* determine the next start time */
if (empty($last_run_time)) {
	if ($current_time > strtotime($base_start_time)) {
		/* if timer expired within a polling interval, then poll */
		if (($current_time - $poller_interval) < strtotime($base_start_time)) {
			$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time);
		}else{
			$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time) + $seconds_offset;
		}
	}else{
		$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time);
	}
}else{
	$next_run_time = $last_run_time + $seconds_offset;
}
$time_till_next_run = $next_run_time - $current_time;

if ($time_till_next_run < 0) {
	linkdiscovery_debug("The next run time has been determined to be NOW\n");
}else{
	linkdiscovery_debug("The next run time has been determined to be at   " . date("Y-m-d G:i:s", $next_run_time) . "\n");
}

if ($time_till_next_run > 0 && $forcerun == FALSE) {
	exit;
}

// check if findhost is allready running

if ($forcerun) {
	linkdiscovery_debug("Scanning has been forced\n");
}

/* Let's fake this to be on the current hour, instead of the actual time */
if ($forcerun == FALSE) {
	db_execute("REPLACE INTO settings (name, value) VALUES ('linkdiscovery_last_run_time', '$hourtime')");
}

//****************************************************
// read default domain
$domain_name = read_config_option("linkdiscovery_domain_name");
/* Do we use the FQDN name as the description? */
$use_fqdn_description = read_config_option("linkdiscovery_use_fqdn_for_description");
/* Do we use the IP for the hostname?  If not, use FQDN */
$use_ip_hostname = read_config_option("linkdiscovery_use_ip_hostname");
/* Do we use the IP for the hostname?  If not, use FQDN */
$update_hostname = read_config_option("linkdiscovery_update_hostname");
// wifi setup
$keepwifi = read_config_option("linkdiscovery_keep_wifi");
// phone setup
$keepphone = read_config_option("linkdiscovery_keep_phone");
// add the nu graphs from the old host, to the new host
$snmp_packets_query_graph = read_config_option("linkdiscovery_packets_graph");
// add the traffic graphs from the old host, to the new host
$snmp_traffic_query_graph = read_config_option("linkdiscovery_traffic_graph");
// add the status graphs, from the new host
$snmp_status_query_graph = read_config_option("linkdiscovery_status_graph");
// should we monitor the host
$monitor = read_config_option("linkdiscovery_monitor");
$thold_traffic_graph_template = read_config_option("linkdiscovery_traffic_thold");
$thold_status_graph_template = read_config_option("linkdiscovery_status_thold");

$snmp_community = read_config_option("snmp_community");
$snmp_community = ($snmp_community=='')?$known_hosts['snmp_community']:$snmp_community;

// check if extenddb is present, if so use it
if( db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='extenddb' AND status=1") != "") {
	$extenddb = true;
}

linkdiscovery_debug("Link Discovery is now running\n");

// Get information on the seed known host
$dbquery = db_fetch_assoc("SELECT id, host_template_id, description, hostname, snmp_community, snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id, max_oids, device_threads FROM host where host.id=" . read_config_option("linkdiscovery_seed"));

$known_hosts = array();
$known_hosts = $dbquery[0];

if (!is_array($known_hosts)) {
	linkdiscovery_debug("Link Discovery failed to pull seed hosts? Exiting.");
	exit;
}

$snmp_array = array(
"host_template_id"	   => '1', // generic snmp-enabled host as default
"snmp_community" 	   => "$snmp_community",
"snmp_port"            => $known_hosts['snmp_port'],
"snmp_timeout"         => read_config_option('snmp_timeout'),
"snmp_version" 		   => $known_hosts['snmp_version'],
"snmp_username"		   => $known_hosts['snmp_username'],
"snmp_password"		   => $known_hosts['snmp_password'],
"snmp_auth_protocol"   => $known_hosts['snmp_auth_protocol'],
"snmp_priv_passphrase" => $known_hosts['snmp_priv_passphrase'],
"snmp_priv_protocol"   => $known_hosts['snmp_priv_protocol'],
"snmp_context" 		   => $known_hosts['snmp_context'],
"snmp_engine_id" 	   => $known_hosts['snmp_engine_id'],
"disable"              => false,
"availability_method"  => read_config_option("ping_method"),
"ping_method"          => read_config_option("ping_method"),
"ping_port"            => read_config_option("ping_port"),
"ping_timeout"         => read_config_option("ping_timeout"),
"ping_retries"         => read_config_option("ping_retries"),
"notes"                => "Added by Link Discovery Plugin",
"device_threads"       => 1,
"max_oids"             => 10,
"snmp_retries" 		   => read_config_option("snmp_retries")
);

$hostdiscovered = array();

// emtpy the host table at each pooling
$tree_id = read_config_option("linkdiscovery_tree");
$sub_tree_id = read_config_option("linkdiscovery_sub_tree");
	
// fetch tree_items, if no return that mean the location has to be in the root tree
if ($sub_tree_id <> 0)
{
	$parent = db_fetch_row('SELECT parent FROM graph_tree_items WHERE graph_tree_id = ' . $tree_id. ' AND host_id=0 AND id='.$sub_tree_id);
	if ( count($parent) == 0 ) {
		api_tree_delete_node_content($tree_id, 0 );
	} else api_tree_delete_node_content( $parent, $sub_tree_id );
} else { // for sure it's on tree, root one
		api_tree_delete_node_content($tree_id, 0 );
}

// remove the truncate function ,so the table is still reflecting all ink discovered, and just updated
db_execute("truncate table plugin_linkdiscovery_hosts");
//db_execute("UPDATE plugin_linkdiscovery_hosts SET scanned='0'"); // clear the scanned field
db_execute("truncate table plugin_linkdiscovery_intf");

// Seed the relevant arrays with known information.
/* besoin des information suivante:
hostname
ip
interface avec un lien de source (seed) et de new host
*/
$sidx = read_config_option("linkdiscovery_CDP_deepness");
/* 
** Loop to the CDP, until we reach the deepness define
*/
linkdiscovery_debug("Initial Seed host: " . $known_hosts['hostname'] . "\n" );
linkdiscovery_save_host( $known_hosts['id'], $known_hosts );
$noscanhost = explode( ",", read_config_option('linkdiscovery_no_scan'));

// call the firest time the CDP discovery
CDP_Discovery($sidx, $known_hosts['hostname'] );

DisplayStack();


function DisplayStack(){
	global $hostdiscovered;
	linkdiscovery_debug(" Host stack: " );
	foreach( $hostdiscovered as $host)
		linkdiscovery_debug($host ." -> ");

	linkdiscovery_debug("\n");

}

// Try CDP.
//**********************
function CDP_Discovery($CDPdeep, $seedhost ) {
	global $cdpdevicename, $isSwitch, $isRouter, $isSRBridge, $isNexus, $isHost,  $keepwifi, $isWifi, $keepphone, $isPhone, $snmp_array, $hostdiscovered, $goodtogo, $noscanhost;
	
linkdiscovery_debug("\n\n\nPool host: " . $seedhost. " deep: ". $CDPdeep ."\n");

	// check if the host is disabled, or on the disable list
	$isDisabled = db_fetch_cell("SELECT disabled FROM host where description='". $seedhost ."' OR hostname='".$seedhost."'");
	if( $isDisabled == 'on') {
		return;
	}

	// check if the host is in the no scan list
	foreach($noscanhost as $nsh) {
		if( strcasecmp($nsh, $seedhost) == 0 ) {
			return;
		}
	}
	
	$isHostScanned = db_fetch_cell( "SELECT scanned FROM plugin_linkdiscovery_hosts where description='". $seedhost ."' OR hostname='".$seedhost ."'");
	if( $isHostScanned == '1' ){
linkdiscovery_debug( " hostname allready scanned: " . $seedhost . " scanned: ". $isHostScanned . " from: " . $hostdiscovered[count($hostdiscovered)-1]."\n");
		return;
	}
	
	// save seed hostname into the stack
	array_push($hostdiscovered, $seedhost );
	DisplayStack();

	// Look for the name of the devices connected
	$searchname = cacti_snmp_walk( $seedhost, $snmp_array['snmp_community'], $cdpdevicename, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
	// check if we where able to do an SNMP query
	if( $searchname ) {
		$snmp=true;
	}
	else {
		$snmp=false;
	}
	
	if( $snmp ) {
		// host is scanned now, otherwise we will do it again
		db_execute("UPDATE plugin_linkdiscovery_hosts SET scanned='1' WHERE hostname='" . $hostdiscovered[count($hostdiscovered)-1]."'" );

		// loop through the list to find out which are switch and/or router
		for( $nb=count($searchname)-1;$nb>=0;$nb-- ) 
		{
			$hostrecord_array = array();
			$hostipcapa = array();
			
			// get the IP and capa of the device
			$hostipcapa = hostgetipcapa( $hostdiscovered[count($hostdiscovered)-1], $searchname[$nb]['oid']);

			// what capacities we find on CDP
			$CDPcapacities = hexdec(preg_replace('/[^0-9A-D]/', '', $hostipcapa['capa']));
			
			$goodtogo = 0; // default value
			if( ($CDPcapacities & $isSwitch) ) {
					$goodtogo = $isSwitch;
			} else if( ($CDPcapacities & $isRouter)  ){
					$goodtogo = $isRouter;
			} else if( ($CDPcapacities & $isWifi) ) {
				if( $keepwifi=='on' )
					$goodtogo = $isWifi;
				else 
					$goodtogo = 0;
			} else if( ($CDPcapacities & $isPhone) ) {
				if( $keepphone=='on' )
					$goodtogo = $isPhone;
				else 
					$goodtogo = 0;
			} else $goodtogo = 0;
			
			if( $goodtogo != 0 ) {
				// extract the IP from the CDP packet
				$hostip = gethostip($hostipcapa['ip']);

				// resolve the hostname and description of the host find into CDP
				$hostrecord_array = resolvehostname($searchname[$nb], $hostip );
				$hostrecord_array['hostip'] = $hostip;
				$hostrecord_array['type'] = $hostipcapa['type'];
				
linkdiscovery_debug("\n  Find peer: " . $hostrecord_array['hostname']." - ".$hostrecord_array['description']. " nb: ". $nb ." capa: ".$hostipcapa['capa']." ip: ".$hostipcapa['ip']." good: ".$goodtogo ." on :" .$hostdiscovered[count($hostdiscovered)-1]. " max: ".count($searchname)."\n");

				// look for the snmp index of the interface, on the seedhost and on the discovered
				$canreaditfpeer = linkdiscovey_get_intf($searchname[$nb], $hostdiscovered[count($hostdiscovered)-1], $hostrecord_array);

				// save peerhost and interface
				linkdiscovery_save_data( $hostdiscovered[count($hostdiscovered)-1], $hostrecord_array, $canreaditfpeer,	$snmp_array );
	
				if (($CDPdeep-1 > 0) ){
					if( strcasecmp($hostdiscovered[count($hostdiscovered)-2],$hostrecord_array['hostname']) != 0  ) 
					{
						if( $goodtogo == $isWifi || $goodtogo == $isPhone ) {
							linkdiscovery_debug(" Dropped WA or Phone: ".$goodtogo." (".$isWifi . $isPhone.")\n");
						}
						else {
							// Get information on the new seed host
							$seedhost = $hostrecord_array['hostname'];
							CDP_Discovery( $CDPdeep-1, $seedhost );
						}
					} else {
//linkdiscovery_debug( "Same host prev: " . $hostdiscovered[count($hostdiscovered)-2] . " new:" . $hostrecord_array['hostname'] ."\n" );
					}
				}
			} else {
linkdiscovery_debug( " dropped hostname: " . strtolower($searchname[$nb]['value']) . " capa: " .$hostipcapa['capa']. " ip: " . $hostipcapa['ip'] . "\n");
			}
		} // end finded host, need to do thold
		
		$dbquery = db_fetch_assoc("SELECT id, hostname FROM host where hostname='" . $seedhost . "'" );
		$seedhostid = $dbquery[0]['id'];

//linkdiscovery_debug("End pool: ". (count($hostdiscovered)>1)?$hostdiscovered[(count($hostdiscovered)-1)]:'' ." back to " . (count($hostdiscovered)>1)?$hostdiscovered[count($hostdiscovered)-2]:'' ."(".count($hostdiscovered).")\n");
linkdiscovery_debug("End pool\n");

	} else linkdiscovery_debug( " Can't do snmp on hostname " . !empty($hostdiscovered)?$hostdiscovered[count($hostdiscovered)-1]:'' . " from: " . (count($hostdiscovered)>1)?$hostdiscovered[count($hostdiscovered)-2]:'' . "(".count($hostdiscovered).")\n");

	// remove the last host scanned
	array_pop($hostdiscovered);
	$seedhost = (count($hostdiscovered)>0)?$hostdiscovered[count($hostdiscovered)-1]:'';
	DisplayStack();
}

// get the ip and capa based on the OID of the name
//***********************
function hostgetipcapa( $seedhost, $hostoidindex ){
	global $snmp_array,$cdpdevicecapacities,$cdpdeviceip,$cdpdevicename,$cdpremotetype;
	
	$ret = array();
	$intfindex = substr( $hostoidindex, strlen($cdpdevicename)+1 );
	
	// Look for the capacities of the devices
	$searchcapa = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpdevicecapacities.".".$intfindex, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
//linkdiscovery_debug("hostgetipcapa1: ". $seedhost. " OID: " . $cdpdevicecapacities.".".$intfindex . " dump: " .$searchcapa. "\n");

	// look for the IP table 
	$searchip = ld_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpdeviceip.".".$intfindex, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
//linkdiscovery_debug("hostgetipcapa2: ". $seedhost. " OID: " . $cdpdeviceip.".".$intfindex ." ip: " .$searchip. "\n");

	// look for the equipement type
	$searchtype = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpremotetype.".".$intfindex, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

	$ret['ip'] = str_replace(":", " ", $searchip);
	$ret['capa'] = $searchcapa;
	$ret['type'] = $searchtype;

//linkdiscovery_debug("seed: ". $seedhost. " OID: " . $hostoidindex . " OID CAPA: ".$cdpdevicecapacities.".".$intfindex." capa: ".var_dump($searchcapa)." ip: ".var_dump($searchip). " type: ". $searchtype ."\n");

	return $ret;
}

// get the interface name/index on the seedhost, and index on the find host 
//**********************
function linkdiscovey_get_intf($hostrecord, $seedhost, $hostrecord_array){
	global $itfnamearray, $itfidxarray, $cdpinterfacename, $cdpremoteitfname, $snmpifdescr, $snmp_array, $goodtogo, $isWifi, $isPhone;

	$ret = false;

	$itfnamearray = array(); // interface array name of the: source, dest
	// look for the snmp index of the interface
	$itfidx = $hostrecord['oid']; // hostrecord contain oid and desthostname find on the CDP record
	$cdpsnmpitfidx = substr( substr( $itfidx, strlen($cdpinterfacename)+1 ), 0, strpos( substr( $itfidx, strlen($cdpinterfacename)+1),".") );

	// sub-index id
	$cdpsnmpsubitfidx = substr( $itfidx, strlen($cdpinterfacename.$cdpsnmpitfidx)+2 );
//linkdiscovery_debug("  Get interface seedhost: ".$seedhost." interface: ".$itfidx." hostrec: ".var_dump($hostrecord_array)."\n" );

	// interface array index of the : source, dest
	$itfidxarray['source'] = $cdpsnmpitfidx;
	$itfidxarray['dest'] = 0;

	// interface name, on the seedhost side
	$itfnamearray['source'] = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $snmpifdescr.".".$cdpsnmpitfidx, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

	$itfnamearray['dest'] = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpremoteitfname.".".$cdpsnmpitfidx.".".$cdpsnmpsubitfidx, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

	if( $goodtogo != $isWifi && $goodtogo != $isPhone ) 
	{
linkdiscovery_debug("snmp interface id for: ". $hostrecord_array['hostname'] ."\n");
		// Get intf index on the destination host, based on the name find on the seedhost
		$itfdstarray = cacti_snmp_walk( $hostrecord_array['hostname'], $snmp_array['snmp_community'], $snmpifdescr, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

		if( $itfdstarray ) {
			foreach( $itfdstarray as $itfdst ) {
				if( strcasecmp($itfdst['value'], $itfnamearray['dest']) == 0 ) {
					// find the right interface
					$itfoid = $itfdst['oid'];
					$itfidxarray['dest'] = substr( $itfoid, strlen($snmpifdescr)+1 );
					break 1;
				}
			}
			$ret = true;

//linkdiscovery_debug("  Find interface seedhost: ".$seedhost." interface: ".var_dump($itfidxarray)." indx: ".$cdpsnmpitfidx." sub-sub: ".$cdpsnmpsubitfidx."\n" );
		} else {
//linkdiscovery_debug("  snmp host " . $hostrecord_array['hostname'] . " Interface error can't read OID: ".$cdpinterfacename."\n");
			$ret = false;
		}
	} else {
linkdiscovery_debug("  snmp wifi  or phone no snmp for interface for: " . $hostrecord_array['hostname'] ."\n");
		$ret = false;
	}
	
	return $ret;
}

//**********************
function linkdiscovery_save_data( $seedhost, $hostrecord_array, $canpeeritf, $snmp_array ){
	global $itfnamearray, $itfidxarray, $monitor, $goodtogo, $isWifi, $isPhone, $update_hostname, $snmpserialno, $snmpsysdescr, $extenddb;

	// if it's a Wifi or a IP phone we save the host, and the link
	// check if the host does not exist, and we save
	// check if the host allready existe into cacti
	$dbquery = db_fetch_assoc("SELECT id, hostname, description FROM host where hostname='" . $hostrecord_array['hostname'] . "' OR description='" . $hostrecord_array['description'] . "'" );
//linkdiscovery_debug("   save peerhost: " .$hostrecord_array['hostname']." desc: ". $hostrecord_array['description']."\n" );

	if ( count($dbquery) == 0 ){
		// Save to cacti
		/*function api_device_save($id, $host_template_id, $description, $hostname, $snmp_community, $snmp_version,
        $snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled,
        $availability_method, $ping_method, $ping_port, $ping_timeout, $ping_retries,
        $notes, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids, $device_threads, $poller_id = 1, $site_id = 1, $external_id = '') {
*/
		// if it's a phone or Wifi don't use any template, and check only via ping
		if( $goodtogo == $isWifi || $goodtogo == $isPhone ) {
			$snmp_array["host_template_id"] 	= '0';
			$snmp_array["availability_method"]  = '3';
			$snmp_array["ping_method"]          = '1';
			$snmp_array["snmp_version"] 		= '0';
			if( $goodtogo == $isPhone ){
				$snmp_array["disable"]				= 'on';
			}
		}

		$new_hostid = api_device_save( '0', $snmp_array['host_template_id'], $hostrecord_array['description'], $hostrecord_array['hostname'], $snmp_array['snmp_community'], $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_port'], $snmp_array['snmp_timeout'], $snmp_array['disable'], $snmp_array['availability_method'], $snmp_array['ping_method'], $snmp_array['ping_port'], $snmp_array['ping_timeout'], $snmp_array['ping_retries'], $snmp_array['notes'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'], $snmp_array['snmp_engine_id'], $snmp_array['max_oids'], $snmp_array['device_threads'], 1, 0 );
linkdiscovery_debug("Host ".$hostrecord_array['description']." saved id ".$new_hostid. "\n");

		// do not monitor Wifi and Phone, and not emailing list
		if( $goodtogo == $isWifi || $goodtogo == $isPhone ) {
			db_execute("update host set monitor='' where id=" . $new_hostid );
			db_execute("update host set thold_send_email=0 where id=" . $new_hostid );
		} else {
			// get host template id based on OS defined on automation
			// take info from profile based on OS returned from automation_find_os($sysDescr, $sysObject, $sysName)()
			$snmp_sysDescr = cacti_snmp_get( $hostrecord_array['hostname'], $snmp_array['snmp_community'], $snmpsysdescr, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
linkdiscovery_debug("host snmp ".$snmp_sysDescr );
			
			$host_template = automation_find_os($snmp_sysDescr, '', '');
			$snmp_array["host_template_id"] = $host_template['host_template'];
linkdiscovery_debug(" template ".$snmp_array["host_template_id"]."\n" );
			db_execute("update host set host_template_id='".$snmp_array['host_template_id']."' where id=" . $new_hostid );
		}

		if($new_hostid == 0) {
			linkdiscovery_debug("   api Save error: ".$new_hostid." host: ".$hostrecord_array['description'] . $hostrecord_array['hostname']."\n");
			return;
		} 
		
		if ($monitor == 'on') {
			db_execute("update host set monitor='on' where id=" . $new_hostid );
		}

		// Set new host to discovery tree
		linkdiscovery_add_tree ($new_hostid);
		// graph the CPU
		if( $goodtogo != $isWifi && $goodtogo != $isPhone ) {
			linkdiscovery_graph_cpu($new_hostid, $snmp_array);
		}
	} else {
		$new_hostid = $dbquery[0]['id'];
		if ( $update_hostname ) {
			db_execute("update host set hostname='". $hostrecord_array['hostname'] . "' where id=" . $new_hostid );
		}
	}
	// save the type and serial number to the new host's record
	if( $extenddb && !empty($hostrecord_array['hostname']) ) {
		// get the serial number and type, not for wifi or phone
		if( $goodtogo != $isWifi && $goodtogo != $isPhone && !empty($hostrecord_array['hostname']) ) {
			$type = trim( substr($hostrecord_array['type'], strpos( $hostrecord_array['type'], "cisco" )+strlen("cisco")+1 ) );
			db_execute("update host set type='".$type. "' where id=" . $new_hostid );
			
			$serialno = cacti_snmp_get( $hostrecord_array['hostname'], $snmp_array['snmp_community'], $snmpserialno, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] );
				
			if( !empty( $serialno) ) {
				db_execute("update host set serial_no='".$serialno. "' where id=" . $new_hostid );
			}
		} else if( $goodtogo == $isPhone && !empty($hostrecord_array['hostname']) ) { 
			// set the flag isPhone
			db_execute("update host set isPhone='on' where id=" . $new_hostid );

		// get Phone number
		linkdiscovery_debug(" parse device: ".$hostrecord_array['hostname']."\n");
			$phonenumbers = array();
			$number = array();
			$tagname = array( "téléphone", "dn" ); //Numéro de téléphone, NR téléphone, Phone n DN
			$phonenumbers = get_page( $hostrecord_array['hostname'], $tagname );
			if( !empty($phonenumbers) ) {
				foreach($phonenumbers as $phonenumber) {
					$num_array = explode( " ", $phonenumber);
					$tmpnumber = str_ireplace( $tagname, "", $num_array[count($num_array)-1] );
					if( count(explode(" ", $tmpnumber)) > 1 ) {
						$tmp = explode( " ", $tmpnumber);
						if( is_numeric( end($tmp) ) ) {
							$number[] = end($tmp);
						}
					} else {
						if( is_numeric($tmpnumber) ) {
							$number[] = $tmpnumber;
						}
					}
				}
				$numbers = implode( ",\n", $number );
				linkdiscovery_debug(" numbers: ".$numbers."\n");
				db_execute("update host set notes='". $numbers . "' where id=" . $new_hostid );
			}
			
			// get the serial number
			$number = null;
			$tagname = array( "série", "serial number" ); //Serial Number, Numéro de série
			$serialnumbers = get_page( $hostrecord_array['hostname'], $tagname );
			if( !empty($serialnumbers) ) {
				foreach($serialnumbers as $serialnumber) {
					$num_array = explode( " ", $serialnumber);
					$number[] = str_ireplace($tagname, "", $num_array[count($num_array)-1] );
				}
				$numbers = implode( ",\n", $number );
				linkdiscovery_debug(" ser numbers: ".$numbers."\n");
				db_execute("update host set serial_no='". $numbers . "' where id=" . $new_hostid );
			}
			
			// get the model number
			$number = null;
			$tagname = array( "modèle", "Product ID", "Model Number" ); // product ID
			$modeles = get_page( $hostrecord_array['hostname'], $tagname );
			if( !empty($modeles) ) {
				foreach($modeles as $modele) {
					$num_array = explode(" ",$modele);
					$number[] = str_ireplace($tagname, "", $num_array[count($num_array)-1] );
				}
				$numbers = implode( ",\n", $number );
				linkdiscovery_debug(" model numbers: ".$numbers."\n");
				db_execute("update host set type='". $numbers . "' where id=" . $new_hostid );
			}
			
			// get the site_id based on the seedhost
			$dbquery = db_fetch_cell("SELECT site_id FROM host where description='". $seedhost ."' OR hostname='".$seedhost. "'" );
			linkdiscovery_debug(" site_id1: ".$dbquery."\n");
			if( !empty($dbquery) ) {
				db_execute("update host set site_id=". $dbquery . " where id=" . $new_hostid );
			}

		} else if( $goodtogo == $isWifi && !empty($hostrecord_array['hostname']) ) { // Get the WA information
			db_execute("update host set type='".str_replace("cisco", "", $hostrecord_array['type']). "' where id=" . $new_hostid );

			// get the site_id based on the seedhost
			$dbquery = db_fetch_cell("SELECT site_id FROM host where description='". $seedhost ."' OR hostname='".$seedhost. "'" );
			linkdiscovery_debug(" site_id2: ".$dbquery."\n");
			if( !empty($dbquery) ) {
				db_execute("update host set site_id=". $dbquery . " where id=" . $new_hostid );
			}
		}

	}

	linkdiscovery_save_host( $new_hostid, $hostrecord_array );
	
	// save the source host
	$dbquery = db_fetch_assoc("SELECT id, hostname FROM host where description='". $seedhost ."' OR hostname='".$seedhost. "'" );
	$seedhostid = $dbquery[0]['id'];
//linkdiscovery_debug("   host_src: ".$seedhostid." itf_src: ".$itfidxarray['source']." -> host_dst:".$new_hostid." itf_dst: ".$itfidxarray['dest']."\n" );

	// save interface information
/*	if ( $canpeeritf && $goodtogo != $isWifi && $goodtogo != $isPhone ) 
	{*/
		db_execute("REPLACE INTO plugin_linkdiscovery_intf (host_id_src, host_id_dst, snmp_index_src, snmp_index_dst ) 
				VALUES ("
		. $seedhostid . ", "
		. $new_hostid . ", "
		. $itfidxarray['source'] . ", "
		. $itfidxarray['dest'] . " )");
//	}

	// and create the needed graphs, except for Phone
	if( $goodtogo != $isPhone ) {
		linkdiscovery_create_graphs($new_hostid, $seedhostid, $itfidxarray['source'], $snmp_array );
	}
}

//**********************
// save host on the linkdiscovery table
function linkdiscovery_save_host( $hostid, $hostrecord_array, $scanned='0' ){
	global $snmp_array;
	
	$hostexist = db_fetch_cell("SELECT id from plugin_linkdiscovery_hosts WHERE hostname='".$hostrecord_array['hostname']."' OR description='".$hostrecord_array['description']."'");
	if( $hostexist == 0 ) {
		// save it to the discovery table for later use
		$ret = db_execute("INSERT INTO plugin_linkdiscovery_hosts (id, host_template_id, description, hostname, community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, scanned ) 
				VALUES ('"
		. $hostid . "', '"
		. $snmp_array['host_template_id'] . "', '"
		. $hostrecord_array['description'] . "', '"
		. $hostrecord_array['hostname'] . "', '"
		. $snmp_array['snmp_community'] . "', '"
		. $snmp_array['snmp_version'] . "', '"
		. $snmp_array['snmp_username'] . "', '"
		. $snmp_array['snmp_password'] . "', '"
		. $snmp_array['snmp_auth_protocol'] . "', '"
		. $snmp_array['snmp_priv_passphrase'] . "', '"
		. $snmp_array['snmp_priv_protocol'] . "', '"
		. $snmp_array['snmp_context'] . "', '"
		. $scanned . "')");

		linkdiscovery_debug("Saved Host, descr: " . $hostrecord_array['description'] ." hostname: " . $hostrecord_array['hostname']." res: ".$ret."\n" );
	} else {
		linkdiscovery_debug(" host exist ". $hostrecord_array['hostname']." id: ".$hostid."\n");
	}
}

//**********************
function linkdiscovery_graph_cpu( $new_hostid, $snmp_array ){
	// graph the CPU if requested, on the new host, and don't do it twice
	//
	$cpu_graph_template = read_config_option("linkdiscovery_CPU_graph");
	if( $cpu_graph_template == 'on' ) {
		$cpu_graph_template_id = linkdiscovery_get_cpu_graph( $snmp_array['host_template_id'] );
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=".$cpu_graph_template_id." AND host_id=".$new_hostid);

		automation_execute_graph_template( $new_hostid, $cpu_graph_template_id);
//linkdiscovery_debug("   Created CPU graph: " . get_graph_title($return_array["local_graph_id"])." hostid: ".$new_hostid ."\n");
	} else {
//linkdiscovery_debug("   Graph CPU exist: " .$new_hostid . " id: " .$cpu_graph_template." exist: ".$existsAlready."\n" );
	}
	
}

//**********************
function linkdiscovery_create_graphs( $new_hostid, $seedhostid, $src_intf, $snmp_array ) {
	global $snmp_status_query_graph, $snmp_traffic_query_graph, $snmp_packets_query_graph, $thold_traffic_graph_template, $thold_status_graph_template;
/* create_complete_graph_from_template - creates a graph and all necessary data sources based on a
        graph template
   @arg $graph_template_id - the id of the graph template that will be used to create the new
        graph
   @arg $host_id - the id of the host to associate the new graph and data sources with
   @arg $snmp_query_array - if the new data sources are to be based on a data query, specify the
        necessary data query information here. it must contain the following information:
          $snmp_query_array["snmp_query_id"]
          $snmp_query_array["snmp_index_on"]
          $snmp_query_array["snmp_query_graph_id"]
          $snmp_query_array["snmp_index"]
   @arg $suggested_values_array - any additional information to be included in the new graphs or
        data sources must be included in the array. data is to be included in the following format:
          $values["cg"][graph_template_id]["graph_template"][field_name] = $value  // graph template
          $values["cg"][graph_template_id]["graph_template_item"][graph_template_item_id][field_name] = $value  
		  // graph template item
          $values["cg"][data_template_id]["data_template"][field_name] = $value  // data template
          $values["cg"][data_template_id]["data_template_item"][data_template_item_id][field_name] = $value  // data template item
          $values["sg"][data_query_id][graph_template_id]["graph_template"][field_name] = $value  // graph template (w/ data query)
          $values["sg"][data_query_id][graph_template_id]["graph_template_item"][graph_template_item_id][field_name] = $value  
		  // graph template item (w/ data query)
          $values["sg"][data_query_id][data_template_id]["data_template"][field_name] = $value  // data template (w/ data query)
          $values["sg"][data_query_id][data_template_id]["data_template_item"][data_template_item_id][field_name] = $value  
		  // data template item (w/ data query)
function create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, &$suggested_values_array) {
*/

	// should we do a graph for traffic
		$snmp_traffic_query_graph_id = linkdiscovery_get_graph_template( $snmp_array['host_template_id'], 'traffic');
	if( $snmp_traffic_query_graph == 'on' ) {
		
		$return_array = array();
		$traffic_graph_template_id = db_fetch_cell("SELECT graph_template_id FROM snmp_query_graph WHERE id=".$snmp_traffic_query_graph_id);
		$snmp_query_id = db_fetch_cell("SELECT snmp_query_id FROM snmp_query_graph WHERE id=".$snmp_traffic_query_graph_id );
	
		// take interface to be monitored, on the new host
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=".$traffic_graph_template_id." AND host_id=".$seedhostid ." AND snmp_query_id=".$snmp_query_id ." AND snmp_index=".$src_intf);

		if( $existsAlready == 0 ) {
			$empty=array();
			$snmp_query_array["snmp_query_id"] = $snmp_query_id;
			$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($seedhostid, $snmp_query_id);
			$snmp_query_array["snmp_query_graph_id"] = $snmp_traffic_query_graph_id;
			$snmp_query_array["snmp_index"] = $src_intf;
			$return_array = create_complete_graph_from_template( $traffic_graph_template_id, $seedhostid, $snmp_query_array, $empty);

//			automation_execute_graph_template( $seedhostid, $traffic_graph_template_id);
			// Create the Threshold
			if( $thold_traffic_graph_template > 0 )
				thold_graphs_create($thold_traffic_graph_template, $return_array['local_graph_id']);

linkdiscovery_debug("   Created traffic graph: " .$src_intf." - ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph traffic exist: " .$seedhostid . " id: " .$traffic_graph_template_id." exist: ".$existsAlready."\n" );
			// Create the Threshold
			if( $thold_traffic_graph_template > 0 )
				thold_graphs_create($thold_traffic_graph_template, $existsAlready);
		}
	}

	// should we do a graph for NonUnicast packet
	// snmp_packets_query_graph_id=39
	// packets_graph_template_id=46
	// snmp_query_id=10
	if( $snmp_packets_query_graph == 'on' ) {
		$snmp_packets_query_graph_id = linkdiscovery_get_graph_template( $snmp_array['host_template_id'], 'Packets');
		$return_array = array();
		$packets_graph_template_id = db_fetch_cell("SELECT graph_template_id FROM snmp_query_graph WHERE id=".$snmp_packets_query_graph_id);
		$snmp_query_id = db_fetch_cell("SELECT snmp_query_id FROM snmp_query_graph WHERE id=".$snmp_packets_query_graph_id );
	
		// take interface to be monitored, on the new host
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=".$packets_graph_template_id." AND host_id=".$seedhostid ." AND snmp_query_id=".$snmp_query_id ." AND snmp_index=".$src_intf);

		if( $existsAlready == 0 ) {
			$empty=array();
			$snmp_query_array["snmp_query_id"] = $snmp_query_id;
			$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($seedhostid, $snmp_query_id);
			$snmp_query_array["snmp_query_graph_id"] = $snmp_packets_query_graph_id;
			$snmp_query_array["snmp_index"] = $src_intf;
			$return_array = create_complete_graph_from_template( $packets_graph_template_id, $seedhostid, $snmp_query_array, $empty);

linkdiscovery_debug("   Created packets graph: " .$src_intf." - ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph packets exist: " .$seedhostid . " id: " .$packets_graph_template_id." exist: ".$existsAlready."\n" );
		}
	}

	// should we do a graph for the status
	if( $snmp_status_query_graph == 'on' ) {
		$snmp_status_query_graph_id = linkdiscovery_get_graph_template( $snmp_array['host_template_id'], 'status');
		
		$return_array = array();
		$status_graph_template_id = db_fetch_cell("SELECT graph_template_id FROM snmp_query_graph WHERE id=".$snmp_status_query_graph_id);
		$snmp_query_id = db_fetch_cell("SELECT snmp_query_id FROM snmp_query_graph WHERE id=".$snmp_status_query_graph_id );
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=".$status_graph_template_id." AND host_id=".$seedhostid ." AND snmp_query_id=".$snmp_query_id ." AND snmp_index=".$src_intf);

		if( $existsAlready == 0 ) {
			$empty=array();
			$snmp_query_array["snmp_query_id"] = $snmp_query_id;
			$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($seedhostid, $snmp_query_id);
			$snmp_query_array["snmp_query_graph_id"] = $snmp_status_query_graph_id;
			$snmp_query_array["snmp_index"] = $src_intf;
			$return_array = create_complete_graph_from_template( $status_graph_template_id, $seedhostid, $snmp_query_array, $empty);

//			automation_execute_graph_template( $seedhostid, $status_graph_template_id);

			// Create the Threshold 
			if( $thold_status_graph_template > 0 )
				thold_graphs_create($thold_status_graph_template, $return_array['local_graph_id']);

linkdiscovery_debug("   Created status graph: " .$src_intf." - ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph status exist: " .$seedhostid . " id: " .$status_graph_template_id." exist: ".$existsAlready."\n" );
			// Create the Threshold
			if( $thold_status_graph_template > 0 )
				thold_graphs_create($thold_status_graph_template, $existsAlready);
		}

	}

	/* lastly push host-specific information to our data sources */
	push_out_host($seedhostid,0);
}

function linkdiscovery_add_tree ($host_id) {
/** api_tree_item_save - saves the tree object and then resorts the tree
0 * @arg $id - the leaf_id for the object
$tree_id * @arg $tree_id - the tree id for the object
3     * @arg $type - the item type graph, host, leaf
$parent * @arg $parent_tree_item_id - The parent leaf for the object
''    * @arg $title - The leaf title in the case of a leaf
0     * @arg $local_graph_id - The graph id in the case of a graph
$host_id     * @arg $host_id - The host id in the case of a graph
1     * @arg $host_grouping_type - The sort order for the host under expanded hosts
1     * @arg $sort_children - The sort type in the case of a leaf
false * @arg $propagate_changes - Wether the changes should be cascaded through all 
children
 * @returns - boolean true or false depending on the outcome of the operation *
*/

	$tree_id = read_config_option("linkdiscovery_tree"); // sous graph_tree_itesm c'est la valeur graph_tree_id
	$tmp_sub_tree_id = read_config_option("linkdiscovery_sub_tree");
	$sub_tree_id = empty($tmp_sub_tree_id)?'0':$tmp_sub_tree_id;
	
	// if the sub_tree_id is on graph_tree_items, that mean we have a parent 
	$parent = db_fetch_row('SELECT parent FROM graph_tree_items WHERE graph_tree_id = ' . $tree_id.' AND host_id=0 AND local_graph_id=0 AND id=' .$sub_tree_id );
	if( !empty($parent) ) {
		api_tree_item_save(0, $tree_id, 3, $sub_tree_id, '', 0, $host_id, 1, 1, false);
	} else {
		// just save under the graph_tree_item, but with sub_tree_id as 0
		api_tree_item_save(0, $tree_id, 3, 0, '', 0, $host_id, 1, 1, false);
	}
}

function gethostip( $hostrecord ){
// hex value: 0A 55 00 0B -> 10 85 00 11
	$ip = explode( " ", $hostrecord );
	if( count($ip) == 4 ) {
		$ip[0] = hexdec($ip[0]);
		$ip[1] = hexdec($ip[1]);
		$ip[2] = hexdec($ip[2]);
		$ip[3] = hexdec($ip[3]);
		$ipadr = implode(".", $ip);
	} else $ipadr = false;

	return $ipadr;
}

function resolvehostname( $hostrecord, $hostip ) {
	global $domain_name, $use_ip_hostname, $use_fqdn_description;
	// just remove the string after the parenthesis, can find at the end of some CDP host
	$removeparenthesis = preg_split('/[(]+/', strtolower($hostrecord['value']), -1, PREG_SPLIT_NO_EMPTY);
	$fqdnname = $removeparenthesis[0];
	$hostnamearray = preg_split('/[\.]+/', strtolower($fqdnname), -1, PREG_SPLIT_NO_EMPTY);
	$hostname = $hostnamearray[0];
	$hostdescription = $hostnamearray[0];
	$hostrecord_array = array();

		// check if need to resolve the name to put the IP into the hostname
		if( $use_ip_hostname ) {
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
			if (!$dnsquery) { // check if it work as supplied, if not add the define domain 
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
				if ( !$dnsquery) { // check if it work with new hostname and domain, if not just use ip find into CDP 
					$hostname = ($hostip == false )?strtolower($fqdnname):$hostip;
				} else $hostname = $dnsquery[0]['ip'];
			} else $hostname = $dnsquery[0]['ip'];
		} else {
			// chek if the hostname receive from CDP is FQDN ortherwise add domain
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
			if ( $dnsquery) { // check if it work with 
				$hostname = strtolower($fqdnname);
			} else {
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
				if( $dnsquery ){
					$hostname = strtolower($fqdnname);
				} else { // if not try to resolve IP
					$hostname = ($hostip == false )?strtolower($fqdnname):gethostbyaddr($hostip);
				}
			} 
		}
				
		// check if we use the FQDN for description
		if( $use_fqdn_description ) {
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
			if ( $dnsquery) { // check if it work with FQDN provided from CDP
				$hostdescription = strtolower($fqdnname);
			} else {
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
				if( $dnsquery ){
					$hostdescription = strtolower($fqdnname);
				} else $hostdescription = ($hostip == false )?strtolower($fqdnname):$hostip;
			}
		}

	$hostrecord_array['hostname'] = $hostname;
	$hostrecord_array['description'] = $hostdescription;

	return $hostrecord_array;
}

function is_ip($address) {
	if(is_ipv4($address) || is_ipv6($address)) {
		return TRUE;
	} else return FALSE;
}

function is_ipv4($address) {
	if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === FALSE) {
		return FALSE;
	}else{
		return TRUE;
	}
}

function is_ipv6($address) {
	if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === FALSE) {
		// Check for SNMP specification and brackets
		if(preg_match("/udp6:\[(.*)\]/", $address, $matches) > 0 &&
			filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE) {
			return TRUE;
		}
		return FALSE;
	}else{
		return TRUE;
	}
}

function linkdiscovery_recreate_tables () {
linkdiscovery_debug("Request received to recreate the LinkDiscovery Plugin's tables\n");
linkdiscovery_debug("   Dropping the tables\n");
	db_execute("drop table plugin_linkdiscovery_hosts");
	db_execute("drop table plugin_linkdiscovery_intf");

linkdiscovery_debug("Creating the tables\n");
	linkdiscovery_setup_table ();
}

function linkdiscovery_debug($text) {
	global $debug;
	if ($debug)	print $text;
	if ($debug) cacti_log($text, false, "LINKDISCOVERY" );
	flush();
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Link Discovery v".read_config_option("plugin_linkdiscovery_version").", Copyright 2017 - Arno Streuli\n\n";
	print "usage: findhosts.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-f	    - Force the execution of a Link Discovery process\n";
	print "-d	    - Display verbose output during execution\n";
	print "-r	    - Drop and Recreate the Link Discovery Plugin's tables before running\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}

function thold_graphs_create($template_id, $graph) {
	global $config;

	$message = "";

	$template = db_fetch_row("SELECT * FROM thold_template WHERE id=" . $template_id);

	$temp = db_fetch_row("SELECT dtr.*
			FROM data_template_rrd AS dtr
			 LEFT JOIN graph_templates_item AS gti
			 ON gti.task_item_id=dtr.id
			 LEFT JOIN graph_local AS gl
			 ON gl.id=gti.local_graph_id
			 WHERE gl.id=$graph");
	$data_template_id = $temp['data_template_id'];
	$local_data_id = $temp['local_data_id'];

	$data_source      = db_fetch_row("SELECT * FROM data_local WHERE id=" . $local_data_id);
	$data_template_id = $data_source['data_template_id'];
	$existing         = db_fetch_assoc('SELECT id FROM thold_data WHERE local_data_id=' . $local_data_id . ' AND data_template_rrd_id=' . $data_template_id);

	if (count($existing) == 0 && count($template)) {
		if ($graph) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd
				WHERE local_data_id=$local_data_id
				ORDER BY id
				LIMIT 1");

			$grapharr = db_fetch_row("SELECT graph_template_id
				FROM graph_templates_item
				WHERE task_item_id=$rrdlookup
				AND local_graph_id=$graph");

			$data_source_name = $template['data_source_name'];

			$insert = array();

			$name = thold_format_name($template, $graph, $local_data_id, $data_source_name);

			$insert['name']               = $name;
			$insert['host_id']            = $data_source['host_id'];
			$insert['local_data_id']             = $local_data_id;
			$insert['local_graph_id']           = $graph;
			$insert['data_template_id']      = $data_template_id;
			$insert['graph_template_id']     = $grapharr['graph_template_id'];
			$insert['thold_hi']           = $template['thold_hi'];
			$insert['thold_low']          = $template['thold_low'];
			$insert['thold_fail_trigger'] = $template['thold_fail_trigger'];
			$insert['thold_enabled']      = $template['thold_enabled'];
			$insert['bl_ref_time_range']  = $template['bl_ref_time_range'];
			$insert['bl_pct_down']        = $template['bl_pct_down'];
			$insert['bl_pct_up']          = $template['bl_pct_up'];
			$insert['bl_fail_trigger']    = $template['bl_fail_trigger'];
			$insert['bl_alert']           = $template['bl_alert'];
			$insert['repeat_alert']       = $template['repeat_alert'];
			$insert['notify_extra']       = $template['notify_extra'];
			$insert['cdef']               = $template['cdef'];
			$insert['thold_template_id']           = $template['id'];
			$insert['template_enabled']   = 'on';

			$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id FROM data_template_rrd where local_data_id='$local_data_id' and data_source_name='$data_source_name'");

			$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');
			foreach ($rrdlist as $rrdrow) {
				$data_rrd_id=$rrdrow['id'];
				$insert['data_template_rrd_id'] = $data_rrd_id;
				$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE local_data_id='$local_data_id' AND data_template_rrd_id='$data_rrd_id'");
				if (count($existing) == 0) {
					$insert['id'] = 0;
					$id = sql_save($insert, 'thold_data');
					if ($id) {
						thold_template_update_threshold ($id, $insert['thold_template_id']);

						$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id");
						$tname = $l[0]['name'];

						$name = $data_source_name;
						if ($rrdrow['data_input_field_id'] != 0) {
							$l = db_fetch_assoc('SELECT name FROM data_input_fields where id=' . $rrdrow['data_input_field_id']);
							$name = $l[0]['name'];
						}
						plugin_thold_log_changes($id, 'created', " $tname [$name]");
						$message .= "Created threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<br>";
					}
				}
			}
		}
	}
		

	if (strlen($message)) {
		$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	}else{
		$_SESSION['thold_message'] = "<font size=-2>Threshold(s) Already Exist - No Thresholds Created</font>";
	}
}

?>

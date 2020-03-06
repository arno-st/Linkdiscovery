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
include_once($config['base_path'] . '/lib/poller.php');

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
$cdpremotetype		 = ".1.3.6.1.4.1.9.9.23.1.2.1.1.8"; // platforme
$cdpdevicecapacities = ".1.3.6.1.4.1.9.9.23.1.2.1.1.9";

// LLDP info
$lldpShortLocPortId  = ".1.0.8802.1.1.2.1.3.7.1.3";
$lldpLongLocPortId 	 = ".1.0.8802.1.1.2.1.3.7.1.4";

$lldpRemoteSystemsData = ".1.0.8802.1.1.2.1.4";
$lldpRemTable 		 = ".1.0.8802.1.1.2.1.4.1";
$lldpRemEntry 		 = ".1.0.8802.1.1.2.1.4.1.1";

$lldpremoteserialnum = ".1.0.8802.1.1.2.1.5.4795.1.3.3.1.4.0"; //.5.4 .3.5 // phone serial number
$lldpremotemodel     = ".1.0.8802.1.1.2.1.5.4795.1.3.3.1.6.0"; //.5.4 .3.5 // phone model

$lldpremotechassitypeid  = ".1.0.8802.1.1.2.1.4.1.1.4.0"; //iso.0.8802.1.1.2.1.4.1.1.4.0.26.25 = INTEGER: 4
$lldpremotechassisid  	 = ".1.0.8802.1.1.2.1.4.1.1.5.0"; //iso.0.8802.1.1.2.1.4.1.1.5.0.26.25 = Hex-STRING: EC 1D 8B E2 82 00
/* 
lldpremchassitypeid define what is the lldpremchassisid
	chassisComponent(1),
    interfaceAlias(2),
    portComponent(3),
    macAddress(4),
    networkAddress(5),
    interfaceName(6),
    local(7)
*/
$lldpremotefsubtype	 = ".1.0.8802.1.1.2.1.4.1.1.6.0"; //iso.0.8802.1.1.2.1.4.1.1.6.0.26.11 = INTEGER: 5 switch, 7 phone
$lldpremotefname     = ".1.0.8802.1.1.2.1.4.1.1.7.0"; //iso.0.8802.1.1.2.1.4.1.1.7.0.26.25 = STRING: "Gi0/16"
$lldpremotefalias    = ".1.0.8802.1.1.2.1.4.1.1.8.0"; //iso.0.8802.1.1.2.1.4.1.1.8.0.26.25 = STRING: "SE-SE46-174 / WS-C2960S-24PS-L / Gi1/0/26"
$lldpremotesysname   = ".1.0.8802.1.1.2.1.4.1.1.9.0"; //iso.0.8802.1.1.2.1.4.1.1.9.0.26.25 = STRING: "SE-SE46-8502.recolte.lausanne.ch"
$lldpremotesysdesc   = ".1.0.8802.1.1.2.1.4.1.1.10.0"; //iso.0.8802.1.1.2.1.4.1.1.10.0.26.25 = STRING: "Cisco IOS Software, C3560CX Software (C3560CX-UNIVERSALK9-M), V etc
$lldpremotesyscapa   = ".1.0.8802.1.1.2.1.4.1.1.11.0"; //iso.0.8802.1.1.2.1.4.1.1.11.0.26.25 = Hex-STRING: 28 00
$lldpremotenablecapa = ".1.0.8802.1.1.2.1.4.1.1.12.0"; //iso.0.8802.1.1.2.1.4.1.1.12.0.26.25 = Hex-STRING: 20 00
/* system capabilities
Bit 'other(0)' indicates that the system has capabilities other than those listed below.
Bit 'repeater(1)' indicates that the system has repeater capability.
Bit 'bridge(2)' indicates that the system has bridge capability.
Bit 'wlanAccessPoint(3)' indicates that the system has WLAN access point capability.
Bit 'router(4)' indicates that the system has router capability.
Bit 'telephone(5)' indicates that the system has telephone capability.
Bit 'docsisCableDevice(6)' indicates that the system has DOCSIS Cable Device capability (IETF RFC 2669 & 2670).
Bit 'stationOnly(7)' indicates that the system has only station capability and nothing else.``
20: which stand for 0x2 and 0x0 bridge, AP
24: which stand for 0x2 and 0x4 bridge and phone
28: L3 device
*/

$snmpifdescr		 = ".1.3.6.1.2.1.2.2.1.2";
$snmpiftype		 	 = ".1.3.6.1.2.1.2.2.1.3"; // interface type
$snmpsysname		 = ".1.3.6.1.2.1.1.5.0"; // system name
$snmpsysdescr		 = ".1.3.6.1.2.1.1.1.0"; // system description
$snmpserialno		 = ".1.3.6.1.2.1.47.1.1.1.1.11.1001";
$snmpsysobjid		 = ".1.3.6.1.2.1.1.2.0";

$intftypeeth = 6; // ethernetCsmacd(6)
$intftypetunnel = 131; // tunnel(131)
$isRouter = 0x01;
$isSRBridge = 0x04;
$isSwitch = 0x08;
$isSwitch2 = 0x40;
$isHost = 0x10;
$isNexus = 0x200;
$isWifi = 0x02; // 2      000010
$isPhone = 0x80; //0x90 et équivalent isHost; // 144 10010000 

$known_hosts = '';
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
	case "-s": // seed host
		linkdiscovery_debug("Force Seedhost: ".$value."\n" );
		// Get information on the seed host
		$dbquery = db_fetch_assoc("SELECT id, host_template_id, description, hostname, snmp_community, snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id, max_oids, device_threads FROM host where host.hostname='" . $value."'");

		$known_hosts = $dbquery[0];
		linkdiscovery_debug("Force Seedhost: (".$value.") ".$known_hosts['hostname']."\n" );
		break;

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

// check if findhost is allready running
$runningfile = $config['base_path'] . '/plugins/linkdiscovery'."/findhost-running";
if ( file_exists( $runningfile ) ) {
	linkdiscovery_debug("Findhost is running.\n");
	exit;
} else {
	touch( $runningfile );
}


if (read_config_option("linkdiscovery_collection_timing") == "disabled") {
	linkdiscovery_debug("Link Discovery Polling is set to disabled.\n");
	if(!isset($debug)) {
		unlink( $runningfile ) or die("Couldn't delete file: ".$runningfile);
		exit;
	}
}

linkdiscovery_debug("Checking to determine if it's time to run.\n");
$poller_interval = read_config_option("poller_interval");

$seconds_offset = read_config_option("linkdiscovery_collection_timing") * 60;

if ($forcerun == FALSE && $seconds_offset == 0) {
	linkdiscovery_debug("FALSE not the right time\n");
	unlink( $runningfile ) or die("Couldn't delete file: ".$runningfile);
	exit;
}


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

if ($time_till_next_run < 0 ) {
	linkdiscovery_debug("The next run time has been determined to be NOW\n");
}else{
	linkdiscovery_debug("The next run time has been determined to be at   " . date("Y-m-d G:i:s", $next_run_time) . "\n");
}

if ($time_till_next_run > 0 && $forcerun == FALSE ) {
	linkdiscovery_debug("not the right time\n");
	unlink( $runningfile ) or die("Couldn't delete file: ".$runningfile);
	exit;
}

// check if findhost is allready running
if ($forcerun) {
	linkdiscovery_debug("Scanning has been forced\n");
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

// add the traffic graphs from the old host, to the new host
$snmp_traffic_query_graph_id = read_config_option("linkdiscovery_traffic_graph");
// add the nu graphs from the old host, to the new host
$snmp_packets_query_graph_id = read_config_option("linkdiscovery_packets_graph");
// add the error graphs from the old host, to the new host
$snmp_errors_query_graph_id = read_config_option("linkdiscovery_errors_graph");
// add the status graphs, from the new host
$snmp_status_query_graph_id = read_config_option("linkdiscovery_status_graph");

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

if ( $known_hosts=='') {
	// Get information on the seed known host
	$dbquery = db_fetch_row("SELECT id, host_template_id, description, hostname, snmp_community, snmp_version,
	snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, ping_method, ping_port, 
	ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, 
	snmp_engine_id, max_oids, device_threads 
	FROM host 
	WHERE host.id=" . read_config_option("linkdiscovery_seed"));

	$known_hosts = array();
	$known_hosts = $dbquery;
}

if (!is_array($known_hosts)) {
	linkdiscovery_debug("Link Discovery failed to pull seed hosts? Exiting.");
	unlink( $runningfile ) or die("Couldn't delete file: ".$runningfile);
	exit;
}

$snmp_array = array(
"host_template_id"	   => '5', // Cisco router as default
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
	$parent = db_fetch_row('SELECT parent FROM graph_tree_items 
	WHERE graph_tree_id = ' . $tree_id. ' AND host_id=0 AND id='.$sub_tree_id);
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

// call the first time the CDP discovery
CDP_Discovery($sidx, $known_hosts['hostname'] );

// End of process
linkdiscovery_debug("\n\n\nEnd of process\n\n\n" );
unlink( $runningfile ) or die("Couldn't delete file: ".$runningfile);

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
	global $cdpdevicename, $isSwitch, $isSwitch2, $isRouter, $isSRBridge, $isNexus, $isHost,  
	$keepwifi, $isWifi, $keepphone, $isPhone, $snmp_array, $hostdiscovered, $goodtogo, $noscanhost;
	
linkdiscovery_debug("\n\n\nPool host: " . $seedhost. " deep: ". $CDPdeep ."\n");

	// check if the host is disabled, or on the disable list
	$isDisabled = db_fetch_cell("SELECT disabled FROM host WHERE description='". $seedhost ."' 
	OR hostname='".$seedhost."'");
	if( $isDisabled == 'on') {
		return;
	}

	// check if the host is in the no scan list
	foreach($noscanhost as $nsh) {
		if( strcasecmp($nsh, $seedhost) == 0 ) {
			return;
		}
	}
	
	$isHostScanned = db_fetch_cell( "SELECT scanned FROM plugin_linkdiscovery_hosts 
	WHERE description='". $seedhost ."' OR hostname='".$seedhost ."'");
	if( $isHostScanned == '1' ){
linkdiscovery_debug( " hostname allready scanned: " . $seedhost . " scanned: ". $isHostScanned . 
" from: " . $hostdiscovered[count($hostdiscovered)-1]."\n");
		return;
	}
	
	// save seed hostname into the stack
	array_push($hostdiscovered, $seedhost );
	DisplayStack();

	// Look for list of devices connected to the seedhost
	$searchname = cacti_snmp_walk( $seedhost, $snmp_array['snmp_community'], $cdpdevicename, 
	$snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
	$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
	$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
	// check if we where able to do an SNMP query
	$snmp=false;
	if( $searchname ) {
		$snmp=true;
	}
	
	if( $snmp ) {
		// host is scanned now, otherwise we will do it again
		db_execute("UPDATE plugin_linkdiscovery_hosts SET scanned='1' WHERE hostname='" .
		$hostdiscovered[count($hostdiscovered)-1]."'" );

		// loop through the list to find out which are switch and/or router
		// start from the last
		for( $nb=count($searchname)-1;$nb>=0;$nb-- ) {
			$hostrecord_array = array();
			$hostipcapa = array();
			
			// get the IP and capacities of the seed device, based on CDP
			$hostipcapa = hostgetipcapa( $hostdiscovered[count($hostdiscovered)-1], $searchname[$nb]['oid'],
			$snmp_array);

			// what capacities we find on CDP neighbord
			$CDPcapacitiesArray = str_split( preg_replace('/[^0-9A-D]/', '', $hostipcapa['capa']), 3 );
			$CDPcapacities = $CDPcapacitiesArray[1];
			
            $goodtogo = 0; // default value
			if( ($CDPcapacities & $isWifi) ) {
				if( $keepwifi=='on' )
					$goodtogo = $isWifi; //0x02
				else 
					$goodtogo = 0;
			} else if( ($CDPcapacities & $isPhone) ) {
				if( $keepphone=='on' )
					$goodtogo = $isPhone; // 0x80
				else 
					$goodtogo = 0;
			} else if( ($CDPcapacities & $isSwitch) || ($CDPcapacities & $isSwitch2) ) {
				$goodtogo = $isSwitch;  // 0x08 ou 0x40
            } else if( ($CDPcapacities & $isRouter)  ){
				$goodtogo = $isRouter; // 0x01
            } else $goodtogo = 0;
			
	
			if( $goodtogo != 0 ) {
				// extract the IP from the CDP packet
				$hostip = gethostip($hostipcapa['ip']);

				// resolve the hostname and description of the host find into CDP
				$hostrecord_array = resolvehostname($searchname[$nb], $hostipcapa['ip'] );
				$hostrecord_array['hostip'] = $hostip;
				$hostrecord_array['type'] = $hostipcapa['type'];
				
linkdiscovery_debug("\nFind peer: " . $hostrecord_array['hostname']." - ".$hostrecord_array['description']. 
" nb: ". $nb ." capa: ".$hostipcapa['capa']." ip: ".$hostipcapa['ip']." goodtogo: ".$goodtogo . 
" capacities: " .$CDPcapacities . " from :" .$hostdiscovered[count($hostdiscovered)-1]. 
" max cdp: ".count($searchname) ."\n");

				// look for the snmp index of the interface, on the seedhost and on the discovered peer
				$canreaditfpeer = linkdiscovey_get_intf($searchname[$nb], 
				$hostdiscovered[count($hostdiscovered)-1], $hostrecord_array, $snmp_array);

				// save peerhost and interface
				if ( $hostrecord_array['hostname'] == '' ) {
					cacti_log("linkdiscovery_save_data: no IP  useless: ". var_dump($hostrecord_array), false, "LINKDISCOVERY" );
				} else {
					linkdiscovery_save_data( $hostdiscovered[count($hostdiscovered)-1], $hostrecord_array, $canreaditfpeer,	$snmp_array );
linkdiscovery_debug("End saved next host on list\n" );
				}
	
				if (($CDPdeep > 0) ){
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
linkdiscovery_debug( "Same host prev: " . $hostdiscovered[count($hostdiscovered)-2] . " new:" . $hostrecord_array['hostname'] ."\n" );
					}
				}
			} else {
linkdiscovery_debug( " dropped hostname: " . strtolower($searchname[$nb]['value']) . " capa: " .$hostipcapa['capa']. " ip: " . $hostipcapa['ip'] . " good: " . $goodtogo . "\n");
			}
		} // end finded host, need to do thold
		
		$seedhostid = db_fetch_cell("SELECT id FROM host WHERE hostname='" . $seedhost . "'" );

linkdiscovery_debug("End pool");

	} else linkdiscovery_debug( " Can't do snmp on hostname " . !empty($hostdiscovered)?$seedhost:'' . " from: " . (count($hostdiscovered)>1)?$hostdiscovered[count($hostdiscovered)-2]:'' . "(".count($hostdiscovered).")\n");

	// remove the last host scanned
	array_pop($hostdiscovered);
	$seedhost = (count($hostdiscovered)>0)?$hostdiscovered[count($hostdiscovered)-1]:'';
	DisplayStack();
}

// get the ip and capa based on the OID of the name
//***********************
function hostgetipcapa( $seedhost, $hostoidindex, $snmp_array ){
	global $cdpdevicecapacities,$cdpdeviceip,$cdpdevicename,$cdpremotetype;
	
	$ret = array();
	$intfindex = substr( $hostoidindex, strlen($cdpdevicename)+1 );
	
	// Look for the capacities of the devices
	$searchcapa = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpdevicecapacities.".".
	$intfindex, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
	$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
	$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
//linkdiscovery_debug("hostgetipcapa1: ". $seedhost. " OID: " . $cdpdevicecapacities.".".$intfindex . " dump: " .$searchcapa. "\n");

	// look for the IP table 
	$searchip = ld_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpdeviceip.".".$intfindex, 
	$snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
	$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
	$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
//linkdiscovery_debug("hostgetipcapa2: ". $seedhost. " OID: " . $cdpdeviceip.".".$intfindex ." ip: " .$searchip. "\n");

	// look for the equipement type
	$searchtype = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $cdpremotetype.".".
	$intfindex, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
	$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
	$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

	$ret['ip'] = str_replace(":", " ", $searchip);
	$ret['capa'] = $searchcapa;
	$ret['type'] = trim($searchtype);

//linkdiscovery_debug("seed: ". $seedhost. " OID: " . $hostoidindex . " OID CAPA: ".$cdpdevicecapacities.".".$intfindex." capa: ".var_dump($searchcapa)." ip: ".var_dump($searchip). " type: ". $searchtype ."\n");

	return $ret;
}

// get the interface name/index on the seedhost, and index on the remote host 
//**********************
function linkdiscovey_get_intf($hostrecord, $seedhost, $hostrecord_array, $snmp_array){
	global $itfnamearray, $itfidxarray, $cdpinterfacename, $cdpremoteitfname, $snmpifdescr, $snmpiftype, 
	$intftypeeth, $intftypetunnel, $goodtogo, $isWifi, $isPhone;

	$ret = false;

	$itfnamearray = array(); // interface array name of the: source, dest
	// look for the snmp index of the interface
	$itfidx = $hostrecord['oid']; // hostrecord contain oid and desthostname find on the CDP record
	$cdpsnmpitfidx = substr( substr( $itfidx, strlen($cdpinterfacename)+1 ), 0,
	strpos( substr( $itfidx, strlen($cdpinterfacename)+1),".") );

	// sub-index id
	$cdpsnmpsubitfidx = substr( $itfidx, strlen($cdpinterfacename.$cdpsnmpitfidx)+2 );
//linkdiscovery_debug("  Get interface seedhost: ".$seedhost." interface: ".$itfidx." hostrec: ".var_dump($hostrecord_array)."\n" );

	// interface array index of the : source, dest
	$itfidxarray['source'] = $cdpsnmpitfidx;
	$itfidxarray['dest'] = 0;

	// interface name, on the seedhost side
	$itfnamearray['source'] = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], 
	$snmpifdescr.".".$cdpsnmpitfidx, $snmp_array['snmp_version'], $snmp_array['snmp_username'], 
	$snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
	$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

// interface name on dest side but taken from CDP
	$itfnamearray['dest'] = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], 
	$cdpremoteitfname.".".$cdpsnmpitfidx.".".$cdpsnmpsubitfidx, $snmp_array['snmp_version'], 
	$snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'],
	$snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 

	// interface source type
	$itftype = cacti_snmp_get( $seedhost, $snmp_array['snmp_community'], $snmpiftype.".".$cdpsnmpitfidx, 
	$snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
	$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], 
	$snmp_array['snmp_context'] ); 

	// pool peer host for interface description, if posible (no phone, no AP, no unpollable device
	if( $goodtogo != $isWifi && $goodtogo != $isPhone 
		&& ($itftype == $intftypeeth || $itftype == $intftypetunnel ) 
		&& $hostrecord_array['hostname'] !== '' ) {
			
linkdiscovery_debug("snmp interface id for: ". $hostrecord_array['hostname'] ." type: ".$itftype."\n");

		// Get intf index on the destination host, based on the name find on the seedhost
		$itfdstarray = cacti_snmp_walk( $hostrecord_array['hostname'], $snmp_array['snmp_community'], 
		$snmpifdescr, $snmp_array['snmp_version'], $snmp_array['snmp_username'], $snmp_array['snmp_password'], 
		$snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], 
		$snmp_array['snmp_context'] ); 

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

		} else {
linkdiscovery_debug("  snmp host " . $hostrecord_array['hostname'] . " Interface error can't read OID: ".$cdpinterfacename."\n");
			$ret = false;
		}
	} else {
linkdiscovery_debug("  snmp wifi, phone or subinterface no snmp for interface for: " . $hostrecord_array['hostname']. " id: ".$itfnamearray['source'] ." type: ".$itftype ."\n");
		$ret = false;
	}
	
	return $ret;
}

//**********************
function linkdiscovery_save_data( $seedhost, $hostrecord_array, $canpeeritf, $snmp_array ){
	global $config, $itfnamearray, $itfidxarray, $monitor, $goodtogo, $isWifi, $isPhone, $update_hostname, 
	$snmpserialno, $snmpsysdescr, $extenddb;

	// if it's a Wifi or a IP phone we save the host, and the link
	// check if the host does not exist, and we save
	// check if the host allready existe into cacti
	$new_hostid = db_fetch_cell("SELECT id FROM host WHERE hostname='" . 
	$hostrecord_array['hostname'] . "' OR description='" . $hostrecord_array['description'] . "'" );

	if ( $new_hostid == 0 ){
		// Save to cacti because not exsiting peer host
		/*function api_device_save($id, $host_template_id, $description, $hostname, $snmp_community, $snmp_version,
        $snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled,
        $availability_method, $ping_method, $ping_port, $ping_timeout, $ping_retries,
        $notes, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_engine_id, 
		$max_oids, $device_threads, $poller_id = 1, $site_id = 1, $external_id = '') {
*/
		// if it's a phone,a Wifi or an ureachable snmp device don't use any template, and check only via ping
		if( $goodtogo == $isWifi || $goodtogo == $isPhone || !$canpeeritf ) {
			$snmp_array["host_template_id"] 	= '0';
			$snmp_array["availability_method"]  = '3';
			$snmp_array["ping_method"]          = '1';
			$snmp_array["snmp_version"] 		= '0';
			$snmp_array["disable"]				= 'on';
			$snmp_array["notes"] = $hostrecord_array['description'];
		} else {
			// get host template id based on OS defined on automation
			// take info from profile based on OS returned from automation_find_os($sysDescr, $sysObject, $sysName)()
			$snmp_sysDescr = cacti_snmp_get( $hostrecord_array['hostname'], $snmp_array['snmp_community'], 
			$snmpsysdescr, $snmp_array['snmp_version'], $snmp_array['snmp_username'], 
			$snmp_array['snmp_password'], $snmp_array['snmp_auth_protocol'], $snmp_array['snmp_priv_passphrase'], 
			$snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'] ); 
			
			$host_template = automation_find_os($snmp_sysDescr, '', '');
			if( $host_template === false ) {
				linkdiscovery_debug("automation_find_os error(".$snmp_sysDescr."): ".$new_hostid." host: ".var_dump($hostrecord_array));
				$host_template['host_template'] = read_config_option("default_template");
			}
			$snmp_array["host_template_id"] = $host_template['host_template'];
		}

		$new_hostid = api_device_save( '0', $snmp_array['host_template_id'], $hostrecord_array['description'], 
		$hostrecord_array['hostname'], $snmp_array['snmp_community'], $snmp_array['snmp_version'], 
		$snmp_array['snmp_username'], $snmp_array['snmp_password'], $snmp_array['snmp_port'], 
		$snmp_array['snmp_timeout'], $snmp_array['disable'], $snmp_array['availability_method'], 
		$snmp_array['ping_method'], $snmp_array['ping_port'], $snmp_array['ping_timeout'], 
		$snmp_array['ping_retries'], $snmp_array['notes'], $snmp_array['snmp_auth_protocol'], 
		$snmp_array['snmp_priv_passphrase'], $snmp_array['snmp_priv_protocol'], $snmp_array['snmp_context'], 
		$snmp_array['snmp_engine_id'], $snmp_array['max_oids'], $snmp_array['device_threads'], 1, 0 );
linkdiscovery_debug("Cacti Host saved: ".$hostrecord_array['description']." saved id ".$new_hostid. " Host template: ". 
$snmp_array["host_template_id"]."\n");

		if($new_hostid == 0) {
			linkdiscovery_debug("linkdiscovery_save_data error: ".$new_hostid." host: ".var_dump($hostrecord_array).
				"snmp: " .var_dump($snmp_array));
			return;
		} 
		
		// do not monitor Wifi, Phone and unreachable device, and no mailing list
		// otherwise if requested yes 
		if ($monitor == 'on') {
			if( $goodtogo == $isWifi || $goodtogo == $isPhone || !$canpeeritf ) {
				db_execute("update host set monitor='' WHERE id=" . $new_hostid );
				db_execute("update host set thold_send_email=0 WHERE id=" . $new_hostid );
			} else {
				db_execute("update host set monitor='on' WHERE id=" . $new_hostid );
			}
		}

		// Set new host to discovery tree
		linkdiscovery_add_tree ($new_hostid);

		// graph the CPU
		if( $goodtogo != $isWifi && $goodtogo != $isPhone && $canpeeritf ) {
			linkdiscovery_graph_cpu($new_hostid, $snmp_array);
		}
	} else {
		if ( $update_hostname ) {
			db_execute("update host set hostname='". $hostrecord_array['hostname'] . "' where id=" . $new_hostid );
		}
	}
	// save the type and serial number to the new host's record
	if( $extenddb && !empty($hostrecord_array['hostname']) ) {
		// get the serial number and type
		if(  !empty($hostrecord_array['hostname']) ) {
			$type = trim( substr($hostrecord_array['type'], strpos( $hostrecord_array['type'], "cisco" )+strlen("cisco")+1 ) );
			db_execute("update host set type='".$type. "' where id=" . $new_hostid );
		}
		if( $goodtogo == $isPhone && !empty($hostrecord_array['hostname']) ) { 
			// set the flag isPhone
			db_execute("update host set isPhone='on' where id=" . $new_hostid );

			$readphone = read_config_option('linkdiscovery_parse_phone');
			if( $readphone ) {
				parse_phone_data( $seedhost, $hostrecord_array, $new_hostid );
			}

		} else if( $goodtogo == $isWifi && !empty($hostrecord_array['hostname']) ) { // Get the WA information
			db_execute("update host set type='".str_replace("cisco", "", $hostrecord_array['type']). "' where id=" . $new_hostid );

			// get the site_id based on the seedhost
			$site_id = db_fetch_cell("SELECT site_id FROM host where description='". $seedhost ."' OR hostname='".$seedhost. "'" );
linkdiscovery_debug(" site_id2: ".$site_id."\n");
			if( !empty($site_id) ) {
				db_execute("update host set site_id=". $site_id . " where id=" . $new_hostid );
			}
		}

	}

	linkdiscovery_save_host( $new_hostid, $hostrecord_array );
	
	// save the source host
	$seedhostid = db_fetch_cell("SELECT id FROM host where description='". $seedhost ."' OR hostname='".
	$seedhost. "'" );
	
	if( empty($seedhostid ) ){
		linkdiscovery_debug("Error on ID retrive for: ". $seedhost ." id: " . $seedhostid );
		return;
	}
linkdiscovery_debug("   host_src: ".$seedhostid." itf_src: ".$itfidxarray['source']." -> host_dst:".$new_hostid." itf_dst: ".$itfidxarray['dest']."\n" );

	// save interface information
	db_execute("REPLACE INTO plugin_linkdiscovery_intf (host_id_src, host_id_dst, snmp_index_src, snmp_index_dst ) 
		VALUES ("
		. $seedhostid . ", "
		. $new_hostid . ", "
		. $itfidxarray['source'] . ", "
		. $itfidxarray['dest'] . " )");

	// and create the needed graphs on the specific link, except for Phone, or if disable
	if( $goodtogo != $isPhone && $canpeeritf ) {
		linkdiscovery_create_graphs($new_hostid, $seedhostid, $itfidxarray['source'], $snmp_array );
	}

	// Call any other registered function
	api_plugin_hook_function('host_save', array('host_id' => $new_hostid));

	// and then run automation rules
	api_plugin_hook_function('device_action_bottom', array(get_nfilter_request_var('drp_action'), $new_hostid));
	automation_update_device($new_hostid);
	// then run THOLD plugin if present
	if (api_plugin_is_enabled('thold')) {
		if (file_exists($config['base_path'] . '/plugins/thold/thold_functions.php')) {
			include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
			autocreate($new_hostid);
		}
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

		linkdiscovery_debug("Saved Host to plugin_linkdiscovery_hosts: " . $hostrecord_array['description'] ." hostname: " . $hostrecord_array['hostname']." res: ".$ret."\n" );
	} else {
		linkdiscovery_debug("plugin_linkdiscovery_hosts host exist ". $hostrecord_array['hostname']." id: ".$hostid."\n");
	}
}

function parse_phone_data( $seedhost, $hostrecord_array, $new_hostid ) {
			// get the site_id based on the seedhost
			$site_id = db_fetch_cell("SELECT site_id FROM host where description='". $seedhost ."' OR hostname='".$seedhost. "'" );
linkdiscovery_debug(" site_id1: ".$site_id."\n");
			if( !empty($site_id) ) {
				db_execute("update host set site_id=". $site_id . " where id=" . $new_hostid );
			}

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
			} else {
				linkdiscovery_debug(" Can't get numbers "."\n");
				return; // so we end the query here
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
			} else linkdiscovery_debug(" Can't get serial numbers "."\n");
			
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
			} else linkdiscovery_debug(" Can't get model "."\n");
}

//**********************
function linkdiscovery_graph_cpu( $new_hostid, $snmp_array ){
	// graph the CPU if requested, on the new host, and don't do it twice
	//
	$cpu_graph_template = read_config_option("linkdiscovery_CPU_graph");
	if( $cpu_graph_template == 'on' ) {

//Template from snmp_query
		$snmp_query_id = db_fetch_cell("SELECT snmp_query.id snmp_id, snmp_query.name snmp_name
			FROM snmp_query
			INNER JOIN host_template
			ON host_template.id=".$snmp_array['host_template_id']."
			INNER JOIN host_template_snmp_query
			ON host_template_snmp_query.host_template_id=host_template.id 
			AND host_template_snmp_query.snmp_query_id=snmp_query.id
			WHERE snmp_query.name like '%CPU%'" );

//Template from Graph
		$graph_template_id = db_fetch_cell("SELECT graph_templates.id graph_id, graph_templates.name graph_name
			FROM graph_templates
			INNER JOIN host_template
			ON host_template.id=".$snmp_array['host_template_id']."
			INNER JOIN host_template_graph 
			ON host_template_graph.host_template_id=host_template.id 
			AND graph_templates.id=host_template_graph.graph_template_id
			WHERE graph_templates.name like '%CPU%'");

		$existsAlready = db_fetch_assoc("SELECT graph_local.id FROM graph_local
		INNER JOIN graph_templates 
		ON graph_local.graph_template_id=graph_templates.id 
		WHERE graph_local.host_id=".$new_hostid. "
		AND graph_templates.name LIKE '%CPU%' ");

linkdiscovery_debug("CPU Graph: ". $new_hostid ." graph_template_id: ".var_dump($existsAlready) );
		if ( !$existsAlready ) {
			if( $graph_template ){
				$empty=array();
				$snmp_query_array=array();
				$return_array = create_complete_graph_from_template( $graph_template_id, $new_hostid, 
								$snmp_query_array, $empty);
			} else if( $snmp_query_id ){
				$query = run_data_query( $new_hostid, $snmp_query_id );
				$empty=array();
				$snmp_query_array["snmp_query_id"] = $snmp_query_id;
/*				$snmp_query_array["snmp_index_on"]
				$snmp_query_array["snmp_query_graph_id"]
				$snmp_query_array["snmp_index"]*/
linkdiscovery_debug("CPU query: ". $new_hostid ." ".var_dump($query) );
//				$return_array = create_complete_graph_from_template( $graph_template_id, $new_hostid,  $snmp_query_array, $empty);
			}
linkdiscovery_debug("   Created CPU graph" );
		} else {
linkdiscovery_debug("CPU Graph exist: ". $new_hostid ." graph_template_id: ".var_dump($existsAlready) );
		}
	}
	
}

//**********************
function linkdiscovery_create_graphs( $new_hostid, $seedhostid, $src_intf, $snmp_array ) {
	global $snmp_status_query_graph_id, $snmp_traffic_query_graph_id, $snmp_packets_query_graph_id, 
	$snmp_errors_query_graph_id;

	// should we do a graph for traffic
	// snmp_query_graph_id= 27
	// graph_template_id = 41
	// snmp_query_id = 1
	if( $snmp_traffic_query_graph_id > 0 ) {
		$return_array = buildGraph( $snmp_traffic_query_graph_id, $new_hostid, $seedhostid, $src_intf, $snmp_array);
		if( $return_array ) {
linkdiscovery_debug("   Created traffic graph src_intf: " .$src_intf." * ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph traffic exist: " .$seedhostid . " id: " .$snmp_traffic_query_graph_id."\n" );
		}
	}

	// should we do a graph for NonUnicast packet
	// snmp_packets_query_graph_id=39
	// packets_graph_template_id=46
	// snmp_query_id=10
	if( $snmp_packets_query_graph_id > 0 ) {
		$return_array = buildGraph( $snmp_packets_query_graph_id, $new_hostid, $seedhostid, $src_intf, $snmp_array);
		if( $return_array ) {
linkdiscovery_debug("   Created packets graph src_intf: " .$src_intf." * ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph packets exist: " .$seedhostid . " id: " .$snmp_packets_query_graph_id."\n" );
		}
	}

	// should we do a graph for the status
	// snmp_query_graph_id= 23
	// graph_template_id = 38
	// snmp_query_id = 1
	if( $snmp_status_query_graph_id > 0 ) {
		$return_array = buildGraph( $snmp_status_query_graph_id, $new_hostid, $seedhostid, $src_intf, $snmp_array);
		if( $return_array ) {
linkdiscovery_debug("   Created status graph src_intf: " .$src_intf." * ". get_graph_title($return_array["local_graph_id"]) ."\n");
		} else {
linkdiscovery_debug("   Graph status exist: " .$seedhostid . " id: " .$snmp_status_query_graph_id."\n" );
		}

	}

		// should we do a graph for errors
    if( $snmp_errors_query_graph_id > 0) {
		$return_array = buildGraph( $snmp_errors_query_graph_id, $new_hostid, $seedhostid, $src_intf, $snmp_array);
		if( $return_array ) {
linkdiscovery_debug("   Created Errors graph src_intf: " .$src_intf." * ". get_graph_title($return_array["local_graph_id"]) ."\n");
        } else {
linkdiscovery_debug("   Graph Errors exist: " .$seedhostid . " id: " .$snmp_errors_query_graph_id."\n" );
        }
    }

	/* lastly push host-specific information to our data sources */
	push_out_host($seedhostid,0);
linkdiscovery_debug("end push out: " .$seedhostid . "\n" );
}

function buildGraph( $snmp_query_graph_id, $new_hostid, $seedhostid, $src_intf, $snmp_array ) {
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

//	$snmp_array['host_template_id']
	// Traffic:
	// $seedhostid = 7106
	// $snmp_query_graph_id= 27
	// $graph_template_id = 41
	// $snmp_query_id = 1
	
	$return_array = array();
	$graph_template_id = db_fetch_cell("SELECT graph_template_id FROM snmp_query_graph 
	WHERE id=".$snmp_query_graph_id);
	
	$snmp_query_id = db_fetch_cell("SELECT snmp_query_graph.snmp_query_id 
	FROM snmp_query_graph
	WHERE snmp_query_graph.graph_template_id=".$graph_template_id );

linkdiscovery_debug("BuildGraph gtpi: ".$graph_template_id ." sqi: ". $snmp_query_id );

	
	if( $snmp_query_id > 0) {
		// take interface to be monitored, on the new host
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local 
		WHERE graph_template_id=".$graph_template_id." 
		AND host_id=".$seedhostid ." 
		AND snmp_query_id=".$snmp_query_id ." 
		AND snmp_index=".$src_intf);
		
		if( empty($existsAlready) ) {
			$empty=array();
			$snmp_query_array["snmp_query_id"] = $snmp_query_id;
			$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($seedhostid, $snmp_query_id);
			$snmp_query_array["snmp_query_graph_id"] = $snmp_query_graph_id;
			$snmp_query_array["snmp_index"] = $src_intf;
			$return_array = create_complete_graph_from_template( $graph_template_id, $seedhostid, $snmp_query_array, $empty);
			$status = $return_array;
//linkdiscovery_debug("BuildGraph: ". var_dump($snmp_query_array)." return: ".var_dump($return_array));
		} else $status = false;
	} else $status = false;
	
	return $status;
}

function linkdiscovery_add_tree ($host_id) {
/** api_tree_item_save - saves the tree object and then resorts the tree
 * @arg $id - the leaf_id for the object
 * @arg $tree_id - the tree id for the object
 * @arg $type - the item type graph, host, leaf
 * @arg $parent_tree_item_id - The parent leaf for the object
 * @arg $title - The leaf title in the caseo a leaf
 * @arg $local_graph_id - The graph id in the case of a graph
 * @arg $host_id - The host id in the case of a graph
 * @arg $site_id - The site id in the case of a graph
 * @arg $host_grouping_type - The sort order for the host under expanded hosts
 * @arg $sort_children_type - The sort type in the case of a leaf
 * @arg $propagate_changes - Wether the changes should be cascaded through all children
 * @returns - boolean true or false depending on the outcome of the operation */

	$tree_id = read_config_option("linkdiscovery_tree"); // sous graph_tree_itesm c'est la valeur graph_tree_id
	$tmp_sub_tree_id = read_config_option("linkdiscovery_sub_tree");
	$sub_tree_id = empty($tmp_sub_tree_id)?'0':$tmp_sub_tree_id;
	
	// if the sub_tree_id is on graph_tree_items, that mean we have a parent 
	$parent = db_fetch_row('SELECT parent FROM graph_tree_items WHERE graph_tree_id = ' . $tree_id.' AND host_id=0 AND local_graph_id=0 AND id=' .$sub_tree_id );
	if( !empty($parent) ) {
		api_tree_item_save(0, $tree_id, 3, $sub_tree_id, '', 0, $host_id, 0, 1, 1, false);
	} else {
		// just save under the graph_tree_item, but with sub_tree_id as 0
		api_tree_item_save(0, $tree_id, 3, 0, '', 0, $host_id, 0, 1, 1, false);
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

//linkdiscovery_debug("fqdn: " .$fqdnname." desc: ".$hostdescription." hostip: ". $hostip."\n");

		// check if need to resolve the name to put the IP into the hostname
		if( $use_ip_hostname ) {
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery1: " .var_dump($dnsquery) );
			if (!$dnsquery) { // check if it work as supplied, if not add the define domain 
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery2: " .var_dump($dnsquery) );
				if ( !$dnsquery) { // check if it work with new hostname and domain, if not just use ip find into CDP 
					$hostname = $hostip; // if no dns answer use what is requested IP
				} else $hostname = $dnsquery[0]['ip'];
			} else $hostname = $dnsquery[0]['ip'];
		} else {
			// chek if the hostname receive from CDP is FQDN ortherwise add domain
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery3: " .var_dump($dnsquery) );
			if ( $dnsquery) { // check if it work with 
				$hostname = strtolower($fqdnname);
			} else {
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery4: " .var_dump($dnsquery) );
				if( $dnsquery ){
					$hostname = strtolower($fqdnname);
				} else { // if not try to resolve IP
					$hostname = is_null($hostip)?strtolower($fqdnname):gethostbyaddr($hostip);
				}
			} 
		}
				
		// check if we use the FQDN for description
		if( $use_fqdn_description ) {
			$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery5: " .$dnsquery );
			if ( $dnsquery) { // check if it work with FQDN provided from CDP
				$hostdescription = strtolower($fqdnname);
			} else {
				$fqdnname .= "." . $domain_name;
				$dnsquery = dns_get_record( $fqdnname, DNS_A);
//linkdiscovery_debug("dnsquery6: " .$dnsquery );
				if( $dnsquery ){
					$hostdescription = strtolower($fqdnname);
				} else $hostdescription = is_null($hostip)?strtolower($fqdnname):$hostip;
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
	if ($debug)	print $text."\n";
	if ($debug) cacti_log($text, false, "LINKDISCOVERY" );
	flush();
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Link Discovery v".read_config_option("plugin_linkdiscovery_version").", Copyright 2017 - Arno Streuli\n\n";
	print "usage: findhosts.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-s=IP    - force discovery from a specific host IP address\n";
	print "-f	    - Force the execution of a Link Discovery process\n";
	print "-d	    - Display verbose output during execution\n";
	print "-r	    - Drop and Recreate the Link Discovery Plugin's tables before running\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}

?>

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function ld_snmp_get($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return "U";
	}

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpget("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_get("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_get("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: LD SNMP Get Timeout for Host:'$hostname', and OID:'$oid'", false);
		}
	}else {
		$snmp_value = '';
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community): "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community) : "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . cacti_escapeshellarg($priv_pass) . " -x " . cacti_escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . cacti_escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . cacti_escapeshellarg($username) .
				" -l " . cacti_escapeshellarg($proto) .
				" -a " . cacti_escapeshellarg($auth_proto) .
				" -A " . cacti_escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			exec(cacti_escapeshellcmd(read_config_option("path_snmpget")) . " -O xvt -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid), $snmp_value);
		}else {
			exec(cacti_escapeshellcmd(read_config_option("path_snmpget")) . " -O xfntevU " . $snmp_auth . " -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid), $snmp_value);
		}

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	/* fix for multi-line snmp output */
	if (isset($snmp_value)) {
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	if (substr_count($snmp_value, "Timeout:")) {
		cacti_log("WARNING: LD SNMP Get Timeout for Host:'$hostname', and OID:'$oid'", false);
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value, false);

	return $snmp_value;
}

function ld_snmp_getnext($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return "U";
	}

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpgetnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_getnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_getnext("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: LD SNMP GetNext Timeout for Host:'$hostname', and OID:'$oid'", false);
		}
	}else {
		$snmp_value = '';
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community): "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community): "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . cacti_escapeshellarg($priv_pass) . " -x " . cacti_escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . cacti_escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . cacti_escapeshellarg($username) .
				" -l " . cacti_escapeshellarg($proto) .
				" -a " . cacti_escapeshellarg($auth_proto) .
				" -A " . cacti_escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			exec(cacti_escapeshellcmd(read_config_option("path_snmpgetnext")) . " -O xvt -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid), $snmp_value);
		}else {
			exec(cacti_escapeshellcmd(read_config_option("path_snmpgetnext")) . " -O xfntevU $snmp_auth -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid), $snmp_value);
		}
	}

	if (isset($snmp_value)) {
		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	if (substr_count($snmp_value, "Timeout:")) {
		cacti_log("WARNING: LD SNMP GetNext Timeout for Host:'$hostname', and OID:'$oid'", false);
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value, false);

	return $snmp_value;
}

function ld_snmp_walk($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $max_oids = 10, $environ = SNMP_POLLER) {
	global $config, $banned_snmp_strings;

	$snmp_oid_included = true;
	$snmp_auth	       = '';
	$snmp_array        = array();
	$temp_array        = array();

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* determine default max_oids */
	if (($max_oids == 0) || (!is_numeric($max_oids))) {
		$max_oids = read_config_option("max_get_size");

		if ($max_oids == "") $max_oids = 10;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($max_oids)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return array();
	}

	$path_snmpbulkwalk = read_config_option("path_snmpbulkwalk");

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3)) &&
		(($version == 1) ||
		(version_compare(phpversion(), "5.1") >= 0) ||
		(!file_exists($path_snmpbulkwalk)))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		/* force php to return numeric oid's */
		if (function_exists("snmp_set_oid_numeric_print")) {
			snmp_set_oid_numeric_print(TRUE);
		}

		if (function_exists("snmprealwalk")) {
			$snmp_oid_included = false;
		}

		snmp_set_quick_print(0);

		if ($version == "1") {
			$temp_array = @snmprealwalk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$temp_array = @snmp2_real_walk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$temp_array = @snmp3_real_walk("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($temp_array === false) {
			cacti_log("WARNING: LD SNMP Walk Timeout for Host:'$hostname', and OID:'$oid'", false);
		}

		/* check for bad entries */
		if (is_array($temp_array) && sizeof($temp_array)) {
		foreach($temp_array as $key => $value) {
			foreach($banned_snmp_strings as $item) {
				if(strstr($value, $item) != "") {
					unset($temp_array[$key]);
					continue 2;
				}
			}
		}
		}

		$o = 0;
		for (@reset($temp_array); $i = @key($temp_array); next($temp_array)) {
			if ($temp_array[$i] != "NULL") {
				$snmp_array[$o]["oid"] = preg_replace("/^\./", "", $i);
				$snmp_array[$o]["value"] = format_snmp_string($temp_array[$i], $snmp_oid_included);
			}
			$o++;
		}
	}else{
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community): "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? cacti_escapeshellarg($community): "-c " . cacti_escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . cacti_escapeshellarg($priv_pass) . " -x " . cacti_escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . cacti_escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . cacti_escapeshellarg($username) .
				" -l " . cacti_escapeshellarg($proto) .
				" -a " . cacti_escapeshellarg($auth_proto) .
				" -A " . cacti_escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option("path_snmpwalk")) . " -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid));
		}else {
			if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($max_oids > 1)) {
				$temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) . " -O Qn $snmp_auth -v $version -t $timeout -r $retries -Cr$max_oids " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid));
			}else{
				$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option("path_snmpwalk")) . " -O Qn $snmp_auth -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid));
			}
		}

		if (substr_count(implode(" ", $temp_array), "Timeout:")) {
			cacti_log("WARNING: LD SNMP Walk Timeout for Host:'$hostname', and OID:'$oid'", false);
		}

		/* check for bad entries */
		if (is_array($temp_array) && sizeof($temp_array)) {
		foreach($temp_array as $key => $value) {
			foreach($banned_snmp_strings as $item) {
				if(strstr($value, $item) != "") {
					unset($temp_array[$key]);
					continue 2;
				}
			}
		}
		}

		for ($i=0; $i < count($temp_array); $i++) {
			if ($temp_array[$i] != "NULL") {
				/* returned SNMP string e.g. 
				 * .1.3.6.1.2.1.31.1.1.1.18.1 = STRING: === bla ===
				 * split off first chunk before the "="; this is the OID
				 */
				list($oid, $value) = explode("=", $temp_array[$i], 2);
				$snmp_array[$i]["oid"]   = trim($oid);
				$snmp_array[$i]["value"] = format_snmp_string($temp_array[$i], true);
			}
		}
	}

	return $snmp_array;
}
?>
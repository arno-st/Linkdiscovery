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

function plugin_linkdiscovery_install () {
	api_plugin_register_hook('linkdiscovery', 'top_header_tabs', 'linkdiscovery_show_tab', 'setup.php');
	api_plugin_register_hook('linkdiscovery', 'top_graph_header_tabs', 'linkdiscovery_show_tab', 'setup.php');
	api_plugin_register_hook('linkdiscovery', 'config_arrays', 'linkdiscovery_config_arrays', 'setup.php'); // array used by this plugin
	api_plugin_register_hook('linkdiscovery', 'config_settings', 'linkdiscovery_config_settings', 'setup.php'); // personl settings info
	api_plugin_register_hook('linkdiscovery', 'draw_navigation_text', 'linkdiscovery_draw_navigation_text', 'setup.php'); // nav bar under console and grpah tab
	api_plugin_register_hook('linkdiscovery', 'poller_bottom', 'linkdiscovery_poller_bottom', 'setup.php'); // define and setting of personal poller
	api_plugin_register_hook('linkdiscovery', 'utilities_action', 'linkdiscovery_utilities_action', 'setup.php');
	api_plugin_register_hook('linkdiscovery', 'utilities_list', 'linkdiscovery_utilities_list', 'setup.php');
	api_plugin_register_hook('linkdiscovery', 'device_remove', 'linkdiscovery_device_remove', 'setup.php');

	api_plugin_register_realm('linkdiscovery', 'linkdiscovery.php,findhosts.php,phones.php', 'Plugin -> LinkDiscovery', 1);

	linkdiscovery_setup_table();
}

function plugin_linkdiscovery_uninstall () {
	// Do any extra Uninstall stuff here

	// Remove items from the settings table
	db_execute('DELETE FROM settings WHERE name LIKE "%linkdiscovery%"');
	db_execute('DROP TABLE IF EXISTS plugin_linkdiscovery_intf');
	db_execute('DROP TABLE IF EXISTS plugin_linkdiscovery_hosts');
}

function plugin_linkdiscovery_check_config () {
	global $config;
	// Here we will check to ensure everything is configured
	linkdiscovery_check_upgrade ();

	return true;
}

function plugin_linkdiscovery_upgrade () {
	// Here we will upgrade to the newest version
	linkdiscovery_check_upgrade();
	return false;
}

function linkdiscovery_check_upgrade() {
	$version = plugin_linkdiscovery_version ();
	$current = $version['version'];
	$old     = read_config_option('plugin_linkdiscovery_version');
	if ($current != $old) {

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='linkdiscovery'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='"    . $version['longname'] . "', 
			author='"  . $version['author'] . "', 
			webpage='" . $version['homepage'] . "' 
			WHERE directory='" . $version['name'] . "' ");

	}
}

function plugin_linkdiscovery_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/linkdiscovery/INFO', true);
	return $info['info'];
}

function linkdiscovery_config_settings () {
	global $tabs, $settings, $linkdiscovery_poller_frequencies, $linkdiscovery_get_host_template, $linkdiscovery_cpu_graph;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	// get device list
	$host_names = db_fetch_assoc("SELECT id, hostname FROM host WHERE DISABLED!='ON' ORDER BY hostname");
	$sample_host_names = array();

	if (sizeof($host_names) > 0) {
		foreach ($host_names as $ht) {
			$sample_host_names[$ht['id']] = $ht['hostname'];
		}
	}

	$tabs['LinkDiscovery'] = 'LinkDiscovery';
	$settings['LinkDiscovery'] = array(
		"linkdiscovery_general_header" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"linkdiscovery_seed" => array(
			"friendly_name" => "Seed device to start the scan",
			"description" => "This is the device where we will start the scan.",
			"method" => "drop_array",
			"value" => "|arg1:host_name|",
			"array" => $sample_host_names,
			),
		"linkdiscovery_domain_name" => array(
			"friendly_name" => "Domain Name for DNS",
			"description" => "Either due to equipment configuration or discovery protocol implementation, a hostname reported by/to a device may not be reported/stored in a fully qualified state. It is strongly suggested you fill this in with the domain name your network equipment is found in (E.G., 'net.yourcompany.com') without a leading or trailing '.'.  It will be appended to hostnames that do not appear to be fully qualified domain names (FQDN).",
			"method" => "textbox",
			"max_length" => 255,
			"default" => ""
			),
		'linkdiscovery_use_ip_hostname' => array(
			'friendly_name' => "Use IP For Hostname",
			'description' => "For a device to be added to Cacti, the IP must be resolved via DNS.  When adding the device, do you want to use the detected IP as the Cacti hostname?  Yes will reduce queries against the DNS server for the system and give a greater chance of polling occurring should DNS fail somewhere along the line.  No means the derived FQDN will be used instead.",
			'method' => 'checkbox',
			'default' => 'off',
			),
		'linkdiscovery_use_fqdn_for_description' => array(
			'friendly_name' => "Use FQDN for Description",
			'description' => "If checked, when adding a device, the device's fully qualified domain name (however it was derived) will be used for the Cacti Description.  If unchecked, the 'short' host name (everything before the first '.') will be used instead.",
			"method" => 'checkbox',
			"default" => "off",
			),
		'linkdiscovery_update_hostname' => array(
			'friendly_name' => "Update the hostname field with FQDN if available",
			'description' => "When discovered, if a device exist into cacti, the hostname will be set to the FQDN value we can retrive either way from the CDP name or the CDP ip.",
			'method' => 'checkbox',
			'default' => 'off',
			),
		"linkdiscovery_collection_timing" => array(
			"friendly_name" => "Poller Frequency",
			"description" => "Choose how often to attempt to find devices on  your network.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $linkdiscovery_poller_frequencies,
			),
		"linkdiscovery_base_time" => array(
			"friendly_name" => "Start Time for Polling",
			"description" => "When would you like the first polling to take place.  All future polling times will be based upon this start time.  A good example would be 12:00AM.",
			"default" => "12:00am",
			"method" => "textbox",
			"max_length" => "10"
			),
		'linkdiscovery_monitor' => array(
			'friendly_name' => 'Enable Monitor',
			'description' => 'Enable monitor for the new device',
			'method' => 'checkbox',
			'default' => 'off'
			),
		"linkdiscovery_CDP_deepness" => array(
			"friendly_name" => "How deep CDP go",
			"description" => "Define how many CDP level we go from the seed device.",
			"default" => "1",
			"method" => "textbox",
			"max_length" => "3"
			),
		"linkdiscovery_graph_header" => array(
			"friendly_name" => "Graph creation",
			"method" => "spacer",
			),
/*		"linkdiscovery_host_template" => array(
			"friendly_name" => "host Template",
			"description" => "Select a Host Template that device will be matched to.",
			"method" => "drop_array",
			"array" => $linkdiscovery_get_host_template,
			),*/
		'linkdiscovery_CPU_graph' => array(
			'friendly_name' => 'CPU Graph',
//			'description' => 'Enable CPU Graph, and which template to use',
			'description' => 'Enable CPU Graph',
			'method' => 'checkbox',
			'default' => 'off'
//			'method' => "drop_array",
//			'array' => $linkdiscovery_cpu_graph, 
			),
		'linkdiscovery_status_graph' => array(
			'friendly_name' => 'Status Graph',
//			'description' => 'Enable Status Graph, and which type to use',
			'description' => 'Enable Status Graph',
			'method' => 'checkbox',
			'default' => 'off'
//			'method' => "drop_array",
//			'array' => linkdiscovery_get_graph_template('status'), 
			),
		'linkdiscovery_traffic_graph' => array(
			'friendly_name' => 'Traffic Graph',
//			'description' => 'Enable Traffic Graph, and which type to use',
			'description' => 'Enable Traffic Graph',
			'method' => 'checkbox',
			'default' => 'off'
//			'method' => "drop_array",
//			'array' => linkdiscovery_get_graph_template('traffic'), 
			),
		'linkdiscovery_packets_graph' => array(
			'friendly_name' => 'Packets Graph',
//			'description' => 'Enable Non-unicast or other packets Graph, and which type to use',
			'description' => 'Enable Non-unicast or other packets Graph',
			'method' => 'checkbox',
			'default' => 'off'
//			'method' => "drop_array",
//			'array' => linkdiscovery_get_graph_template('Packets'), 
			),
		'linkdiscovery_status_thold' => array(
			'friendly_name' => 'Status Threshold',
			'description' => 'Enable Status Threshold, and which template to use',
			'method' => "drop_array",
			'array' => linkdiscovery_get_thold_template('status'), 
			),
		'linkdiscovery_traffic_thold' => array(
			'friendly_name' => 'Traffic Threshold',
			'description' => 'Enable traffic Threshold, and which template to use',
			'method' => "drop_array",
			'array' => linkdiscovery_get_thold_template('traffic'), 
			),
		"linkdiscovery_other_header" => array(
			"friendly_name" => "other option",
			"method" => "spacer",
			),
		"linkdiscovery_keep_wifi" => array(
			"friendly_name" => "Keep the link with WiFi Access Point",
			"description" => "Should we keep Wifi Access Point as peer host (no CDP scan on it, AP save into cacti), monitored with ping, and link monitored on source device.",
			'method' => 'checkbox',
			'default' => 'off'
			),
		"linkdiscovery_keep_phone" => array(
			"friendly_name" => "Keep the identified phone",
			"description" => "Should we keep IP Phone as device (no CDP scan on it, save into cacti as device), and monitor via ping only.",
			'method' => 'checkbox',
			'default' => 'off'
			),
		'linkdiscovery_no_scan' => array(
			'friendly_name' => "Don't scan this host",
			'description' => 'Comma separeted hostname, where scanning is prohibited. Hostname must match hostname defined on cacti',
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
		),
		'linkdiscovery_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages during LinkDiscovery',
			'method' => 'checkbox',
			'default' => 'off'
			),
		"linkdiscovery_tree" => array(
			"friendly_name" => "Tree location",
			"description" => "Select a location in the tree to place the host discovered.",
			"method" => "drop_array",
			"array" => linkdiscovery_get_tree_headers('tree'),
			),
		"linkdiscovery_sub_tree" => array(
			"friendly_name" => "Sub-Tree location",
			"description" => "Select a location in the sub-tree to place the host discovered.",
			"method" => "drop_array",
			"array" => linkdiscovery_get_tree_headers('subtree'),
			'default' => ''
			),
	);
}

function linkdiscovery_show_tab () {
	global $config;
	include_once($config["library_path"] . "/database.php");

	if (api_user_realm_auth('linkdiscovery.php')) {
		if (!substr_count($_SERVER["REQUEST_URI"], "linkdiscovery.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/linkdiscovery/linkdiscovery.php"><img src="' . $config['url_path'] . 'plugins/linkdiscovery/images/tab_discover.gif" alt="LinkDiscovery" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/linkdiscovery/linkdiscovery.php"><img src="' . $config['url_path'] . 'plugins/linkdiscovery/images/tab_discover_down.gif" alt="LinkDiscovery" align="absmiddle" border="0"></a>';
		}
	}
	
	if (api_user_realm_auth('phones.php') && read_config_option('linkdiscovery_keep_phone') ) {

		if (!substr_count($_SERVER["REQUEST_URI"], "phones.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/linkdiscovery/phones.php"><img src="' . $config['url_path'] . 'plugins/linkdiscovery/images/tab_phones.gif" alt="Phones" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/linkdiscovery/phones.php"><img src="' . $config['url_path'] . 'plugins/linkdiscovery/images/tab_phones_down.gif" alt="Phones" align="absmiddle" border="0"></a>';
		}
	}
}

function linkdiscovery_config_arrays () {
	global $config, $settings, $linkdiscovery_poller_frequencies, $linkdiscovery_get_host_template, $linkdiscovery_cpu_graph;

	$linkdiscovery_poller_frequencies = array(
		"disabled" => "Disabled",
		"240" => "Every 4 Hours",
		"360" => "Every 6 Hours",
		"480" => "Every 8 Hours",
		"720" => "Every 12 Hours",
		"1440" => "Every Day",
		"10080" => "Every Week",
		"20160" => "Every 2 Weeks",
		"40320" => "Every 4 Weeks"
		);


	// get template list
	$host_template_names = db_fetch_assoc("SELECT id, name FROM host_template");
	$linkdiscovery_get_host_template = array();

	if (sizeof($host_template_names) > 0) {
		foreach ($host_template_names as $ht) {
			$linkdiscovery_get_host_template[$ht['id']] = $ht['name'];
		}
	}
/*
	// get CPU graph template
	$linkdiscovery_cpu_graph = array();
	$linkdiscovery_host_template = (read_config_option('linkdiscovery_host_template')>0)?read_config_option('linkdiscovery_host_template'):1;
	$dbquery = db_fetch_assoc("SELECT graph_templates.id, graph_templates.name
			FROM host_template,host_template_graph,graph_templates 
			WHERE host_template.id=" . $linkdiscovery_host_template . "
			AND host_template.id=host_template_graph.host_template_id
			AND graph_templates.id=host_template_graph.graph_template_id AND graph_templates.name LIKE '%cpu%'");

	if (sizeof($dbquery) > 0) {
		$linkdiscovery_cpu_graph[0] = "Disabled";
		foreach ($dbquery as $ht) {
		$linkdiscovery_cpu_graph[$ht['id']] = $ht['name'];
		}
	}
*/	
}

function routerconfigs_draw_navigation_text ($nav) {
	$nav['linkdiscovery.php:'] = array(
		'title' => __('Linkdiscovery', 'linkdiscovery'),
		'mapping' => 'index.php:',
		'url' => 'linkdiscovery.php',
		'level' => '1'
	);
	$nav['phones.php:'] = array(
		'title' => __('Phones list', 'linkdiscovery'),
		'mapping' => 'index.php:',
		'url' => 'phones.php',
		'level' => '1'
	);

	return $nav;
}

function linkdiscovery_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'host_template_id', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(150)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(250)', 'NULL' => true);
	$data['columns'][] = array('name' => 'community', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_version', 'type' => 'tinyint(1)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'default' =>  '');
	$data['columns'][] = array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'default' => '');
	$data['columns'][] = array('name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'default' => '');
	$data['columns'][] = array('name' => 'snmp_context', 'type' => 'varchar(64)', 'default' => '');
	$data['columns'][] = array('name' => 'scanned', 'type' => 'tinyint(1)', 'default' => '0');
	$data['primary'] = 'description';
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Plugin linkdiscovery - Table of linkdiscovery discovered hosts';
	api_plugin_db_table_create('linkdiscovery', 'plugin_linkdiscovery_hosts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'host_id_src', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'host_id_dst', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_index_src', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_index_dst', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '0');
	$data['primary'] = "host_id_src`,`snmp_index_src";
	$data['keys'][] = array('name' => 'host_id_src', 'columns' => 'host_id_src');
	$data['keys'][] = array('name' => 'snmp_index_src', 'columns' => 'snmp_index_src');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Plugin linkdiscovery - Table of linkdiscovery discovered interface';
	api_plugin_db_table_create('linkdiscovery', 'plugin_linkdiscovery_intf', $data);
}

function linkdiscovery_poller_bottom () {
	global $config;

	include_once($config['library_path'] . '/poller.php');
	include_once($config["library_path"] . "/database.php");

	if (read_config_option("linkdiscovery_collection_timing") == "disabled")
		return;

	$t = read_config_option("linkdiscovery_last_poll");

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option("poller_interval");
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}

	if ($t != '' && (time() - $t < $poller_interval))
		return;

	$command_string = trim(read_config_option("path_php_binary"));

	// If its not set, just assume its in the path
	if (trim($command_string) == '')
		$command_string = 'php';
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/linkdiscovery/findhosts.php';

	exec_background($command_string, $extra_args);
	
	if ($t == "")
		$sql = "INSERT INTO settings VALUES ('linkdiscovery_last_poll','" . time() . "')";
	else
		$sql = "UPDATE settings SET value = '" . time() . "' where name = 'linkdiscovery_last_poll'";
	$result = db_execute($sql);

}

function linkdiscovery_get_tree_headers($type) {
	$headers = array();
	if ($type == 'tree') { 
		// root tree
		$trees = db_fetch_assoc("SELECT id, name FROM graph_tree ORDER BY id");
		foreach ($trees as $tree) {
			$headers[$tree['id']] = $tree['name'];
			$linkdiscovery_tree[$tree['id']] = $tree['name'];
			$linkdiscovery_sub_tree = '';
		}
	} else {
		$parent = read_config_option("linkdiscovery_tree");
		if($parent == null ) $parent=0;
		$headers = treeList( $headers, $parent, '0', '' );
	}
	return $headers;
}

function treeList( $headers, $treeId=0, $parentId, $spaces ){
	
	$items = db_fetch_assoc("SELECT id, title, parent, graph_tree_id FROM graph_tree_items WHERE host_id=0 AND graph_tree_id=" .$treeId. " AND parent=".$parentId." ORDER BY parent, id");
	if (sizeof($items) > 0) {
		$spaces .= '--'; 
		foreach ($items as $item) {
			$parent = $item['id'];
			$headers[$item['id']] = $spaces . $item['title'];
			$headers = treeList( $headers, $treeId, $parent, $spaces );
		}
	} else $header[0] = '';
	
	return $headers;
}

function linkdiscovery_get_cpu_graph( $linkdiscovery_host_template ){
		// get CPU graph template
	$dbquery = db_fetch_cell("SELECT graph_templates.id
			FROM host_template,host_template_graph,graph_templates 
			WHERE host_template.id=" . $linkdiscovery_host_template . "
			AND host_template.id=host_template_graph.host_template_id
			AND graph_templates.id=host_template_graph.graph_template_id AND graph_templates.name LIKE '%cpu%' LIMIT 1");

	
	return $dbquery;
}
function linkdiscovery_get_thold_template( $type ) {
	global $thold;

	if( $thold = false )
		return;

	$header = array();
	
	$dbquery = db_fetch_assoc("SELECT thold_template.id, thold_template.name
			FROM thold_template 
			WHERE thold_template.name LIKE'%$type%'");


	if (sizeof($dbquery) > 0) {
		$header[0] = "Disabled";
		foreach ($dbquery as $ht) {
		$header[$ht['id']] = $ht['name'];
		}
	}

	return $header;
}

function linkdiscovery_get_graph_template( $linkdiscovery_host_template, $type) {
	$dbquery = db_fetch_cell("SELECT snmp_query_graph.id
		FROM host_template_snmp_query,snmp_query,snmp_query_graph,graph_templates 
		WHERE host_template_snmp_query.host_template_id=" . $linkdiscovery_host_template . "
		AND host_template_snmp_query.snmp_query_id=snmp_query.id
		AND snmp_query.id=snmp_query_graph.snmp_query_id
		AND snmp_query_graph.graph_template_id=graph_templates.id
		AND graph_templates.name LIKE'%$type%'");

	return $dbquery;
}

function linkdiscovery_utilities_action ($action) {
	global $config;

	if ($action == 'linkdiscovery_clear') {
			include_once($config["library_path"] . "/api_tree.php");

		$leaf_id = read_config_option("linkdiscovery_tree");
		// query the host id to remove from the tree
		$dbquery = db_fetch_assoc("SELECT id from plugin_linkdiscovery_hosts ORDER by id");
		if (sizeof($dbquery) > 0) {
/* api_tree_delete_content - given a tree and a branch/leaf, recursively remove all elements
 * @arg $tree_id - The tree to remove from
 * @arg $leaf_id - The branch to remove
 * @returns - null */
//			api_tree_delete_node_content($tree_id, $leaf_id);
		$tree_id = read_config_option("linkdiscovery_tree");
		$sub_tree_id = read_config_option("linkdiscovery_sub_tree");
	
	// fetch tree_items, if no return that mean the location has to be in the root tree
		if ($sub_tree_id <> 0)
		{
			$parent = db_fetch_row('SELECT parent FROM graph_tree_items WHERE graph_tree_id = ' . $tree_id. ' AND host_id=0 AND id='.$sub_tree_id);
			if ( sizeof($parent) == 0 ) {
				api_tree_delete_node_content($tree_id, 0 );
			} else api_tree_delete_node_content( $parent, $sub_tree_id );
		} else { // for sure it's on tree, root one
				api_tree_delete_node_content($tree_id, 0 );
		}

			db_execute('DELETE FROM plugin_linkdiscovery_hosts');
			db_execute('DELETE FROM plugin_linkdiscovery_intf');
		}
	
		top_header();
		utilities();
		bottom_footer();
	}
	return $action;
}

function linkdiscovery_utilities_list () {
	global $colors;

	html_header(array("LinkDiscovery Results"), 2);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=linkdiscovery_clear'>Clear LinkDiscovery Results</a>
		</td>
		<td class="textArea">
			This will clear the results from the Link Discovery data.
		</td>
	</tr>
	<?php
}

function linkdiscovery_device_remove( $hosts_id ){
	//array(1) { [0]=> string(4) "1921" } device remove : 
	if( sizeof($hosts_id) ) {
		foreach( $hosts_id as $host_id) {
			// remove host from plugin_linkdiscovery_hosts and plugin_linkdiscovery_intf
			db_execute("DELETE FROM plugin_linkdiscovery_hosts where id=".$host_id );
			db_execute("DELETE FROM plugin_linkdiscovery_intf where host_id_dst=".$host_id );
			db_execute("DELETE FROM plugin_linkdiscovery_intf where host_id_src=".$host_id );
		}
	}

	return $hosts_id;
}

?>

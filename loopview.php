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

$guest_account = true;
chdir('../../');
include("./include/auth.php");
include_once($config['base_path'] . '/plugins/linkdiscovery/setup.php');

linkdiscovery_check_upgrade();

/* ================= input validation ================= */
input_validate_input_number(get_request_var("page"));

// loop is second on IP adresse
// not on technical network, no link between IP and Loop number
if (isset_request_var('loop')) {
	set_request_var('loop', sanitize_search_string(get_request_var("loop")) );
}

// remember these search fields in session vars so we don't have to keep passing them around 
load_current_session_value("loop", "sess_loop_loop", "");
load_current_session_value("page", "sess_loop_current_page", "1");

$sql_where  = '';
$loop       		= get_request_var("loop");

/*
		'-1' => __('Any', 'monitor'),
		'0'  => __('None', 'monitor'),
		'1'  => __('Low', 'monitor'),
		'2'  => __('Medium', 'monitor'),
		'3'  => __('High', 'monitor'),
		'4'  => __('Mission Critical', 'monitor')
*/
// take only cisco device
$host_sql_query = "SELECT id, description, hostname, monitor_criticality, snmp_sysDescr FROM host
					WHERE monitor_criticality = 4
					AND snmp_sysDescr LIKE 'cisco%'
					ORDER BY host.description";
$hosts_result = db_fetch_assoc($host_sql_query);
// setup the page number to view, max 6
$Nb_Line_Per_Page = 6;
$page = get_request_var('page');	
$row = 1;
$max_pages = 1;

$spanningtree=0;

if ($loop != '' && $loop != NULL ) {
loop_log( 'PdB loop query: '. $hosts_result[$loop]['description'] .' row: '.$row.' page: '.$max_pages); 
	// query the Loop root to have all the first neigbord
	$host_result = query_host($hosts_result[$loop]['id']); // give the loop base information PdB
	
	$loop_array = array(); // array of all line in a loop
	$line_array = array(); // array of all device in a line
	
	// result as $line
	/* 
	[intf_src] => TenGigabitEthernet1/1/1 
	[intf_src_indx] => 2 
	[desc_dst] => se-hdv-4001 
	[intf_dst] => GigabitEthernet1/0/25 
	[id_dst] => 677 
	[Criticality] => 0 ) 
	*/
	// parse eache line of the PdB
	$row_count = 0;
	foreach ( $host_result as $line ) {
	// init the first object of the line
	// do not store other pdb or core
		if( $line['Criticality'] == 4 )
			continue;
			// check if the device allready exist int the loop_array
			$found = array_search( $line['id_dst'], array_column_recursive($loop_array, 'id_dst') );
	//loop_log( 'parse_switch line exist1: '. print_r( array_column_recursive($loop_array, 'id_dst'), true ) );
			if( $found !== false ) {
	//loop_log( 'parse_switch line exist2: '. $line['id_dst'].' '.$found );
				continue;
			}
		array_push( $line_array, $line );
		parse_switch($line);
		$row_count++;
		$loop_array[] = $line_array;
	//loop_log( 'Each line result: '. print_r($line_array, true) ); 
		$line_array = [];
	}
	loop_log( 'Each Loop result: '. print_r($loop_array, true) ); 
	
	$row = count($loop_array);
	$max_pages = ceil($row/$Nb_Line_Per_Page);
}
general_header();

?>
<script type="text/javascript">
<!--

function applyFilterChange() {
	strURL = '?header=false&loop=' + $('#loop').val();
	strURL += '&page=1';
		loadPageNoHeader(strURL);
}

function clearFilter() {
	<?php
		kill_session_var("sess_loop_loop");

		unset($_REQUEST["sess_loop_loop"]);
	?>
	strURL  = 'loopview.php?header=false&page=1&clear=1';
	loadPageNoHeader(strURL);
}
</script>

<?php
// TOP DEVICE SELECTION
html_start_box('<strong>Filters</strong>', '100%', '', '3', 'center', '');

?>
	<meta charset="utf-8"/>
		<td class="noprint">
		<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $config['url_path'];?>plugins/linkdiscovery/loopview.php?header=false">
			<table width="100%" cellpadding="0" cellspacing="0">

			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Loop:&nbsp;
				</td>
				<td width="1">
					<select id="loop">
						<?php
						if (sizeof($hosts_result) > 0) {
							foreach ($hosts_result as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var("loop") == $key) { print " selected"; } print ">" . $value['description'] . "</option>\n";
							}
						}
						?>
					</select>
				</td>
				<td nowrap style='white-space: nowrap;'>
					<input id='Go' type='button' value='<?php print __('Go');?>' onClick='applyFilterChange()'>
				</td>
			</tr>
		</table>
	</form>
	</td>
</tr>
<?php
html_end_box();

if ($loop != '' && $loop != NULL ) {
	html_start_box('', '100%', '', '3', 'center', '');
	/* html_nav_bar - draws a navigation bar which includes previous/next links as well as current
		page information
	@arg $base_url - the base URL will all filter options except page (should include url_path)
	@arg $max_pages - the maximum number of pages to display
	@arg $current_page - the current page in the navigation system
	@arg $rows_per_page - the number of rows that are displayed on a single page
	@arg $total_rows - the total number of rows in the navigation system
	@arg $object - the object types that is being displayed
	@arg $page_var - the object types that is being displayed
	@arg $return_to - paint the resulting page into this dom object
	@arg $page_count - provide a page count 
	*/
	
	// check if weathermap is present, if so use it
	if( db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='weathermap' AND status=1") ) {
		$nav = html_nav_bar('loopview.php', $max_pages, get_request_var('page'), $Nb_Line_Per_Page, $row );
	
		print $nav;
		draw_weathermap($loop_array);
	} else {
		print "<tr><td style='padding: 4px; margin: 4px;' colspan=11><center><strong>Weathermap is not installed or enabled</strong></center></td></tr>";
	}

	html_end_box(false);
}
bottom_footer();

function query_host( $id ) {
	/* 
	for each host we need:
	source interfaces
	destination interfaces
	destination hosts
	link id based on graph of rrd number
	
	all as an array:
intf_src					intf_src_indx	id_src	desc_dst		intf_dst					id_dst	Criticality 	
TenGigabitEthernet1/1/1 	2 				4954 	se-hdv-4001 	GigabitEthernet1/0/25 		6089 	0
TenGigabitEthernet1/1/2 	3 				4954 	se-hdv-4005 	GigabitEthernet1/0/25 		6069 	0
TenGigabitEthernet1/1/3 	4 				4954 	se-hdv-4010 	GigabitEthernet1/0/25 		6038 	0
TenGigabitEthernet1/1/4 	5 				4954 	se-hdv-4015 	GigabitEthernet1/0/25 		6035 	0
TenGigabitEthernet1/1/29 	30 				4954 	sre-vbb-30 		TenGigabitEthernet1/1/26 	3160 	4
TenGigabitEthernet1/1/30 	31 				4954 	sre-vbb-30 		TenGigabitEthernet2/1/27 	3160 	4
TenGigabitEthernet2/1/1 	34 				4954 	se-hdv-4003 	GigabitEthernet1/0/25 		5999 	0
TenGigabitEthernet2/1/2 	35 				4954 	se-hdv-4009 	GigabitEthernet1/0/28 		5979 	0
TenGigabitEthernet2/1/3 	36 				4954 	se-hdv-4014 	GigabitEthernet1/0/28 		5943 	0
TenGigabitEthernet2/1/4 	37 				4954 	se-mad-4001 	GigabitEthernet1/0/28 		5929 	0
TenGigabitEthernet2/1/29 	62 				4954 	sre-vbb-30 		TenGigabitEthernet2/1/26 	3160 	4
TenGigabitEthernet2/1/30 	63 				4954 	sre-vbb-30 		TenGigabitEthernet1/1/27 	3160 	4	*/
	$loop_sql_query = "SELECT intf_src.field_value AS 'intf_src', intf_src.snmp_index AS 'intf_src_indx', discointf.host_id_src AS 'id_src',
		host_dst.description AS 'desc_dst', 
		intf_dst.field_value AS 'intf_dst',
		host_dst.id AS 'id_dst', host_dst.monitor_criticality AS 'Criticality'
		FROM plugin_linkdiscovery_intf discointf
		INNER JOIN host host_dst ON host_dst.id=discointf.host_id_dst
		INNER JOIN host_snmp_cache intf_src ON intf_src.host_id=discointf.host_id_src
		INNER JOIN host_snmp_cache intf_dst ON intf_dst.host_id=discointf.host_id_dst
		WHERE discointf.host_id_src=".$id." 
		AND intf_src.field_name='ifName' 
		AND intf_dst.field_name='ifName' 
		AND intf_src.snmp_index=discointf.snmp_index_src 
		AND intf_dst.snmp_index=discointf.snmp_index_dst	
		AND intf_src.snmp_query_id IN (SELECT id FROM snmp_query WHERE name LIKE '%nterface%')
		ORDER BY discointf.snmp_index_src 
	";
/*
host test is 9400 B40
ID of the RRD in relation of the grpah_id=20398
SELECT DISTINCT dtd.local_data_id, dtd.name_cache, dtd.data_source_path
FROM data_template_data AS dtd 
INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id=dtd.local_data_id 
INNER JOIN graph_templates_item AS gti ON dtr.id=gti.task_item_id 
WHERE gti.local_graph_id = 20398 AND dtd.local_data_id > 0

20669 	sre-b40-140 Te1/1/1 - Traffic - SE-B40-TEST / WS-C... 	<path_rra>/sre-b40-140_traffic_in_20669.rrd
*/
	
	$result = db_fetch_assoc($loop_sql_query);
loop_log( 'query_host result: '. print_r($result, true) ); 

	return $result;
}

function draw_weathermap( $loop_array ) {
	global $config, $hosts_result, $loop, $row, $max_pages, $pages;
	$originX = 250; // horizontal
	$originY = 1300; // start from bottom
	$spaceX = 280; // horizontal 530 - 250 = 280
	$spaceY = 150;
	
	$header = $config['base_path'] . '/plugins/linkdiscovery/header_weathermap.txt';
	$dest = $config['base_path'] . '/plugins/weathermap/configs/Loop-'.$hosts_result[$loop]['description'].'.conf';
	
	loop_log( 'draw_weathermap from: '. $header.' To: '. $dest); 

	$header_src = file_get_contents($header);
	$title = 'TITLE Boucle '.$hosts_result[$loop]['description']."\n"; 

	$result = file_put_contents($dest, $title);
	$result += file_put_contents($dest, $header_src, FILE_APPEND);

$data = "\n\tNODE ".$hosts_result[$loop]['description']."m1
        LABEL ".$hosts_result[$loop]['description']."m1
        ICON 50 50 images/objects/cisco-switch-vss.png
        TARGET cactihost:".$hosts_result[$loop]['id']
		."\n\tPOSITION 50 1100\n\n"
		."\tNODE ".$hosts_result[$loop]['description']."m2
        LABEL ".$hosts_result[$loop]['description']."m2
        ICON 50 50 images/objects/cisco-switch-vss.png
        TARGET cactihost:".$hosts_result[$loop]['id']
		."\n\tPOSITION 1750 1100\n\n";	
	$result = file_put_contents($dest, $data, FILE_APPEND);
	loop_log( 'draw_weathermap result: '. $result );
		
//LOOPVIEW PdB loop query: 9400 row: 12 max_pages: 2

	foreach( $loop_array as $index_loop=>$line ){
loop_log( 'draw_weathermap line: '. print_r($line, true).' index: '.$index_loop ); 
		$last_label =  $hosts_result[$loop]['description']."m1"; // fixe label for position
		$spaceY = -150*($index_loop+1);
		
		// we have to loop for each device, but not the last one (it's back to the origin, and only need for the link, not for the device)
		for( $index_line=0, $line_size = count($line)-1; $index_line<$line_size; $index_line++){
			$device = $line[$index_line];
			$next_device = $line[$index_line+1];
loop_log( 'draw_weathermap device: '. print_r($device, true). ' last_label: '.$last_label.' index_line: '.$index_line ); 
			$data = "\n\tNODE ".$device['desc_dst']
			."\n\tLABEL ".$device['desc_dst']
			."\n\tTARGET cactihost:".$device['id_dst']
			."\n\tPOSITION " . $last_label . ' '.$spaceX.' '. $spaceY."\n\n";	
			
			// record to file
			$result = file_put_contents($dest, $data, FILE_APPEND);
			
			$data = "\n\tNODE ".$device['desc_dst']."_in"
			."\n\tLABEL ".$device['intf_dst']
			."\n\tICON none"
			."\n\tPOSITION ".$device['desc_dst'] ." -40 -15\n\n";
			$result = file_put_contents($dest, $data, FILE_APPEND);  // 38 x 16
			
			$last_label = $device['desc_dst']; // save current label for next device in line
			$spaceY = 0;

			$data = "\n\tNODE ".$device['desc_dst']."_out"
			."\n\tLABEL ".$next_device['intf_src']
			."\n\tICON none"
			."\n\tPOSITION ".$device['desc_dst'] ." 40 -15\n\n";
			$result = file_put_contents($dest, $data, FILE_APPEND);  // 38 x 16

		}
	}

/*
each record look like this:
an array of below data is the line_array
 [intf_src] => TenGigabitEthernet2/1/4 [intf_src_indx] => 37 [id_src] => 9400 [desc_dst] => se-mad-4001 [intf_dst] => GigabitEthernet1/0/28 [id_dst] => 655 [Criticality] => 0 )
 
 And many line array compose the loop_array
/*

Position of the 2 root device
NODE SRE-B45-145m1
        LABEL SRE-B45-145m1
        ICON 50 50 images/objects/cisco-switch-vss.png
        TARGET cactihost:651
        POSITION 50 1000

NODE SRE-B45-145m2
        LABEL SRE-B45-145m2
        ICON 50 50 images/objects/cisco-switch-vss.png
        TARGET cactihost:651
        POSITION 1550 1000

        INFOURL /cacti/graph.php?rra_id=all&local_graph_id=19064
        OVERLIBGRAPH /cacti/graph_image.php?rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300&local_graph_id=
19064

		position is horizontal vertical
		0,0 is top left
		space between line is 150
		that mean 6 line 150, 300, 450, 600, 750, 900, origin at 1050 bottom at 1200
		decrase value as interface incrase
		page a based on 34 'line', that mean 6 'line' per page
*/
}

/* funtion called when looking for the connection on a switch
it's a recursive one.
The $line['id_dst'] is the one where we need to gather all link
return what's need to make the map:
output port
destination device on ouput port
Input port on destination device
it's an array of n devices

as database query, it's alway a ID (host_dst.id, intf_dst.snmp_index, intf_src.snmp_index on dest device)
*/
function parse_switch( $line ) {
	global $line_array, $loop_array, $hosts_result, $loop;

loop_log( 'parse_switch line result: '. print_r($line_array, true) );
loop_log( 'parse_switch : '. print_r($line, true) ); // 6089
	if ($line['id_dst'] == reset($line_array)['id_src'] ) { // we are back to start
loop_log( 'parse_switch line start over' ); 
		return true; // is when we reach the start over
	}
	// parse the switch
	$result = query_host($line['id_dst']);
	
	$ret = array();
	foreach( $result as $peer ){
		if( $peer['id_dst'] == end($line_array)['id_src'] ) { // check if we have the previous switch, happen on every uplink, two side ;)
//loop_log( 'parse_switch peer drop: '. print_r($peer, true) );
			continue;
		}
		
		// if we reach the start of the loop keep the link id
		if( $peer['id_dst'] != $hosts_result[$loop]['id'] ) {
		// check if the device allready exist int the loop_array
			$found = array_search( $peer['id_dst'], array_column_recursive($loop_array, 'id_dst') );
//loop_log( 'parse_switch peer exist1: '. print_r( array_column_recursive($loop_array, 'id_dst'), true ) );
			if( $found !== false ) {
//loop_log( 'parse_switch peer exist2: '. $peer['id_dst'].' '.$found );
				continue;
			}
		}
		

		array_push( $line_array, $peer );
//loop_log( 'parse_switch peer: '. print_r($peer, true) ); 
//loop_log( 'parse_switch line result peer: '. print_r($line_array, true) ); 
		$start_over = parse_switch($peer);
		if ( $start_over ) {
//loop_log( 'parse_switch start_over : '. print_r($line_array, true) );
			return true;
		}

	}
}

function array_column_recursive(array $haystack, $needle) {
    $found = [];
    array_walk_recursive($haystack, function($value, $key) use (&$found, $needle) {
        if ($key == $needle)
            $found[] = $value;
    });
    return $found;
}


function loop_log( $text ){
    $dolog = read_config_option('linkdiscovery_loop_log_debug');
    if( $dolog ) cacti_log( $text, false, "LOOPVIEW" );

}

?>

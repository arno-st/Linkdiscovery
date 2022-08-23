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

// loop is second on IP adresse
if (isset_request_var('loop')) {
	set_request_var('loop', sanitize_search_string(get_request_var("loop")) );
}

// remember these search fields in session vars so we don't have to keep passing them around 
load_current_session_value("loop", "sess_loop_loop", "");

$sql_where  = '';
$loop       		= get_request_var("loop");

$host_sql_query = "SELECT * FROM host
					WHERE monitor_criticality = 3
					ORDER BY host.description";
$host_result = db_fetch_assoc($host_sql_query);

if ($loop == '' || $loop == NULL ) {
	$loop='0';
}
$spanningtree=0;
// query the Loop root to have all the first neigbord
$result = query_host($host_result[$loop]['id']); // give the root information
cacti_log( 'First loop result: '. print_r($result, true), TRUE, 'LOOPVIEW'); 
/*
 First loop result: Array ( 
 [0] => Array ( [intf_src] => TenGigabitEthernet1/1/1 [desc_dst] => se-amv-3001 [intf_dst] => GigabitEthernet1/0/25 [id_dst] => 777 [Criticality] => 0 ) 
 [1] => Array ( [intf_src] => TenGigabitEthernet1/1/2 [desc_dst] => se-amv-3008 [intf_dst] => TenGigabitEthernet1/1/1 [id_dst] => 9455 [Criticality] => 0 ) 
*/

// start spantree tree work
	$spantree=array();
// parse each interface from root loop
	foreach( $result as $interface ){
		$next_host = $interface;
cacti_log( ' Work on: '. $interface['intf_src'], TRUE, 'LOOPVIEW');

		$spantree[$interface['intf_src']][] = $next_host; // store the interface to spantree
cacti_log( ' spantree: '. print_r($spantree, true), TRUE, 'LOOPVIEW');
		// if that host is core or PdB, end here
		if( $next_host['Criticality'] == 4 || $next_host['Criticality'] == 3 ) {
cacti_log( ' drop at Interface: '. print_r($next_host, true), TRUE, 'LOOPVIEW');
			continue;
		}


cacti_log( ' Do while for: '. print_r($next_host, true), TRUE, 'LOOPVIEW');
		// reset spanningtree on each interface
		$spanningtree=0;
		do {
			$next_host = query_host($next_host['id_dst']);
cacti_log( 'Interface next_host: '. print_r($next_host, true), TRUE, 'LOOPVIEW');
			// end if no more host or if we reach the first host again
			if( sizeof($next_host) == 0 || $next_host['Criticality'] == 4 || $next_host['Criticality'] == 3 
				|| $next_host['desc_dst'] == $interface['desc_dst'] ) {
cacti_log( 'Interface spanningtree exit: '. print_r($spantree, true), TRUE, 'LOOPVIEW');
				continue 2;
			}
cacti_log( ' Work spanning on: '. print_r($next_host, true), TRUE, 'LOOPVIEW');
			array_push( $spantree[$interface['intf_src']][$next_host['desc_dst']], $next_host );
			$spanningtree++;
		} while( $spanningtree < 6 );
cacti_log( 'Interface spanningtree: '. print_r($spantree, true), TRUE, 'LOOPVIEW');
	}

cacti_log( 'Switch Spanningtree: '. print_r($spantree, true), TRUE, 'LOOPVIEW');

general_header();

?>
<script type="text/javascript">
<!--

function applyFilterChange() {
	strURL = '?header=false&loop=' + $('#loop').val();
		loadPageNoHeader(strURL);
}

function clearFilter() {
	<?php
		kill_session_var("sess_loop_loop");

		unset($_REQUEST["sess_loop_loop"]);
	?>
	strURL  = 'loopview.php?header=false&rows=-1&page=1&clear=1';
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
					<select id="loop" onChange="applyFilterChange()">
						<?php
						if (sizeof($host_result) > 0) {
							foreach ($host_result as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var("loop") == $key) { print " selected"; } print ">" . $value['description'] . "</option>\n";
							}
						}
						?>
					</select>
				</td>
				<td nowrap style='white-space: nowrap;'>
					<input id='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()'>
				</td>
			</tr>
		</table>
	</form>
	</td>
</tr>
<?php
html_end_box();


html_start_box('', '100%', '', '3', 'center', '');

/*

intf_src.field_value AS 'intf_src',
	host_dst.description AS 'desc_dst', 
	intf_dst.field_value AS 'intf_dst' 
*/
$display_text = array("Interface", "Description Destination", "Interface Destination");
html_header($display_text );

if (sizeof($result)) {
        $class   = 'odd';
        foreach($result as $row) {
                ($class == 'odd' )?$class='even':$class='odd';

                print"<tr class='$class tablerow'>";
                print"<td style='padding: 4px; margin: 4px;'>"
                        . $row['intf_src'] . '</td>
                        <td>' . $row['desc_dst'] . '</td>
                        <td>' . $row['intf_dst'] . '</td>
                        <td align="right">';

                print "</tr>";
	}
}else{
	print "<tr><td style='padding: 4px; margin: 4px;' colspan=11><center>There are no Hosts to display!</center></td></tr>";
}

/*
$display_text = array(
	"hostname_src_id" => array("Host Source ID", "ASC"),
	"hostname_src" => array("Hostname Source", "ASC"),
	"desc_src" => array("Description Source", "ASC"),
	"intf_src" => array("Interface Source", "ASC"),
	"hostname_dst" => array("Hostname Destination", "ASC"),
	"desc_dst" => array("Description Destination", "ASC"),
	"intf_dst" => array("Interface Destination", "ASC"),
	"nosort" => array("", ""));

html_header_sort($display_text, get_request_var("sort_column"), get_request_var("sort_direction"), false);
*/

html_end_box(false);

//print $nav;

bottom_footer();

function query_host( $id ) {
	$loop_sql_query = "SELECT intf_src.field_value AS 'intf_src', intf_src.snmp_index AS 'intf_src_indx',
		host_dst.description AS 'desc_dst', 
		intf_dst.field_value AS 'intf_dst',
		host_dst.id AS 'id_dst', host_dst.monitor_criticality AS 'Criticality'
		FROM plugin_linkdiscovery_intf discointf
		INNER JOIN host host_dst ON host_dst.id=discointf.host_id_dst
		INNER JOIN host_snmp_cache intf_src ON intf_src.host_id=discointf.host_id_src
		INNER JOIN host_snmp_cache intf_dst ON intf_dst.host_id=discointf.host_id_dst
		WHERE discointf.host_id_src=".$id." 
		AND intf_src.field_name='ifDescr' 
		AND intf_dst.field_name='ifDescr' 
		AND intf_src.snmp_index=discointf.snmp_index_src 
		AND intf_dst.snmp_index=discointf.snmp_index_dst	
		AND intf_src.snmp_query_id IN (SELECT id FROM snmp_query WHERE name LIKE '%nterface%')
		ORDER BY discointf.snmp_index_src 
	";
	
	$result = db_fetch_assoc($loop_sql_query);
	return $result;
}
?>

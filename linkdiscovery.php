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

linkdiscovery_setup_table();
linkdiscovery_check_upgrade();

/* ================= input validation ================= */
input_validate_input_number(get_request_var("page"));
input_validate_input_number(get_request_var("rows"));
/* ==================================================== */

// clean up host string 
if (isset_request_var('hostname_src') ) {
	set_request_var('hostname_src', sanitize_search_string(get_request_var("hostname_src")) );
}

if (isset_request_var('hostname_dst')) {
	set_request_var('hostname_dst', sanitize_search_string(get_request_var("hostname_dst")) );
}

// clean up sort_column 
if (isset_request_var('sort_column')) {
	set_request_var('sort_column', sanitize_search_string(get_request_var("sort_column")) );
}

// clean up search string 
if (isset_request_var('sort_direction')) {
	set_request_var('sort_direction', sanitize_search_string(get_request_var("sort_direction")) );
}

// clean up unknown_intf
if (isset_request_var('unknown_intf')) {
	set_request_var('unknown_intf', sanitize_search_string(get_request_var("unknown_intf")) );
}

// remember these search fields in session vars so we don't have to keep passing them around 
load_current_session_value("page", "sess_linkdiscovery_current_page", "1");
load_current_session_value("hostname_src", "sess_linkdiscovery_host", "");
load_current_session_value("hostname_dst", "sess_linkdiscovery_host_dst", "");
load_current_session_value("rows", "sess_linkdiscovery_rows", "-1");
load_current_session_value("sort_column", "sess_linkdiscovery_sort_column", "host_src.id");
load_current_session_value("sort_direction", "sess_linkdiscovery_sort_direction", "ASC");

$sql_where  = '';
$hostname_src       = get_request_var("hostname_src");
$hostname_dst       = get_request_var("hostname_dst");
$unknown_intf 		= get_request_var("unknown_intf");

$query_unknown = '';

if( $unknown_intf == '' || $unknown_intf == '0' || $unknown_intf == NULL ) {
	$unknown_intf='0';
} else 	$query_unknown = " AND discointf.snmp_index_dst=0 ";


if ($hostname_src != '') {
	$sql_where .= " AND " . "host_src.hostname like '%$hostname_src%'";
}
if ($hostname_dst != '') {
	$sql_where .= " AND " . "host_dst.hostname like '%$hostname_dst%'";
}

general_header();

$total_rows = db_fetch_cell("SELECT
	COUNT(host_src.id)
	FROM plugin_linkdiscovery_intf discointf, host host_dst, host host_src, host_snmp_cache intf_src, host_snmp_cache intf_dst
	WHERE host_src.id=discointf.host_id_src and host_dst.id=discointf.host_id_dst
    AND intf_src.host_id=host_src.id and intf_dst.host_id=host_dst.id 
    AND intf_src.field_name='ifDescr' AND intf_dst.field_name='ifDescr' 
    AND intf_src.snmp_index=discointf.snmp_index_src
	AND intf_dst.snmp_index IN (discointf.snmp_index_dst, discointf.snmp_index_dst=0)	
	AND intf_src.snmp_query_id=1
	$query_unknown 
	$sql_where");

/* if the number of rows is -1, set it to the default */
if (get_request_var("rows") == "-1") {
	$per_row = read_config_option('num_rows_table'); //num_rows_device');
}else{
	$per_row = get_request_var('rows');
}
$page = ($per_row*(get_request_var('page')-1));
$sql_limit = $page . ',' . $per_row;

$sortby  = get_request_var("sort_column");
if( strcmp($sortby, 'hostname_src_id')  == 0) {
	$sortby="host_src.id";
} else if( strcmp($sortby, 'hostname_src')  == 0) {
	$sortby="host_src.hostname";
} else if( strcmp($sortby, 'desc_src')  == 0) {
	$sortby="host_src.description";
} else if( strcmp($sortby, 'intf_src')  == 0) {
	$sortby="discointf.snmp_index_src";
} else if( strcmp($sortby, 'hostname_dst')  == 0) {
	$sortby="host_dst.hostname";
} else if( strcmp($sortby, 'desc_dst')  == 0) {
	$sortby="host_dst.description";
} else if( strcmp($sortby, 'intf_dst')  == 0) {
	$sortby="discointf.snmp_index_dst";
} else $sortby="host_src.id ASC, discointf.snmp_index_src";

$sql_query = "SELECT host_src.id, 
		host_src.hostname AS 'hostname_src',host_src.description AS 'desc_src', intf_src.field_value AS 'intf_src',
		host_dst.hostname AS 'hostname_dst', host_dst.description AS 'desc_dst', 
		IF(discointf.snmp_index_dst=0 ,'Unknown',intf_dst.field_value) AS 'intf_dst' 
		FROM plugin_linkdiscovery_intf discointf, host host_dst, host host_src, host_snmp_cache intf_src, host_snmp_cache intf_dst
		WHERE host_src.id=discointf.host_id_src and host_dst.id=discointf.host_id_dst
        AND intf_src.host_id=host_src.id and intf_dst.host_id=host_dst.id 
        AND intf_src.field_name='ifDescr' AND intf_dst.field_name='ifDescr' 
        AND intf_src.snmp_index=discointf.snmp_index_src 
		AND intf_dst.snmp_index IN (discointf.snmp_index_dst, discointf.snmp_index_dst=0)	
		AND intf_src.snmp_query_id=1
		$query_unknown 
		$sql_where 
		ORDER BY " . $sortby . " " . get_request_var("sort_direction") . "
		LIMIT " . $sql_limit;

$result = db_fetch_assoc($sql_query);
?>
<script type="text/javascript">
<!--

function applyFilterChange(objForm) {
	strURL = '?header=false&hostname_src=' + objForm.host.value;
	strURL += '&rows=' + objForm.rows.value;
	strURL +=  '&unknown_intf=' + objForm.unknown_intf.value;
	strURL +=  '&hostname_dst=' + objForm.hostname_dst.value;
	document.location = strURL;
}

-->
</script>
<?php
// TOP DEVICE SELECTION
html_start_box('<strong>Filters</strong>', '100%', '', '3', 'center', '');

?>
<tr class='even'>
	<td>
	<form id=''linkdiscovery' action="<?php print $config['url_path'];?>plugins/linkdiscovery/linkdiscovery.php?header=false">
		<table width="100%" cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Hostname Source:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="hostname_src" size="25" value="<?php print get_request_var("hostname_src");?>">
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Hostname Destination:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="hostname_dst" size="25" value="<?php print get_request_var("hostname_dst");?>">
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;&nbsp;Unknown Interface Only:&nbsp;&nbsp;
				</td>
				<td width="1">
					<input type="checkbox" name="unknown_intf" value="1" <?php ($unknown_intf=='1')?print " checked":print "" ?>>
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Rows:&nbsp;
				</td>
				<td width="1">
					<select name="rows" onChange="applyFilterChange(document.form)">
						<option value="-1"<?php if (get_request_var("rows") == "-1") {?> selected<?php }?>>Default</option>
						<?php
						if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
						}
						?>
					</select>
				</td>
				<td nowrap style='white-space: nowrap;'>
					<input type="submit" value="Go" title="Set/Refresh Filters">
					<input id='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()'>
				</td>
			</tr>
		</table>
	</form>
		<script type='text/javascript'>

		function clearFilter() {
			<?php
				kill_session_var("sess_linkdiscovery_host");
				kill_session_var("sess_linkdiscovery_host_dst");
				kill_session_var("sess_linkdiscovery_rows");
				kill_session_var("sess_linkdiscovery_sort_column");
				kill_session_var("sess_linkdiscovery_sort_direction");

				unset($_REQUEST["hostname_src"]);
				unset($_REQUEST["hostname_dst"]);
				unset($_REQUEST["rows"]);
				unset($_REQUEST["sort_column"]);
				unset($_REQUEST["sort_direction"]);
				unset($_REQUEST["unknown_intf"]);
				$unknown_intf=null;
			?>
			strURL  = 'linkdiscovery.php?header=false&rows=-1&page=1&clear=1';
			loadPageNoHeader(strURL);
		}
		</script>

	</td>
</tr>
<?php
html_end_box();


html_start_box('', '100%', '', '3', 'center', '');

$nav = html_nav_bar('linkdiscovery.php?view', MAX_DISPLAY_PAGES, get_request_var('page'), $per_row, $total_rows, 12, __('Devices'), 'page', 'main');

print $nav;

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

$i=0;
if (sizeof($result)) {
	foreach($result as $row) {
		form_alternate_row_color($colors["alternate"], $colors["light"], $i); $i++;
		if ($row["hostname_src"] == "") {
			$row["hostname_src"] = "Not Detected";
		}

		print"<td style='padding: 4px; margin: 4px;'>" 
			. $row['id'] . "</td>
			<td>" . $row['hostname_src'] . '</td>
			<td>' . $row['desc_src'] . '</td>
			<td>' . $row['intf_src'] . '</td>
			<td>' . $row['hostname_dst'] . '</td>
			<td>' . $row['desc_dst'] . '</td>
			<td>' . $row['intf_dst'] . '</td>
			<td align="right">';

		print "</td>";
	}
}else{
	print "<tr><td style='padding: 4px; margin: 4px;' colspan=11><center>There are no Hosts to display!</center></td></tr>";
}

html_end_box(false);

print $nav;

bottom_footer();

?>

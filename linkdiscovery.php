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

linkdiscovery_setup_table();
linkdiscovery_check_upgrade();

define("MAX_DISPLAY_PAGES", 21);

/* ================= input validation ================= */
input_validate_input_number(get_request_var("page"));
input_validate_input_number(get_request_var("rows"));
/* ==================================================== */

/* clean up host string */
if (isset($_REQUEST["hostname_src"])) {
	$_REQUEST["hostname_src"] = sanitize_search_string(get_request_var("hostname_src"));
}

if (isset($_REQUEST["hostname_dst"])) {
	$_REQUEST["hostname_dst"] = sanitize_search_string(get_request_var("hostname_dst"));
}

/* clean up sort_column */
if (isset($_REQUEST["sort_column"])) {
	$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
}

/* clean up search string */
if (isset($_REQUEST["sort_direction"])) {
	$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
}

/* clean up unknown_intf */
if (isset($_REQUEST["unknown_intf"])) {
	$_REQUEST["unknown_intf"] = sanitize_search_string(get_request_var("unknown_intf"));
}

/* if the user pushed the 'clear' button */
if (isset($_REQUEST["button_clear_x"])) {
	kill_session_var("sess_linkdiscovery_current_page");
	kill_session_var("sess_linkdiscovery_host");
	kill_session_var("sess_linkdiscovery_host_dst");
	kill_session_var("sess_linkdiscovery_rows");
	kill_session_var("sess_linkdiscovery_sort_column");
	kill_session_var("sess_linkdiscovery_sort_direction");

	unset($_REQUEST["page"]);
	unset($_REQUEST["hostname_src"]);
	unset($_REQUEST["hostname_dst"]);
	unset($_REQUEST["rows"]);
	unset($_REQUEST["sort_column"]);
	unset($_REQUEST["sort_direction"]);
	unset($_REQUEST["unknown_intf"]);
	$unknown_intf=null;
}

/* remember these search fields in session vars so we don't have to keep passing them around */
load_current_session_value("page", "sess_linkdiscovery_current_page", "1");
load_current_session_value("hostname_src", "sess_linkdiscovery_host", "");
load_current_session_value("hostname_dst", "sess_linkdiscovery_host_dst", "");
load_current_session_value("rows", "sess_linkdiscovery_rows", "-1");
load_current_session_value("sort_column", "sess_linkdiscovery_sort_column", "host_src.id");
load_current_session_value("sort_direction", "sess_linkdiscovery_sort_direction", "ASC");

$sql_where  = '';
$hostname_src       = get_request_var_request("hostname_src");
$hostname_dst       = get_request_var_request("hostname_dst");
$unknown_intf = get_request_var_request("unknown_intf");

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

include(dirname(__FILE__) . "/general_header.php");

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

$page    = get_request_var_request("page");
if (get_request_var_request("rows") == "-1") {
	$per_row = read_config_option("num_rows_device");
}else{
	$per_row = get_request_var_request("rows");
}

$sortby  = get_request_var_request("sort_column");
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

// user request to export the data
if (isset($_GET['button_export_x'])) {
	$result = db_fetch_assoc("SELECT host_src.id, 
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
		ORDER BY " . $sortby . " " . get_request_var_request("sort_direction"));

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=linkdiscovery_results.csv");
	print "Host source ID,Hostname Source, Description Source, Interface Source, Hostname Destination, Description destination, Interface destination\n";

	foreach ($result as $host) {
		foreach($host as $h=>$r) {
			$host['$h'] = str_replace(',','',$r);
		}
		print $host['id'] . ",";
		print $host['hostname_src'] . ",";
		print $host['desc_src'] . ",";
		print $host['intf_src'] . ",";
		print $host['hostname_dst'] . ",";
		print $host['desc_dst'] . ",";
		print $host['intf_dst'] . "\n";
	}
	exit;
}

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
		ORDER BY " . $sortby . " " . get_request_var_request("sort_direction") . "
		LIMIT " . ($per_row*($page-1)) . "," . $per_row;

$result = db_fetch_assoc($sql_query);
?>
<script type="text/javascript">
<!--

function applyFilterChange(objForm) {
	strURL = '&hostname_src=' + objForm.host.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	strURL = strURL + '&unknown_intf=' + objForm.unknown_intf.value;
	strURL = strURL + '&hostname_dst=' + objForm.hostname_dst.value;
	document.location = strURL;
}

-->
</script>
<?php
// TOP DEVICE SELECTION
html_start_box("<strong>Filters</strong>", "100%", $colors["header"], "3", "center", "");

?>
<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
	<td class="noprint">
	<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $config['url_path'];?>plugins/linkdiscovery/linkdiscovery.php">
		<table width="100%" cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Hostname Source:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="hostname_src" size="25" value="<?php print get_request_var_request("hostname_src");?>">
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Hostname Destination:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="hostname_dst" size="25" value="<?php print get_request_var_request("hostname_dst");?>">
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
						<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
						<?php
						if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
						}
						?>
					</select>
				</td>
				<td nowrap style='white-space: nowrap;'>
					&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
					<input type="submit" name="button_clear_x" value="Clear" title="Reset fields to defaults">
					<input type="submit" name="button_export_x" value="Export" title="Export to a file">
				</td>
			</tr>
		</table>
	</form>
	</td>
</tr>
<?php
html_end_box();

html_start_box("", "100%", $colors["header"], "3", "center", "");

/* generate page list */
$url_page_select = get_page_list($page, MAX_DISPLAY_PAGES, $per_row, $total_rows, "linkdiscovery.php?view");

$nav = "<tr bgcolor='#" . $colors["header"] . "'>
		<td colspan='14'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; "; if ($page > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("linkdiscovery.php?view&hostname_src=$hostname_src" . "&page=" . ($page-1)) . "'>"; } $nav .= "Previous"; if ($page > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textHeaderDark'>
						Showing Rows " . (($per_row*($page-1))+1) . " to " . ((($total_rows < $per_row) || ($total_rows < ($per_row*$page))) ? $total_rows : ($per_row*$page)) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right' class='textHeaderDark'>
						<strong>"; if (($page * get_request_var_request("host_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("linkdiscovery.php?view&hostname_src=$hostname_src" . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("host_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
					</td>\n
				</tr>
			</table>
		</td>
	</tr>\n";

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

html_header_sort($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

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

print $nav;

html_end_box(false);

include_once("./include/bottom_footer.php");

?>

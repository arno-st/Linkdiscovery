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

$guest_account = true;
chdir('../../');
include("./include/auth.php");
include_once($config['base_path'] . '/plugins/linkdiscovery/setup.php');

linkdiscovery_check_upgrade();

/* ================= input validation ================= */
input_validate_input_number(get_request_var("page"));
input_validate_input_number(get_request_var("rows"));
/* ==================================================== */
// clean up phone string 
if (isset_request_var('phone')) {
	set_request_var('phone', sanitize_search_string(get_request_var("phone")) );
}

if (isset_request_var('phone_IP')) {
	set_request_var('phone_IP', sanitize_search_string(get_request_var("phone_IP")) );
}

if (isset_request_var('phone_number')) {
	set_request_var('phone_number', sanitize_search_string(get_request_var("phone_number")) );
}

if (isset_request_var('phone_model')) {
	set_request_var('phone_model', sanitize_search_string(get_request_var("phone_V")) );
}

if (isset_request_var('switch_name')) {
	set_request_var('switch_name', sanitize_search_string(get_request_var("switch_name")) );
}

if (isset_request_var('switch_description')) {
	set_request_var('switch_description', sanitize_search_string(get_request_var("switch_description")) );
}

if (isset_request_var('switch_port')) {
	set_request_var('switch_port', sanitize_search_string(get_request_var("switch_port")) );
}

// clean up sort_column 
if (isset_request_var('sort_column')) {
	set_request_var('sort_column', sanitize_search_string(get_request_var("sort_column")) );
}

// clean up search string 
if (isset_request_var('sort_direction')) {
	set_request_var('sort_direction', sanitize_search_string(get_request_var("sort_direction")) );
}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_linkdiscovery_phone");
		kill_session_var("sess_linkdiscovery_phone_IP");
		kill_session_var("sess_linkdiscovery_phone_number");
		kill_session_var("sess_linkdiscovery_phone_model");
		kill_session_var("sess_linkdiscovery_switch_name");
		kill_session_var("sess_linkdiscovery_switch_description");
		kill_session_var("sess_linkdiscovery_switch_port");
		kill_session_var("sess_linkdiscovery_rows");
		kill_session_var("sess_linkdiscovery_sort_column");
		kill_session_var("sess_linkdiscovery_sort_direction");

		unset($_REQUEST["phone"]);
		unset($_REQUEST["phone_IP"]);
		unset($_REQUEST["phone_number"]);
		unset($_REQUEST["phone_model"]);
		unset($_REQUEST["switch_name"]);
		unset($_REQUEST["switch_description"]);
		unset($_REQUEST["switch_port"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	} else {
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += phone_request_check_changed('phone', 'sess_linkdiscovery_phone');
		$changed += phone_request_check_changed('phone_IP', 'sess_linkdiscovery_phone_IP');
		$changed += phone_request_check_changed('phone_number', 'sess_linkdiscovery_phone_number');
		$changed += phone_request_check_changed('phone_model', 'sess_linkdiscovery_phone_model');
		$changed += phone_request_check_changed('switch_name', 'sess_linkdiscovery_switch_name');
		$changed += phone_request_check_changed('switch_description', 'sess_linkdiscovery_switch_description');
		$changed += phone_request_check_changed('switch_port', 'sess_linkdiscovery_switch_port');
		$changed += phone_request_check_changed('rows', 'sess_linkdiscovery_rows');
		$changed += phone_request_check_changed('sort_column', 'sess_thold_sort_column');
		$changed += phone_request_check_changed('sort_direction', 'sess_thold_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}
// remember these search fields in session vars so we don't have to keep passing them around 
load_current_session_value("page", "sess_linkdiscovery_current_page", "1");
load_current_session_value("phone", "sess_linkdiscovery_phone", "");
load_current_session_value("phone_IP", "sess_linkdiscovery_phone_IP", "");
load_current_session_value("phone_number", "sess_linkdiscovery_phone_number", "");
load_current_session_value("phone_model", "sess_linkdiscovery_phone_model", "");
load_current_session_value("switch_name", "sess_linkdiscovery_switch_name", "");
load_current_session_value("switch_description", "sess_linkdiscovery_switch_description", "");
load_current_session_value("switch_port", "sess_linkdiscovery_switch_port", "");
load_current_session_value("rows", "sess_linkdiscovery_rows", "-1");
load_current_session_value("sort_column", "sess_linkdiscovery_sort_column", "phone_number");
load_current_session_value("sort_direction", "sess_linkdiscovery_sort_direction", "ASC");

$sql_where  = '';
$phone       		= get_request_var("phone");
$phone_IP      		= get_request_var("phone_IP");
$phone_number       = get_request_var("phone_number");
$phone_model       	= get_request_var("phone_model");
$switch_name       	= get_request_var("switch_name");
$switch_description = get_request_var("switch_description");
$switch_port       	= get_request_var("switch_port");

if ($phone != '') {
	$sql_where .= " AND " . "host.description LIKE '%$phone%'";
}
if ($phone_IP != '') {
	$sql_where .= " AND " . "host.hostname LIKE '%$phone_IP%'";
}
if ($phone_number != '') {
	$sql_where .= " AND " . "host.notes LIKE '%$phone_number%'";
}
if ($phone_model != '') {
	$sql_where .= " AND " . "host.model LIKE '%$phone_model%'";
}

if ($switch_name != '') {
	$sql_where .= " AND " . "switch.hostname LIKE '%$switch_name%'";
}
if ($switch_description != '') {
	$sql_where .= " AND " . "switch.description LIKE '%$switch_description%'";
}
if ($switch_port != '') {
	$sql_where .= " AND " . "intf_src.field_value LIKE '%$switch_port%'";
}

general_header();

$total_rows = db_fetch_cell("SELECT count(host.hostname)
		FROM host, host as switch, plugin_linkdiscovery_intf discointf, host_snmp_cache intf_src 
		WHERE host.isPhone ='on' 
		AND host.id = discointf.host_id_dst 
		AND switch.id = discointf.host_id_src 
		AND intf_src.host_id=switch.id 
		AND intf_src.field_name='ifDescr' 
		AND intf_src.snmp_index IN (discointf.snmp_index_src, discointf.snmp_index_src=0)
		AND intf_src.snmp_query_id=(SELECT id FROM snmp_query WHERE name LIKE '%nterface%')
	$sql_where");

/* if the number of rows is -1, set it to the default */
if (get_request_var("rows") == "-1") {
	$per_row = read_config_option('num_rows_table'); //num_rows_device');
}else{
	$per_row = get_request_var('rows');
}
$page = ($per_row*(get_request_var('page')-1));
$sql_limit = $page . ',' . $per_row;

	if ($_REQUEST['sort_column'] == 'lastread') {
		$sort = $_REQUEST['sort_column'] . "/1";
	}else{
		$sort = $_REQUEST['sort_column'];
	}

$sortby  = get_request_var("sort_column");
if( strcmp($sortby, 'phone')  == 0) {
	$sortby="phone";
} elseif( strcmp($sortby, 'phone_IP')  == 0) {
	$sortby="phone_IP";
} else if( strcmp($sortby, 'phone_number')  == 0) {
	$sortby="phone_number";
} else if( strcmp($sortby, 'phone_model')  == 0) {
	$sortby="phone_model";
} else if( strcmp($sortby, 'switch_name')  == 0) {
	$sortby="switch_name";
} else if( strcmp($sortby, 'switch_description')  == 0) {
	$sortby="switch_description";
} else if( strcmp($sortby, 'switch_port')  == 0) {
	$sortby="switch_port";
}

$sql_query = "SELECT host.description AS phone, host.hostname AS phone_IP, host.notes AS phone_number, pehm.model AS phone_model 
		switch.hostname AS switch_name, switch.description AS switch_description, intf_src.field_value AS switch_port 
		FROM host
		INNER JOIN plugin_linkdiscovery_intf AS discointf ON host.id=discointf.host_id_dst 
		LEFT JOIN host AS switch ON switch.id = discointf.host_id_src 
		INNER JOIN host_snmp_cache AS intf_src ON intf_src.host_id=switch.id 
		INNER JOIN plugin_extenddb_host_model AS pehm ON pehm.host_id = host.id
		WHERE host.isPhone ='on' 
		AND intf_src.field_name='ifDescr' 
		AND intf_src.snmp_index IN (discointf.snmp_index_src, discointf.snmp_index_src=0)
		AND intf_src.snmp_query_id=(SELECT id FROM snmp_query WHERE name LIKE '%nterface%')
 		$sql_where 
		ORDER BY " . $sortby . " " . get_request_var("sort_direction") . "
		LIMIT " . $sql_limit;

$result = db_fetch_assoc($sql_query);

function phone_request_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

?>
<script type="text/javascript">
<!--

function applyFilterChange(objForm) {
	strURL = '?header=false&phone=' + objForm.phone.value;
	strURL += '&rows=' + objForm.rows.value;
	strURL +=  '&phone_IP=' + objForm.phone_IP.value;
	strURL +=  '&phone_number=' + objForm.phone_number.value;
	strURL +=  '&phone_model=' + objForm.phone_model.value;
	strURL +=  '&switch_name=' + objForm.switch_name.value;
	strURL +=  '&switch_description=' + objForm.switch_description.value;
	strURL +=  '&switch_port=' + objForm.switch_port.value;
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
	<form id=''linkdiscovery' action="<?php print $config['url_path'];?>plugins/linkdiscovery/phones.php?header=false">
		<table width="100%" cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space:' width="1">
					&nbsp;Phone:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="phone" size="15" value="<?php print get_request_var("phone");?>">
				</td>
				<td nowrap style='white-space:' width="1">
					&nbsp;Phone IP:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="phone_IP" size="15" value="<?php print get_request_var("phone_IP");?>">
				</td>
				<td nowrap style='white-space:' width="1">
					&nbsp;Phone number:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="phone_number" size="5" value="<?php print get_request_var("phone_number");?>">
				</td>
				<td nowrap style='white-space:' width="1">
					&nbsp;Phone model:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="phone_model" size="8" value="<?php print get_request_var("phone_model");?>">
				</td>
				<td nowrap style='white-space:' width="1">
					&nbsp;Switch name:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="switch_name" size="15" value="<?php print get_request_var("switch_name");?>">
				</td>
				<td nowrap style='white-space:' width="1">
					&nbsp;Switch description:&nbsp;
				</td>
				<td width="1">
					<input type="text" name="switch_description" size="25" value="<?php print get_request_var("switch_description");?>">
				</td>
				<td nowrap style='white-space:' width="1">
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
				<td nowrap style='white-space:'>
					<input type="submit" value="Go" title="Set/Refresh Filters">
					<input id='clear' type='button' title="Clear Filters" value='<?php print __('Clear');?>' onClick='clearFilter()'>
				</td>
			</tr>
		</table>
	</form>
		<script type='text/javascript'>

		function clearFilter() {
			strURL  = 'phones.php?header=false&rows=-1&page=1&clear=1';
			loadPageNoHeader(strURL);
		}
		</script>

	</td>
</tr>
<?php
html_end_box();


html_start_box('', '100%', '', '3', 'center', '');

$nav = html_nav_bar('phones.php?view', MAX_DISPLAY_PAGES, get_request_var('page'), $per_row, $total_rows, 12, __('Devices'), 'page', 'main');

print $nav;

$display_text = array(
	"phone" => array("Phone", "ASC"),
	"phone_IP" => array("Phone IP", "ASC"),
	"phone_number" => array("Phone number", "ASC"),
	"phone_model" => array("Phone model", "ASC"),
	"switch_name" => array("Switch name", "ASC"),
	"switch_description" => array("Switch description", "ASC"),
	"switch_port" => array("Switch port", "ASC"),
	"nosort" => array("", ""));

html_header_sort($display_text, get_request_var("sort_column"), get_request_var("sort_direction"), false);

$i=0;
if (sizeof($result)) {
    $class   = 'odd';

	foreach($result as $row) {
		($class == 'odd' )?$class='even':$class='odd';
		if ($row["phone"] == "") {
			$row["phone"] = "Not Detected";
		}

		print"<tr class='$class tablerow'>";
		print"<td style='padding: 4px; margin: 4px;'>" 
			. $row['phone'] . '</td>
			<td>' . $row['phone_IP'] . '</td>
			<td>' . $row['phone_number'] . '</td>
			<td>' . $row['phone_model'] . '</td>
			<td>' . $row['switch_name'] . '</td>
			<td>' . $row['switch_description'] . '</td>
			<td>' . $row['switch_port'] . '</td>
			<td align="right">';

		print "</tr>";
	}
}else{
	print "<tr><td style='padding: 4px; margin: 4px;' colspan=11><center>There are no Hosts to display!</center></td></tr>";
}

html_end_box(false);

print $nav;

bottom_footer();

?>

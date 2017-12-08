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

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

error_reporting(E_ALL ^ E_DEPRECATED);

// get the requested url, and rteurn an array of the requested field
function get_page( $url, $tagnames ){
	$ret = array();
	
	$handle = curl_init($url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
	$html = curl_exec($handle);
	curl_close($handle);

	libxml_use_internal_errors(true); // Prevent HTML errors from displaying
	
	$doc = new DOMDocument();
	$doc->loadHTML($html);
	if (!$doc) {
		parse_debug("doc EMPTY ".$url."\n");
	} else {
		$domelements = $doc->getElementsByTagName('tr');
		$elements = dnl2array($domelements);
		if ( $domelements->length > 0){
			$textContent = "";
			foreach( $elements as $data) {
				$textContent = preg_replace('/\s+/i', ' ',trim($data->textContent) );
				// get max 50 char, autrement recois tout en 1 foix
				foreach( $tagnames as $tagname ) {
					if( stripos($textContent, $tagname ) !== false && strlen($textContent) < 50  ) {
						parse_debug("element value: ".$textContent."\n");
						$ret[] = $textContent;
						break;
					} else {
						parse_debug("Notelement value: ".$textContent."\n");
					}
				}
			}
		} else {
			$dommeta = $doc->getElementsByTagName('meta');
			if( $dommeta->length >= 1 ){
				$xpath = new DOMXpath( $doc );
				$meta_redirect = $xpath->query("//meta");
				foreach ($meta_redirect as $node) {
					parse_debug( "node ".$node->getAttribute('content'). "\n");
					$pos = stripos($node->getAttribute('content'), 'URL=' );
					parse_debug( "pos: ". $pos."\n" );
					if( $pos !== false ) {
						$newurl = $url.'/'.substr( $node->getAttribute('content'), $pos+4 );
						parse_debug( "nodes ".$newurl. "\n");
						$ret = get_page( $newurl, $tagnames );
						break;
					}
				}
			}
		}
/*
		$domelements = $doc->getElementsByTagName('tr');
		$elements = dnl2array($domelements);
		$textContent = "";
		foreach( $elements as $data) {
			$textContent = preg_replace('/\s+/', ' ',trim($data->textContent) );
			// get max 50 char, autrement recois tout en 1 foix
			foreach( $tagnames as $tagname ) {
				if( strpos($textContent, $tagname ) !== FALSE && strlen($textContent) < 50 ) {
					parse_debug("element: ".var_dump($data)." value: ".$textContent."\n");
					$ret[] = $textContent;
				} else parse_debug("Notelement: ".var_dump($data)." value: ".$textContent."\n");

			}
		}
*/
	}
	
	return $ret;
}

// Converts a DOMNodeList to an Array of DOMelement that can be easily foreached
function dnl2array($domnodelist) {
    $return = array();
    for ($i = 0; $i < $domnodelist->length; ++$i) {
        $return[] = $domnodelist->item($i);
    }
    return $return;
}

function parse_debug($text) {
	global $debug;
//	if ($debug)	print $text;
//	if ($debug) cacti_log($text, false, "LINKDISCOVERY" );
	flush();
}
?>
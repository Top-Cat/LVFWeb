<?php

$c = query(
	'lvf_stats',
	'find'
)->sort(array('date' => -1))->limit(1);

if ($c->hasNext()) {
	$row = $c->getNext();

	$marr = array("Stop Updates" => array("stopnew" => "New", "stopchange" => "Changed", "stopsame" => "Same"), "Today's Requests" => array("list_req" => "LIST", "ETA" => "ETA", "HISTORY" => "History", "error_req" => "Error", "stop_req" => "Stop", "veh_req" => "Vehicle", "route_req" => "Route"));
	foreach ($marr as $title => $arr) {
		print "<h3>" . $title . "</h3><ul>";
		foreach ($arr as $db => $txt) {
			print "<li>" . $txt . ": " . (isset($row[$db]) ? $row[$db] : 0) . "</li>";
		}
		?></ul><?php
	}
}

?>
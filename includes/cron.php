#!/usr/local/bin/php
<?php

include "connect.php";

/*
 * Check I'm Master
 */

$cfg = yaml_parse_file("/media/raid/servers/failover/failover.cfg");

$hosts = $m->getHosts();
$ok = false;
foreach ($hosts as $host) {
	if ($host['state'] == 1 && $host['host'] == $cfg['address']) {
		$ok = true;
		break;
	}
}
if (!$ok) { die(); }

/*
 * We are master! Do stuff :D
 */

query(
	'lvf_destinations',
	'update',
	array(
		array(),
		array(
			'$rename' => array(
				'dest_cnt' => 'dest_cnt_y'
			)
		),
		array(
			'multiple' => true
		)
	)
);

$curl = doCurl("http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?ReturnList=StopCode1,StopPointName");

$stops = array();
$cursor = query('lvf_stops', 'find');
while ($cursor->hasNext()) {
	$row = $cursor->getNext();
	$stops[$row['_id']] = $row['name'];
}

$i = 0;
foreach ($curl as $row) {
	$data = json_decode($row);
	if ($data[0] == 0 && $data[2] != NULL && $data[2] != "NONE") {
		$stopName = str_replace(array(" / ", " /"), "/", trim(str_replace("'", "", $data[1])));
		
		if (!isset($stops[$data[2]]) || $stops[$data[2]] != $stopName) {
			$oldRow = query(
				'lvf_stops',
				'findAndModify',
				array(
					array(
						'_id' => $data[2]
					),
					array(
						'_id' => $data[2],
						'name' => $stopName
					),
					null,
					array(
						'upsert' => true
					)
				)
			);

			if ($oldRow == NULL) {
				stats::event("stopnew");
				debug::info("New Stop " . $data[2] . " with name '" . $stopName . "'");
			} else {
				stats::event("stopchange");
				debug::info("Stop " . $data[2] . " updated from '" . $oldRow['name'] . "' to '" . $stopName . "'");
			}
			$stops[$data[2]] = $stopName;
		} else {
			stats::event("stopsame");
		}
	}
}

stats::finalise();

?>
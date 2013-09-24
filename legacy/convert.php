<?php

include "sql.php";
include "../includes/connect.php";

//include "convert/update_where_seen.php";
include "convert/hits.php";

/*
$result = mysql_query("SELECT * FROM lvf_lists ORDER BY vid");
$lists = array();
$vid = 0;
while ($row = mysql_fetch_assoc($result)) {
	if ($row['vid'] != $vid && $vid > 0) {
		$r = $db->lvf_vehicles->update(array(($vid < 91000 ? 'vid' : 'uvi') => $vid), array('$set' => array('lists' => $lists)));
		$lists = array();
	}
	$vid = intval($row['vid']);
	$lists[] = $row['list_name'];
}*/
/*
$result = mysql_query("SELECT * FROM lvf_vehicles");
$db->lvf_vehicles->drop();
$out = array();
while ($row = mysql_fetch_assoc($result)) {
	$arr = array(
		'cdreg' => $row['cdreg'],
		'uvi' => intval($row['uvi']),
		'cur_reg' => $row['cur_reg'],
		'orig_reg' => $row['orig_reg'],
		'keep' => $row['keep'] == "Y",
		'operator' => $row['operator'],
		'fnum' => $row['fnum'],
		'rnfnum' => $row['rnfnum'],
		'sfnum' => intval($row['sfnum'])
	);
	if (intval($row['vid']) < 90000) {
		$arr['vid'] = intval($row['vid']);
	} else if (!empty($row['cdreg'])) {
		$arr['pre'] = true;
	}
	if (!empty($row['note'])) {
		$arr['note'] = $row['note'];
	}
	$out[] = $arr;
}
$db->lvf_vehicles->batchInsert($out);*/

/*$result = mysql_query("SELECT * FROM lvf_route_day WHERE date <= '2013-06-01'");

$out = array();
while ($row = mysql_fetch_assoc($result)) {
	$out[] = array(
		'date' => new MongoDate(strtotime($row['date'])),
		'first_seen' => new MongoDate(strtotime($row['date'] . " " . $row['first_seen'])),
		'last_seen' => new MongoDate(strtotime($row['date'] . " " . $row['last_seen'])),
		'lineid' => $row['lineid'],
		'route' => $row['route'],
		'vid' => intval($row['vehicle_id'])
	);
	if (count($out) > 2000) {
		$db->lvf_history->batchInsert($out, array('continueOnError' => true, 'w' => 0));
		$out = array();
	}
}
$db->lvf_history->batchInsert($out, array('continueOnError' => true, 'w' => 0));*/

/*$result = mysql_query("SELECT * FROM lvf_stops");

$db->lvf_stops->drop();
while ($row = mysql_fetch_assoc($result)) {
	$db->lvf_stops->update(
		array(
			'_id' => $row['StopId']
		),
		array(
			'$set' => array(
				'name' => $row['StopName']
			)
		),
		array(
			'upsert' => true
		)
	);
}*/

/*$result = mysql_query("SELECT * FROM lvf_destinations WHERE direction > 0");

$out = array();
while ($row = mysql_fetch_assoc($result)) {
	$data = array(
		'route' => $row['route'],
		'lineid' => $row['Lineid'],
		'direction' => intval($row['direction']),
		'destination' => $row['destination']
	);
	if (!empty($row['day'])) {
		$data['day'] = $row['day'];
	}
	if (!empty($row['rday'])) {
		$data['rday'] = $row['rday'];
	}
	if ($row['dest_cnt'] > 0) {
		$data['dest_cnt'] = $row['dest_cnt'];
	}
	if ($row['notify'] > 0) {
		$data['notify'] = true;
	}
	if (strlen($row['operators']) > 2) {
		$data['operators'] = $row['operators'];
	}
	$out[] = $data;
}
$db->lvf_destinations->batchInsert($out);*/

?>

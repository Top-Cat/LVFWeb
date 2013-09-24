<?php

$result = mysql_query("SELECT * FROM lvf_hits");

while ($row = mysql_fetch_assoc($result)) {
	$db->lvf_stats->update(
		array(
			'date' => new MongoDate(strtotime($row['date']))
		),
		array(
			'$set' => array(
				'request' => intval($row['requests']),
				'route_req' => intval($row['route_req']),
				'veh_req' => intval($row['veh_req']),
				'stop_req' => intval($row['stop_req']),
				'HISTORY' => intval($row['history']),
				'ETA' => intval($row['eta']),
				'list_req' => intval($row['list_req']),
				'error_req' => intval($row['error_req']),
				'dumpv' => intval($row['dumpv'])
			)
		),
		array(
			'upsert' => true
		)
	);
}

?>
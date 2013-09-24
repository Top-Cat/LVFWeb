<?php
	 
// update where seen records for all active vehicles

//$result = mysql_query("SELECT vid, uvi FROM lvf_vehicles WHERE vid < 22000");
$cursor = query(
	"lvf_vehicles",
	"find",
	array(
		array(
			'whereseen' => array('$exists' => false),
			'vid' => array('$exists' => true)
		)
	)
);
//while ( $row = mysql_fetch_assoc($result)) {
while ($cursor->hasNext()) {
	$row = $cursor->getNext();

	// read most recent record from route day
	
//	$resulta = mysql_query("SELECT route, lineid, date, last_seen FROM lvf_route_day WHERE vehicle_id = '" . $uvi . "' order by date desc, last_seen desc limit 1");
	$history = query(
		"lvf_history",
		"find",
		array(
			array(
				'vid' => $row['uvi']
			)
		)
	)->sort(array('last_seen' => -1))->limit(1);
	if ($history->hasNext()) {
		$hist = $history->getNext();
		query(
			"lvf_vehicles",
			"update",
			array(
				array(
					'uvi' => $row['uvi']
				),
				array(
					'$set' => array(
						'whereseen' => array(
							'route' => $hist['route'],
							'line_id' => $hist['lineid'],
							'last_seen' => $hist['last_seen']
						)
					)
				)
			)
		);
		print "We did " . $row['uvi'] . "<br />";
	}
}
?>

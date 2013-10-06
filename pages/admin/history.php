<?php

if (isset($_GET['uvi'])) {
	if (isset($_GET['move'])) {
		try {
			query(
				"lvf_history",
				"update",
				array(
					array(
						'_id' => new MongoId($_GET['id']),
						'date' => array(
							'$lt' => new MongoDate(strtotime("today"))
						)
					),
					array(
						'$set' => array(
							'vid' => intval($_GET['uvi'])
						)
					)
				)
			);
		} catch (MongoCursorException $e) {
			if ($e->getCode() == 11001) {
				// Duplicate key, perform merge
				$new = query(
					"lvf_history",
					"findOne",
					array(
						array(
							'_id' => new MongoId($_GET['id'])
						)
					)
				);
				$old = query(
					"lvf_history",
					"findOne",
					array(
						array(
							'vid' => intval($_GET['uvi']),
							'date' => $new['date'],
							'lineid' => $new['lineid']
						)
					)
				);

				if ($new['first_seen']->sec < $old['first_seen']->sec) {
					$update['$set']['first_seen'] = $new['first_seen'];
				}
				if ($new['last_seen']->sec > $old['last_seen']->sec) {
					$update['$set']['last_seen'] = $new['last_seen'];
				}
				if (!empty($update)) {
					query(
						"lvf_history",
						"update",
						array(
							array(
								'_id' => $old['_id']
							),
							$update
						)
					);
					query("lvf_history", "remove", array(array('_id' => new MongoId($_GET['id']))));
				}
			}
		}
	} else {
		$c = query("lvf_history", "find", array(array('vid' => intval($_GET['uvi']), 'date' => array('$lt' => new MongoDate(strtotime("today"))))))->sort(array('date' => -1));
		$out = array();
		while ($c->hasNext()) {
			$out[] = $c->getNext();
		}
		print json_encode($out);
	}
} else {

?>
	<form>
		<label>From: <input type="text" id="search_history" /></label>
		<label>To: <input type="text" id="to_history" /></label>
	</form>
	<hr style="display: block" />
	<div class="results" id="hist_results"></div>
<?php } ?>

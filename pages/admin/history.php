<?php

if (isset($_GET['uvi'])) {
	if (isset($_GET['move'])) {
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
						'vid' => intval($_GET['to'])
					)
				)
			)
		);
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

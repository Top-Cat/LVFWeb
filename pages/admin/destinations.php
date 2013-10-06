<?php

if (isset($_GET['route'])) {
	if (isset($_GET['save'])) {
		$update = array('$set' => array('destination' => $_GET['dest']));
		if (empty($_GET['day'])) {
			$update['$unset']['day'] = 1;
		} else {
			$update['$set']['day'] = $_GET['day'];
		}

		query(
			"lvf_destinations",
			"update",
			array(
				array(
					'_id' => new MongoId($_GET['id'])
				),
				$update
			)
		);
	} elseif (isset($_GET['delete'])) {
		query(
			"lvf_destinations",
			"remove",
			array(
				array(
					'_id' => new MongoId($_GET['id'])
				)
			)
		);
	} else {
		$c = query("lvf_destinations", "find", array(array('route' => $_GET['route'])))->sort(array('direction' => 1, 'day' => 1));
		$out = array();
		while ($c->hasNext()) {
			$out[] = $c->getNext();
		}
		print json_encode($out);
	}
} else {

?>
	<label style="display: inline-block; width: auto">Route: <input type="text" id="route" /></label>
	<hr style="display: block" />
	<div class="results" id="results"></div>
<?php } ?>

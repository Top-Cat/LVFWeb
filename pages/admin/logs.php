<?php

if (isset($_GET['get'])) {
	$match = array();
	if (isset($_GET['since'])) {
		$match = array("_id" => array('$gt' => new MongoId($_GET['since'])));
	}
	$c = query("lvf_audit", "find", array($match))->sort(array('_id' => -1));
	$arr = array();
	while ($c->hasNext()) {
		$row = $c->getNext();

		$out = array(
			'id' => $row['_id']->{'$id'},
			'time' => $row['_id']->getTimestamp(),
			'txt' => $row['text']
		);

		if (isset($row['uvi'])) {
			$out['uvi'] = $row['uvi'];
		}

		$arr[] = $out;
	}
	print json_encode(array_reverse($arr));
	$template = false;
} else {

?>
	<div id="logs"></div>
<?php } ?>

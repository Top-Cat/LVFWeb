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
		$arr[] = array($row['_id']->{'$id'}, $row['_id']->getTimestamp(), $row['text']);
	}
	print json_encode(array_reverse($arr));
	$template = false;
} else {

?>
	<div id="logs"></div>
<?php } ?>

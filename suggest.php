<?php

include "includes/connect.php";

$c = query(
	'lvf_suggest',
	'find',
	array(
		array(
			'error' => false,
			'query' => array(
				'$regex' => new MongoRegex('/^' . $_GET['reg'] . '/i')
			)
		),
		array(
			'_id' => 0,
			'count' => 1,
			'query' => 1
		)
	)
)->sort(
	array(
		'count' => -1
	)
)->limit(5)->hint('count_-1_query_1');

$arr = array();
while ($c->hasNext()) {
	$row = $c->getNext();
	$arr[] = array('value' => $row['query'], 'count' => $row['count']);
}

print json_encode($arr);

?>
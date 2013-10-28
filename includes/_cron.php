#!/usr/local/bin/php
<?php

include "../includes/connect.php";

$curl = doCurl("http://www.tfl.gov.uk/tfl/businessandpartners/syndication/feed.aspx?email=webmaster@thomasc.co.uk&feedId=10", "", false);

function csvToArray($contents, $linelen = 1000) {
	$result = array();
	$tarray = array();
	$quoted = false;
	$word = "";
	for($i = 0; $i < strlen($contents); $i++) {
		//get the current character
		$char = substr($contents, $i, 1);
		//check for the start/end of a quoted section
		if ($char == '"') {  
			$quoted = !$quoted;
		}
		
		//if we are not in quote mode...
		if ($quoted == false) {
			//check for commas
			if ($char == ',') {
				$tarray[] = $word;
				$word = "";
				
				//now if we are over the limit of $linelen, then add the current temporary array to the result
				if (count($tarray) >= $linelen) {
					$result[] = $tarray;
					$tarray = array();  //reset the temporary array
				}
			}
			if ($char == "\n") {
				$tarray[] = $word;
				$word = "";
				$result[] = $tarray;
				$tarray = array();  //reset the temporary array
			}
		}
		
		if ($char != '"') {
			if (($char != ',' && $char != "\n") || $quoted) {
				$word .= $char;
			}
		}
	}
	
	return $result;
}

$arr = csvToArray($curl);

$stops = array();
unset($arr[0]);
foreach ($arr as $stopd) {
	if ($stopd[8] == 0 && $stopd[1] != "NONE" && count($stopd) > 1) {
		$name = str_replace(array(" / ", " /", "/ "), "/",
			mb_convert_case(
				strtolower(
					trim(
						str_replace(array("<T>", ">T<", "<>", "#", "'"), "",
							$stopd[3]
						)
					)
				)
			, MB_CASE_TITLE, "UTF-8")
		);
		$stops[$stopd[1]] = $name;
	}
}
ksort($stops);

$c = query(
	'lvf_stops',
	'find'
)->sort(
	array(
		'_id' => 1
	)
);

$dbstops = array();
while ($c->hasNext()) {
	$r = $c->getNext();
	$dbstops[$r['_id']] = $r['name'];
}

$diff = array_diff($stops, $dbstops);
foreach ($diff as $i => $n) {
	print $i . ',"' . $n . '","' . $dbstops[$i] . "\"\n";
}

?>
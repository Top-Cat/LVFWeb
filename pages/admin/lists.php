<?php

if (isset($_GET['list'])) {
	if (isset($_GET['uvi'])) {
		if (isset($_GET['remove'])) {
			$r = query("lvf_vehicles", "findAndModify", array(array("uvi" => intval($_GET['uvi'])), array('$pull' => array("lists" => $_GET['list'])), array('uvi' => 1, 'vid' => 1, 'lists' => 1)));
		} else if (isset($_GET['add'])) {
			$r = query(
				"lvf_vehicles",
				"findAndModify",
				array(
					array("vid" => intval($_GET['uvi'])),
					array('$addToSet' => array("lists" => $_GET['list'])),
					array('uvi' => 1, 'vid' => 1, 'lists' => 1)
				)
			);
			if (empty($r)) {
				$r = query(
					"lvf_vehicles",
					"findAndModify",
					array(
						array("uvi" => intval($_GET['uvi'])),
						array('$addToSet' => array("lists" => $_GET['list'])),
						array('uvi' => 1, 'vid' => 1, 'lists' => 1)
					)
				);
			}
		}
	}

	$c = query("lvf_vehicles", "find", array(array("lists" => $_GET['list']), array('uvi' => 1, 'vid' => 1)), false, false)->sort(array('uvi' => 1));
	$arr = array();
	$vids = array();
	$sort = array();
	while ($c->hasNext()) {
		$row = $c->getNext();
		$arr[] = $row['uvi'];
		$vids[] = isset($row['vid']) ? $row['vid'] : 0;
		$sort[] = isset($row['vid']) ? $row['vid'] : $row['uvi'];
	}
	if (!empty($r)) {
		$key = array_search($_GET['list'], $r['lists']);
		if (isset($_GET['remove']) && $key !== false) {
			unset($arr[$key]);
			unset($vids[$key]);
			unset($sort[$key]);
		} else if ($key === false) {
			$arr[] = $r['uvi'];
			$vids[] = isset($r['vid']) ? $r['vid'] : 0;
			$sort[] = isset($r['vid']) ? $r['vid'] : $r['uvi'];
		}
	}
	array_multisort($sort, $vids, $arr);
	print json_encode(array($arr, $vids));
	$template = false;
} else {

?>
	<input type="text" style="margin-bottom: 10px" id="list" />
	<hr id="hr1" />
	<div id="vids"></div>
	<hr style="display: block" />
	<input type="text" id="newvid" /><button id="add" style="display: inline-block; margin-left: 15px" type="submit"><span><i><b></b><u>Add</u></i></span></button>
<?php } ?>

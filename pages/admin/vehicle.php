<?php

$fields = array("vid", "uvi", "cur_reg", "cdreg", "orig_reg", "operator fnum");
$type = array(0, 0, 1, 1, 1, 1);

if (isset($_GET['val'])) {
	$match = array();
	$fs = explode(" ", $fields[intval($_GET['field'])]);
	$vs = explode(" ", $_GET['val']);

	foreach ($fs as $k => $v) {
		$val = strtoupper($vs[$k]);
		if ($type[intval($_GET['field'])] == 0) {
			$val = intval($val);
		}
		$match[$v] = $val;
	}

	$arr = query("lvf_vehicles", "findOne", array($match, array('whereseen' => 0, '_id' => 0)));
	print json_encode($arr);
	$template = false;
} else if (isset($_GET['save']) || isset($_GET['insert'])) {
	$template = false;
	$ufields = array("cur_reg" => 0, "orig_reg" => 0, "operator" => 0, "fnum" => 0, "rnfnum" => 0, "sfnum" => 1, "note" => 0);
	$set = array();
	$unset = array();
	if (isset($_GET['keep'])) {
		$set['keep'] = $_GET['keep'] == "true";
	}
	if (isset($_GET['pre'])) {
		if ($_GET['pre'] == "true") {
			$set['pre'] = true;
		} else {
			$unset['pre'] = 1;
		}
	}
	foreach ($ufields as $f => $t) {
		if (isset($_GET[$f]) && !empty($_GET[$f])) {
			$val = $_GET[$f];
			if ($t == 1) {
				$val = intval($val);
			}
			$set[$f] = $val;
		} else {
			$unset[$f] = 1;
		}
	}
	if (isset($_GET['save'])) {
		$update = array();
		if (!empty($set)) {
			$update['$set'] = $set;
		}
		if (!empty($unset)) {
			$update['$unset'] = $unset;
		}
		if (!empty($update)) {
			query(
				"lvf_vehicles",
				"update",
				array(
					array('uvi' => intval($_GET['uvi'])),
					$update
				)
			);
		}
	} else if (isset($_GET['insert'])) {
		do {
			$val = query(
				"counters",
				"findAndModify",
				array(
					array('_id' => 'uvi'),
					array('$inc' => array('seq' => 1)),
					array('_id' => 0),
					array('new' => true)
				)
			);
			$guess = $val['seq'];
		} while (query("lvf_vehicles", "find", array(array('uvi' => $guess)))->limit(1)->count(true) > 0);
		$set['uvi'] = $guess;
		query(
			"lvf_vehicles",
			"insert",
			array($set)
		);
		print $guess;
	}
} else if (isset($_GET['withdraw'])) {
	query(
		"lvf_tasks",
		"insert",
		array(
			array(
				'task' => 'withdraw',
				'vid' => intval($_GET['vid'])
			)
		)
	);
} else {

?><script>
	fields = ["uvi", "vid", "cur_reg", "cdreg", "orig_reg", "operator", "fnum", "rnfnum", "sfnum", "note", "keep", "pre"]
	type = [0, 0, 1, 0, 1, 1, 1, 1, 1, 1, 2, 2];
	window.onload = function() {
		txt = document.getElementById('list');
		dd = document.getElementById('field');
		txt.onkeydown = function(e) {
			if (e.keyCode == 13) {
				do_req("?field=" + dd.selectedIndex + "&val=" + txt.value, list);
			}
		};
	};
	function save(obj) {
		var elms = obj.parentNode.parentNode.getElementsByTagName("input");
		var data = "";
		for (var i = 0; i < elms.length; i++) {
			elem = elms[i];
			if (elem.type === 'checkbox') {
				val = elem.checked;
			} else {
				val = elem.value;
			}
			data += "&" + elem.id + "=" + val;
		}
		do_req("?save" + data, saved);
	}
	function insert(obj) {
		var elms = obj.parentNode.parentNode.getElementsByTagName("input");
		var data = "";
		for (var i = 0; i < elms.length; i++) {
			if (type[i] > 0) {
				elem = elms[i];
				if (elem.type === 'checkbox') {
					val = elem.checked;
				} else {
					val = elem.value;
				}
				data += "&" + elem.id + "=" + val;
			}
		}
		do_req("?insert" + data, inserted);
	}
	function withdraw() {
		if (confirm("Are you sure?")) {
			do_req("?withdraw&vid=" + document.getElementById('vid').value, saved);
		}
	}
	function saved(r) {
	}
	function inserted(r) {
		document.getElementById('uvi').value = r;
	}
	function list(r) {
		obj = JSON.parse(r);

		for (y in fields) {
			x = fields[y];
			elem = document.getElementById(x);
			if (obj[x] !== undefined) {
				if (type[y] < 2) {
					elem.value = obj[x];
				} else {
					elem.checked = obj[x];
				}
			} else {
				if (type[y] < 2) {
					elem.value = "";
				} else {
					elem.checked = false;
				}
			}
		}
	}
</script>
<?php print $anav; ?>
	<select id="field"><?php
		foreach ($fields as $v) { ?>
		<option><?php print $v; ?></option><?php 
		} ?>
	</select>
	<input type="text" style="margin-bottom: 10px" id="list" />
	<hr style="display: block" />
	<form id="v">
		<label>UVI: <input type="text" id="uvi" disabled /></label>
		<label>VID: <input type="text" id="vid" disabled /></label>
		<label>Real Reg: <input type="text" id="cur_reg" /></label>
		<label>Countdown Reg: <input type="text" id="cdreg" disabled /></label>
		<label>Original Reg: <input type="text" id="orig_reg" /></label>
		<label>Operator: <input type="text" id="operator" /></label>
		<label>Fleet Number: <input type="text" id="fnum" /></label>
		<label>Ranging Fleet Number: <input type="text" id="rnfnum" /></label>
		<label>Sorting Fleet Number: <input type="text" id="sfnum" /></label>
		<label>Note: <input type="text" id="note" /></label>
		<label>Keep: <input type="checkbox" id="keep" /></label>
		<label>Pre-entered: <input type="checkbox" id="pre" /></label>
		<div class="c"><button type="button" onclick="insert(this)"><span><i><b></b><u>Insert</u></i></span></button><button type="button" onclick="save(this)"><span><i><b></b><u>Save</u></i></span></button><button type="button" onclick="withdraw()"><span><i><b></b><u>Withdraw</u></i></span></button></div>
	</form>
</div><?php } ?>

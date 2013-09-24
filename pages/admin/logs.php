<?php

if (isset($_GET['get'])) {
	$match = array();
	if (isset($_GET['since'])) {
		$match = array("_id" => array('$gt' => new MongoId($_GET['since'])));
	}
	$c = query("lvf_audit", "find", array($match))->sort(array('_id' => -1));
	if (!isset($_GET['since'])) {
		$c->limit(20);
	}
	$arr = array();
	while ($c->hasNext()) {
		$row = $c->getNext();
		$arr[] = array($row['_id']->{'$id'}, $row['_id']->getTimestamp(), $row['text']);
	}
	print json_encode(array_reverse($arr));
	$template = false;
} else {

?><script>
	var since = "";
	var since_t = 0;
	window.onload = function() {
		do_req("?get", list);
		setInterval(update, 10000);
	};
	function update() {
		do_req("?get&since=" + since, list);
	}
	function list(r) {
		obj = JSON.parse(r);

		logs = document.getElementById('logs');
		for (i in obj) {
			var time = document.createElement("div");
			time.className = "logtime";
			d = new Date(obj[i][1] * 1000);
			y = d.getFullYear();
			m = d.getMonth() + 1;
			h = d.getHours();
			j = d.getMinutes();
			d = d.getDate();
			time.innerHTML = y + "-" + (m <= 9 ? "0" + m : m) + "-" + (d <= 9 ? "0" + d : d) + " " + (h <= 9 ? "0" + h : h) + ":" + (j <= 9 ? "0" + j : j);

			var div = document.createElement("div");
			div.className = "log";
			if (obj[i][1] >= since_t) {
				since_t = obj[i][1];
				since = obj[i][0];
			}
			div.innerHTML = obj[i][2];

			logs.insertBefore(div, logs.firstChild);
			logs.insertBefore(time, logs.firstChild);
		}
	}
</script>
<?php print $anav; ?>
	<div id="logs"></div>
</div><?php } ?>

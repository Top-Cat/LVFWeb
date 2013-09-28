function show(val) {
	if (val != cur) {
		document.getElementById(cur).style.display = 'none';
		document.getElementById(val).style.display = '';

		document.getElementById('nav_' + cur).className = '';
		document.getElementById('nav_' + val).className = 'sel';

		cur = val;
	}
	return false;
}

fields = ["uvi", "vid", "cur_reg", "cdreg", "orig_reg", "operator", "fnum", "rnfnum", "sfnum", "note", "keep", "pre"]
type = [0, 0, 1, 0, 1, 1, 1, 1, 1, 1, 2, 2];
window.addEventListener('load', function() {
	search_txt = document.getElementById('search');
	dd = document.getElementById('field');
	search_txt.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?vehicle&ajax&field=" + dd.selectedIndex + "&val=" + search_txt.value, search);
		}
	};

	txt = document.getElementById('list');
	txt.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?lists&ajax&list=" + txt.value, list);
		}
	};
	btn = document.getElementById('add');
	vidtxt = document.getElementById('newvid');
	vidtxt.onkeydown = btn.onclick = function(e) {
		if (e.type == 'click' || e.keyCode == 13) {
			do_req("?lists&ajax&add&uvi=" + vidtxt.value + "&list=" + txt.value, list);
		}
	}

	do_req("?logs&ajax&get", log);
	setInterval(update, 10000);

	routetxt = document.getElementById('route');
	routetxt.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?destinations&ajax&route=" + routetxt.value, route);
		}
	};

	stop_find = document.getElementById('stop_find');
	stop_name = document.getElementById('stop_name');
	stop_find.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?stops&ajax&stop=" + stop_find.value, stop);
		}
	};

	histsrch = document.getElementById('search_history');
	histsrch.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?history&ajax&uvi=" + histsrch.value, history, function(){}, histsrch.value);
		}
	};

	results = document.getElementById('results');
	hresults = document.getElementById('hist_results');
}, false);

function p(v) {
	return v < 10 ? "0" + v : v;
}

cUvi = 0;
function history(r, uvi) {
	cUvi = uvi;
	obj = JSON.parse(r);
	inner = "<div><div>Line ID</div><div>Date</div><div>First Seen</div><div>Last Seen</div><div>Route</div></div>";

	for (x in obj) {
		var date_raw = new Date(1000*obj[x]['date']['sec']);
		date = p(date_raw.getDate()) + "/" + p(date_raw.getMonth() + 1) + "/" + date_raw.getFullYear();
		var first_seen_raw = new Date(1000*obj[x]['first_seen']['sec']);
		first_seen = p(first_seen_raw.getHours()) + ":" + p(first_seen_raw.getMinutes()) + ":" + p(first_seen_raw.getSeconds());
		var last_seen_raw = new Date(1000*obj[x]['last_seen']['sec']);
		last_seen = p(last_seen_raw.getHours()) + ":" + p(last_seen_raw.getMinutes()) + ":" + p(last_seen_raw.getSeconds());
		inner += "<div>" +
				"<div>" + obj[x]['lineid'] + "</div>" +
				"<div>" + date + "</div>" +
				"<div>" + first_seen + "</div>" +
				"<div>" + last_seen + "</div>" +
				"<div>" + obj[x]['route'] + "</div>" +
				"<img src='content/move.png' onclick='histmove(\"" + obj[x]['_id']['$id'] + "\", this)' />" +
				"</div>";
	}

	hresults.innerHTML = inner;
}
function histmove(id, obj) {
	uviTo = document.getElementById('to_history').value;
	if (uviTo != cUvi) {
		do_req("?history&ajax&move&uvi=" + uviTo + "&id=" + id, saved);
		obj.parentNode.parentNode.removeChild(obj.parentNode);
	}
}

function stop(r) {
	stop_name.value = r;
}
function stopsave() {
	do_req("?stops&ajax&save&stop=" + stop_find.value + "&name=" + stop_name.value, saved);
}
function stopdelete() {
	do_req("?stops&ajax&delete&stop=" + stop_find.value + "&name=" + stop_name.value, saved);
}

cRoute = 0;
function route(r) {
	obj = JSON.parse(r);
	cRoute = obj[0]['route'];
	inner = "<div><div>Line ID</div><div>Direction</div><div>Destination</div><div>Day</div><div>Count</div></div>";

	for (x in obj) {
		inner += "<div id='" + obj[x]['_id']['$id'] + "'>" +
				"<input type='text' value='" + obj[x]['lineid'] + "' />" +
				"<input type='text' value='" + obj[x]['direction'] + "' />" +
				"<input type='text' value='" + obj[x]['destination'] + "' />" +
				"<input type='text' value='" + (obj[x]['day'] != undefined ? obj[x]['day'] : "") + "' />" +
				"<div>" + (obj[x]['dest_cnt'] != undefined ? obj[x]['dest_cnt'] : 0) + "</div>" +
				"<img src='content/save.png' onclick='routesave(this)' />" +
				"<img src='content/delete.png' onclick='routedelete(this)' />" +
				"</div>";
	}

	results.innerHTML = inner;
}
function addrow() {
	if (cRoute > 0) {
		inner = "<input type='text' />" +
			"<input type='text' />" +
			"<input type='text' />" +
			"<input type='text' />" +
			"<div>0</div>" +
			"<img src='content/save.png' />" +
			"<img src='content/delete.png' />";
		var newLine = document.createElement("div");
		newLine.innerHTML = inner;

		results.appendChild(newLine);
	}
}
function routesave(obj) {
	div = obj.parentNode;

	dest = div.children[2].value;
	day = div.children[3].value;

	do_req("?destinations&ajax&save&id=" + div.id + "&day=" + day + "&dest=" + dest, saved);
}
function routedelete(obj) {
	div = obj.parentNode;

	do_req("?destinations&ajax&delete&id=" + div.id, saved);
	div.parentNode.removeChild(div);
}

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
	do_req("?vehicle&ajax&save" + data, saved);
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
	do_req("?vehicle&ajax&insert" + data, inserted);
}
function doclear(obj) {
	var elms = obj.parentNode.parentNode.getElementsByTagName("input");
	for (var i = 0; i < elms.length; i++) {
		elem = elms[i];
		if (elem.type === 'checkbox') {
			elem.checked = false;
		} else {
			elem.value = "";
		}
	}
}
function withdraw() {
	vid = document.getElementById('vid').value;
	if (confirm("Are you sure you want to withdraw the vehicle with vid '" + vid + "'?")) {
		do_req("?vehicle&ajax&withdraw&vid=" + vid, saved);
	}
}
function dodelete() {
	uvi = document.getElementById('uvi').value;
	if (confirm("Are you sure you want to delete the vehicle with uvi '" + uvi + "'?")) {
		do_req("?vehicle&ajax&delete&uvi=" + uvi, saved);
	}
}
function saved(r) {
}
function inserted(r) {
	document.getElementById('uvi').value = r;
}
function search(r) {
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

function list(r) {
	obj = JSON.parse(r);

	vids = document.getElementById('vids');
	while (vids.firstChild) {
		vids.removeChild(vids.firstChild);
	}
	for (i in obj[0]) {
		var div = document.createElement("div");
		div.className = "list";
		div.id = obj[0][i];
		div.innerHTML = obj[0][i];
		if (obj[1][i] != 0) {
			div.innerHTML = obj[1][i];
		}
		div.onclick = function() {
			do_req("?lists&ajax&remove&uvi=" + this.id + "&list=" + txt.value, list);
		}
		vids.appendChild(div);
	}
	document.getElementById('hr1').style.display = 'block';
}

var since = "";
var since_t = 0;
function update() {
	do_req("?logs&ajax&get&since=" + since, log);
}
function log(r) {
	obj = JSON.parse(r);

	logs = document.getElementById('logs');
	for (i in obj) {
		var container = document.createElement("div");

		var time = document.createElement("div");
		time.className = "logtime";
		d = new Date(obj[i]['time'] * 1000);
		y = d.getFullYear();
		m = d.getMonth() + 1;
		h = d.getHours();
		j = d.getMinutes();
		d = d.getDate();
		time.innerHTML = y + "-" + (m <= 9 ? "0" + m : m) + "-" + (d <= 9 ? "0" + d : d) + " " + (h <= 9 ? "0" + h : h) + ":" + (j <= 9 ? "0" + j : j);
		container.appendChild(time);

		var div = document.createElement("div");
		div.className = "log";
		if (obj[i]['time'] >= since_t) {
			since_t = obj[i]['time'];
			since = obj[i]['id'];
		}
		div.innerHTML = obj[i]['txt'];
		container.appendChild(div);

		if (obj[i]['uvi'] != undefined) {
			container.className = "editable";
			container.id = obj[i]['uvi'];
			container.onclick = function() {
				show('vehicle');
				do_req("?vehicle&ajax&field=1&val=" + this.id, search);
			};
		}

		logs.insertBefore(container, logs.firstChild);
	}
}

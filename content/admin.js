cur = "logs";
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
	txt = document.getElementById('search');
	dd = document.getElementById('field');
	txt.onkeydown = function(e) {
		if (e.keyCode == 13) {
			do_req("?field=" + dd.selectedIndex + "&val=" + txt.value, search);
		}
	};
}, false);
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
	if (confirm("Are you sure?")) {
		do_req("?withdraw&vid=" + document.getElementById('vid').value, saved);
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

window.addEventListener('load', function() {
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
}, false);
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
window.addEventListener('load', function() {
	do_req("?logs&ajax&get", log);
	setInterval(update, 10000);
}, false);
function update() {
	do_req("?logs&ajax&get&since=" + since, log);
}
function log(r) {
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
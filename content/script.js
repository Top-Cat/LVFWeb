function mainExtra(rs, data) {
	if (txt == data) {
		if (rs >= 0) {
			$("#load").animate({width: (rs * 25) + "%"}, 100, "easeInOutQuad");
			if (rs == 4) {
				$("#load").animate({opacity: 0}, 1000, "easeInOutQuad");
			}
		} else {
			ajaxOver();
		}
	}
}

firstRequest = true;
maxiHeight = 0;
page = 0;
lines = new Array();
pageIndex = [0];
txt = "";
timeout = null;

function startrequest() {
	page = 0;
	maxiHeight = 0;
	pageIndex = [0];
	txt = txtField.val();
	doRequest();
}

function doRequest() {
	$("#content").animate({opacity: 0}, 100, "easeInOutQuad");
	button.hide();
	ajLoader.style.display = 'inline-block';
	setTimeout("$('#load').animate({opacity: 1}, 100, 'easeInOutQuad'); $('#load').width('0%'); do_req('request/' + encodeURIComponent(txt) + '?' + (new Date().getTime()), response, mainExtra, txt);", 10);
	if (timeout != null) { clearTimeout(timeout); }
	timeout = setTimeout("rText.innerHTML = '';", 500);
	//ga('send','event','request','start',txt);
}

function ajaxOver() {
	clearTimeout(timeout);
	ajLoader.style.display = 'none';
	button.show();
	$("#content").animate({opacity: 1}, 100, "easeInOutQuad");
}

function response(r, data) {
	if (txt == data) {
		ajaxOver();
		if (!firstRequest) {
			nsetText(r);
		} else {
			$("#padding").animate({height: 45}, 1000, "easeInOutQuad", function() {
				separator.style.display = 'block';
				nsetText(r);
			});
			firstRequest = false;
		}
	}
}
function nsetText(r) {
	obj = JSON.parse(r);
	
	if (obj.success) {
		displayRow();
	} else {
		//ga('send','event','request','error');
		rText.innerHTML = obj.error + "<br /><br />If you are having difficulties obtaining results refer to the help page<br />or join the LVF User group at LVF_Users@yahoogroups.com";
	}
	thisHeight = parseInt($(rText).css("height").replace(/[^-\d\.]/g, ''));
	maxiHeight = Math.max(maxiHeight, thisHeight);
	$("#wrapper").animate({height: maxiHeight + 175}, 600, "easeInOutQuad");
}

function buildHeaders(header) {
	out = "";
	for (x in header) {
		out += "<th";
		if (header[x] > 0) {
			out += " style='width: " + header[x] + "px'";
		}
		out += ">" + x + "</th>";
	}
	return out;
}

function displayRow() {
	loopStart = pageIndex[page];
	loopIndex= loopStart;
	out = "<span" + (maxiHeight > 26 ? " style='min-height: " + (maxiHeight - 26) + "px'" : "") + "><span>";
	//ga('send','event','request','done',obj.request.cmd);
	if (obj.request.cmd == "LIST") {
		out += "<table><tr>" + buildHeaders({'Oper': 40, 'Fleet': 105, 'Reg': 100, 'Rte': 50, 'Destination': 170, 'Time': 50, 'Stop': 50, 'Stop Name': 0});
		for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
			if (loopIndex + 1 > obj.response.lines.length) {
				break;
			}
			line = obj.response.lines[loopIndex];
			out += "</tr><tr><td title='" + line['opName'] + "'>" + line['op'] + "</td><td>" + line['fnum'] + "</td><td><a href='#HISTORY " + line['reg'] + "'>" + (line['note'].length > 0 ? line['note'] : line['reg']) + "</a></td>";
			if (line['dest'] == "unknown") {
				out += "<td></td><td colspan='5'>No record of this vehicle in service</td>";
			} else if (line['dest'] == "withdrawn") {
				out += "<td></td><td colspan='5'>Vehicle withdrawn, usage records may still exist</td>";
			} else {
				out += "<td><a href='#" + line['line'] + "'>" + line['line'] + "</a></td>";
				if (!line['today']) {
					out += "<td colspan='3'>last seen at " + line['when'] + "</td>";
				} else {
					out += "<td>" + line['dest'] +  "</td><td>" + line['when'] + "</td><td title='" + line['stopName'] + "'><a href='#" + line['stop'] + "'>" + line['stop'] + "</a></td><td>" + line['stopName'].substring(0,30) + "</td>";
				}
			}
		}
	} else if (obj.request.cmd == "ROUTE") {
		mainroute = obj.response.extra[obj.request.reg];
		for (x in obj.response.extra) {
			route = obj.response.extra[x];
			if (x != "extra") {
				out += "<h2>" + route.operators + " - <a target='_blank' href='http://www.tfl.gov.uk/tfl/gettingaround/maps/buses/?q=" + x + "'>Route " + x + "</a> - between " + (('origin' in route) ? route.origin : ((mainroute != undefined) ? mainroute.origin : "Unknown")) + " and " + (('destination' in route) ? route.destination : mainroute.destination) + "</h2>";
			}
		}
		direction = 0;
		for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
			if (loopIndex + 1 > obj.response.lines.length) {
				break;
			}
			line = obj.response.lines[loopIndex];
			if (line.direction != direction) {
				i += 3;
				if (direction > 0) {
					out += "</tr></table>";
				}
				direction = line.direction;
				if (line.direction == 3) {
					out += "<h3>Vehicles that have" + (direction > 0 ? " also" : "") + " been on the route during the day :-</h3><table><tr>" + buildHeaders({'Fleet': 100, 'Reg': 110, 'Line': 70, 'Used From': 120, 'Used Until': 0});
				} else {
					dests = new Array();
					for (x in obj.response.extra) {
						route = obj.response.extra[x];
						if ((direction == "1" ? 'origin' : 'destination') in route) {
							dests.push((direction == "1" ? route.origin : route.destination) + (x != obj.request.reg ? " (" + x + ")" : ""));
						}
					}
					out += "<h3>Towards - " + dests.join(" / ") + "</h3><table><tr>" + buildHeaders({'Fleet': 100, 'Reg': 105, 'Destination': 180, 'Due': 60, 'Stop Id': 70, 'Stop Name': 395});
				}
			}
			if (line.direction == 3) {
				out += "</tr><tr><td>" + line['fnum'] + "</td><td><a href='#" + line['reg'] + "'>" + (line['note'].length > 0 ? line['note'] : line['reg']) + "</a></td><td>" + (line['line'] != line['route'] ? line['line'] : "") + "</td><td>" + line['first_time'] + "</td><td>" + line['last_time'] + "</td>";
			} else {
				route = line['line'];
				out += "</tr><tr";
				/*if (line['sdflag'] == 1 ) {
					out += " class='sd'";
				}*/
				route = line['dest'];
				out += "><td>" + line['fnum'] + "</td><td><a href='#" + line['reg'] + "'>" + (line['note'].length > 0 ? line['note'] : line['reg']) + "</a></td><td>" + route + "</td><td>" + line['when'] + "</td><td><a href='#" + line['stop'] + "'>" + line['stop'] + "</a></td><td>" + line['stopName'] + "</td>";
			}
		}
	} else if (obj.request.cmd == "HISTORY") {
		if ('route' in obj.response.extra) {
			out += "<h2>Vehicles used on route <a target='_blank' href='http://www.tfl.gov.uk/tfl/gettingaround/maps/buses/?q=" + obj.response.extra.route + "'>" + obj.response.extra.route + "</a></h2><table><tr>" + buildHeaders({'Fleet': 110, 'Reg': 110, 'Note': 110, 'Date': 150, 'From': 80, 'Until': 0});
			for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
				if (loopIndex + 1 > obj.response.lines.length) {
					break;
				}
				line = obj.response.lines[loopIndex];
				out += "</tr><tr><td>" + line['fnum'] + "</td><td>" + line['reg'] + "</td><td>" + line['note'] + "</td><td>" + line['when'] + "</td><td>" + line['first_time'] + "</td><td>" + line['last_time'] + "</td>";
			}
		} else {
			out += "<h2>Vehicle " + obj.response.extra.op + " " + obj.response.extra.fleetNumber + " (" + obj.response.extra.reg + ")" + (obj.response.extra.note != "" ? " (" + obj.response.extra.note + ")" : "") + " has been used as follows :-</h2><table><tr>" + buildHeaders({'Route': 110, 'Date': 150, 'From': 80, 'Until': 0});
			for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
				if (loopIndex + 1 > obj.response.lines.length) {
					break;
				}
				line = obj.response.lines[loopIndex];
				out += "</tr><tr><td><a href='#" + line['route'] + "'>" + line['line'] + "</a></td><td>" + line['when'] + "</td><td>" + line['first_time'] + "</td><td>" + line['last_time'] + "</td>";
			}
		}
	} else if (obj.request.cmd == "ETA") {
		out += "<h2>Current stop/time predictions for " + obj.response.extra.operator + " " + obj.response.extra.fleetNumber + " (" + obj.response.extra.reg + ")" + (obj.response.extra.note != "" ? " (" + obj.response.extra.note + ")" : "") + " on Route " + obj.response.extra.route + "</h2>";
		dest = '';
		for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
			if (loopIndex + 1 > obj.response.lines.length) {
				break;
			}
			line = obj.response.lines[loopIndex];
			if (line.dest != undefined && line.dest != dest) {
				i += 3;
				if (dest != '') {
					out += "</tr></table>";
				}
				dest = line.dest;
				out += "<h3>Destination is " + dest + "</h3><table><tr>" + buildHeaders({'Due': 65, 'Stop Id': 75, 'Stop Name': 0});
			}
			out += "</tr><tr><td>" + line['when'] + "</td><td><a href='#" + line['stop'] + "'>" + line['stop'] + "</a></td><td>" + line['stopName'] + "</td>";
		}
	} else if (obj.request.cmd == "DUMPVEHICLE") {
		out += "<h2>Data from countdown for vehicle " + obj.response.extra.reg + ", Vehicle Id " + obj.response.extra.id + "</h2><table><tr>" + buildHeaders({'Stop Id': 75, 'Route': 70, 'Line': 70, 'Dir': 50, 'Destination': 0, 'Time': 200});
		for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
			if (loopIndex + 1 > obj.response.lines.length) {
				break;
			}
			line = obj.response.lines[loopIndex];
			out += "</tr><tr><td><a href='#" + line['stop'] + "'>" + line['stop'] + "</a></td><td>" + line['route'] + "</td><td>" + line['line'] + "</td><td>" + line['direction'] + "</td><td>" + line['dest'] + "</td><td>" + line['when'] + "</td>";
		}
	} else if (obj.request.cmd == "STOP") {
		out += "<h2>Stop information for stops ";
		if (obj.response.extra.ids.length > 1) {
			f = false;
			for (x in obj.response.extra.ids) {
				if (!f) {
					f = true;
				} else {
					out += ", ";
				}
				out += "<a href='#" + obj.response.extra.ids[x] + "'>" + obj.response.extra.ids[x] + "</a>";
			}
		} else {
			out += "<a target='_blank' href='http://www.tfl.gov.uk/tfl/gettingaround/maps/buses/?q=" + obj.response.extra.ids[0] + "'>" + obj.response.extra.ids[0] + "</a>";
		}
		out += " - " + obj.response.extra.name + "</h2><table><tr>" + buildHeaders({'Due': 75, 'Oper': 70, 'Fleet': 100, 'Reg': 110, 'Note': 110, 'Route': 70, 'Destination': 0});
		for (i=loopStart;i<loopStart+20;i++,loopIndex++) {
			if (loopIndex + 1 > obj.response.lines.length) {
				break;
			}
			line = obj.response.lines[loopIndex];
			out += "</tr><tr><td>" + line['when'] + "</td><td title='" + line['opName'] + "'>" + line['op'] + "</td><td>" + line['fnum'] + "</td><td><a href='#" + line['reg'] + "'>" + line['reg'] + "</a></td><td>" + line['note'] + "</td><td><a href='#" + line['line'] + "'>" + line['line'] + "</a></td><td>" + line['dest'] + "</td>";
		}
	}
	pageIndex[page + 1] = loopIndex;
	rText.innerHTML = out + '</tr></table></span></span><button onclick="previous()" class="l"><span><i><b>&nbsp;</b><u>&lt; Previous</u></i></span></button><button onclick="next()" class="r"><span><i><b>&nbsp;</b><u>Next &gt;</u></i></span></button>';
}

function next() {
	if (pageIndex[++page] < obj.response.lines.length) {
		displayRow();
	} else {
		page--;
	}
}

function previous() {
	if (page > 0) {
		page--;
		displayRow();
	}
}

function pushText() {
	if (txtField.val() != txtField.attr('placeholder')) {
		if (window.location.hash.substr(1) != txtField.val()) {
			document.location.hash = txtField.val();
		} else {
			startrequest();
		}
	}
}

$(function() {
	txtField = $('#l');
	button = $('#b');
	ajLoader = document.getElementsByTagName('strong')[0];
	separator = document.getElementsByTagName('hr')[1];
	rText = document.getElementById('content');

	button.click(pushText);
	txtField.keydown(
		function(e) {
			if(e.keyCode == 13) {
				pushText();
			}
		}
	);

	if (window.location.hash.length > 0) {
		txtField.val(decodeURIComponent(window.location.hash.substr(1)));
		startrequest();
	}

	window.onhashchange = function(event) {
		if (window.location.hash.length > 0) {
			txtField.val(decodeURIComponent(window.location.hash.substr(1)));
			$(txtField).removeClass('placeholder');
			startrequest();
		} else if (!firstRequest) {
			firstRequest = true;
			separator.style.display = 'none';
			$("#content").animate({opacity: 0}, 500, "easeInOutQuad");
			$("#padding").animate({height: 200}, 1000, "easeInOutQuad");
			$("#wrapper").animate({height: 120}, 600, "easeInOutQuad");
		}
	}

	$(document).bind("keydown", function(e) { if ((e.which || e.keyCode) == 116) { e.preventDefault(); window.onhashchange(); } });

	$('[placeholder]').focus(function() {
		var input = $(this);
		if (input.val() == input.attr('placeholder')) {
			input.val('');
			input.removeClass('placeholder');
		}
	}).blur(function() {
		var input = $(this);
		if (input.val() == '' || input.val() == input.attr('placeholder')) {
			input.addClass('placeholder');
			input.val(input.attr('placeholder'));
		}
	}).blur();
});


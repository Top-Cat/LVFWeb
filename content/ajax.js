function getXMLHttpRequestObject() {
	var XMLHttpRequestObject = false;
	if (window.XMLHttpRequest) {
		XMLHttpRequestObject = new XMLHttpRequest();
	} else if (window.ActiveXObject) {
		XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
	}
	if (!XMLHttpRequestObject) {
		alert("Your browser does not support Ajax.");
		return false;
	}
	return XMLHttpRequestObject;
}

var reqs = Array();
var w = 0;

function do_req(url, func, extra, data) {
	if(typeof(extra)==='undefined') extra = function(){};
	w++;
	reqs[w] = getXMLHttpRequestObject();
	if (reqs[w]) {
		if (reqs[w].readyState == 4 || reqs[w].readyState == 0) {
			reqs[w].q = extra;
			reqs[w].data = data;
			reqs[w].onreadystatechange = function() { this.q(this.readyState, this.data); if (this.readyState == 4) { if (this.status == 200) { this.t(this.responseText, this.data); } else { this.q(-1, this.data); } } };
			reqs[w].open("GET", url, true);
			reqs[w].t = func;
			reqs[w].send(null);
		}
	}
}

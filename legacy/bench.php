<?php

function doRequest($reg) {
	$k = isset($_GET['n']) ? 1 : 2;
	?><div style="clear: both">Requesting <?php print $reg; ?> ...</div><?php
	for ($i = 0; $i < $k; $i++) {
		for ($j = 0; $j < 2; $j++) {
			?><div style="float: left; margin-right: 100px; padding: 20px 0 50px; display: inline-block"><?php
			$output = array();
			$time = microtime(true);
			exec("/usr/local/bin/php ../" . ($i > 0 ? "../www/" : "") . "request.php \"" . $reg . "\"", $output);
			print "Actual Time: " . number_format(microtime(true) - $time, 3) . "<br />";
			print str_replace(array("\n", "    "), array("<br />", "<div style='width: 50px; display: inline-block'>&nbsp;</div>"), print_r(json_decode(implode("\n", $output))->response->times, true));
			?></div><?php
		}
	}
}

doRequest("4");
doRequest("LN51KZC");
doRequest("77297");
doRequest("Archway Station/Junction Road");
doRequest("HISTORY 4");
doRequest("HISTORY LN51KZC");
doRequest("LIST ADS");
doRequest("ETA LU DE77");
doRequest("LN51-2");

?>
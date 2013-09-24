<?php $title = "Statistics"; ?>
<div id="hpadding"></div>
<div id="help">
	<h2>Request Type</h2>
	<div id="lvf_type_graph" style="margin: auto; width: 1000px; height: 500px;"></div>
	<script src="https://www.google.com/jsapi"></script>
	<script>
		google.load("visualization", "1", {packages:["corechart"]});
		google.setOnLoadCallback(drawChart);
		function drawChart() {
			var chart2 = new google.visualization.AreaChart(document.getElementById('lvf_type_graph'));
			var data2 = new google.visualization.DataTable();
			data2.addColumn('date', 'Day');
			data2.addColumn('number', 'Route');
			data2.addColumn('number', 'Vehicle');
			data2.addColumn('number', 'Stop');
			data2.addColumn('number', 'Error');
			data2.addColumn('number', 'History');
			data2.addColumn('number', 'ETA');
			data2.addColumn('number', 'List');
<?php
	$c = query(
		'lvf_stats',
		'find',
		array(
			array(
				'date' => array(
					'$gt' => new MongoDate(strtotime("midnight -21 days")),
					'$lt' => new MongoDate(strtotime("today"))
				)
			)
		)
	)->sort(array('date' => 1));

	function i($val) {
		if (isset($val)) {
			return $val;
		}
		return 0;
	}

	while ($c->hasNext()) {
		$row = $c->getNext();
		print "\n			data2.addRow([new Date('" . date("Y/n/j", $row['date']->sec) . "'), " . i($row['route_req']) . ", " . i($row['veh_req']) . ", " . i($row['stop_req']) . ", " . i($row['error_req']) . ", " . i($row['HISTORY']) . ", " . i($row['ETA']) . ", " . i($row['list_req']) . "]);";
	}
	print "\n\n";
?>
			var options2 = {
				isStacked: true,
				backgroundColor: '#F7F7F7',
				hAxis: {textPosition: 'none', minorGridlines: {count: 3}},
				chartArea: {left: 50, top: 20, width: 840, height: 440}
			};
			chart2.draw(data2, options2);
		}
	</script>
</div>
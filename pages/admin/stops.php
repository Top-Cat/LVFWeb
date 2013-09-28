<?php

if (isset($_GET['stop'])) {
	if (isset($_GET['save'])) {
		query(
			"lvf_stops",
			"update",
			array(
				array(
					'_id' => $_GET['stop']
				),
				array(
					'$set' => array(
						'name' => $_GET['name']
					)
				),
				array(
					'upsert' => true
				)
			)
		);
	} elseif (isset($_GET['delete'])) {
		query(
			"lvf_stops",
			"remove",
			array(
				array(
					'_id' => $_GET['stop']
				)
			)
		);
	} else {
		$row = query("lvf_stops", "findOne", array(array('_id' => $_GET['stop'])));
		print $row['name'];
	}
} else {
?>
	<form>
		<label>Stop Id: <input type="text" id="stop_find" /></label>
		<hr style="display: block" />
		<label>Stop Name: <input type="text" id="stop_name" /></label>
		<div class="c"><button type="button" onclick="stopsave()"><span><i><b></b><u>Save</u></i></span></button><?php
		?><button type="button" onclick="stopdelete()"><span><i><b></b><u>Delete</u></i></span></button></div>
	</form>
<?php } ?>

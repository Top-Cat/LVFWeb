<?php

function check_veh_update ( $vid, $creg ) {
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$insert_vehicles = 0 ;
	$insert_where_seen = 0 ;
	$newuvi = $vid ;
	$row = query('lvf_vehicles', 'findOne', array(array('vid' => $vid)));
	if (!is_null($row)) {
//		$audit_text = "read row registration = " . $creg . ", VehicleId = " . $vid ;
//		mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
		$cdnreg = $row['cdreg'] ;
		$keep = isset($row['keep']) ? $row['keep'] : false ;
		$uvi = $row['uvi'] ;
		$realreg = $row['cur_reg'] ;
		$origreg = $row['orig_reg'] ;
		$ooper = isset($row['operator']) ? $row['operator'] : "UN" ;
		$ofnum = isset($row['fnum']) ? $row['fnum'] : "" ;
		if ( $cdnreg != $creg ) {
			$ncreg = str_replace(".", "", $creg ) ;
//			if ( $ncreg != $creg ) {
//				$audit_text = "check_veh_update - before registration = " . $creg . ", after registration = " . $ncreg . ", vid = " . $vid ;
//				mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");	
//			}
// registration change
		   if ( !$keep || ( $ncreg != $creg )) {
			 $result = mysql_query("SELECT vid, fnum FROM lvf_vehicles WHERE cdreg = '" . $creg . "'");
			 if ($row = mysql_fetch_assoc($result)) {
				$oldvid = $row['vid'] ;
				$nfnum = $row['fnum'] ;
				$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '98000' order by vid LIMIT 1 ");
	   			$row = mysql_fetch_assoc($result) ;
				$newvid = $row['vid'] - 1 ;
				$audit_text = "Registration already exists in vehicle data - VehicleId = " . $oldvid . ", new vid = " . $newvid . ", registration = " . $creg . ", old registration = " . $cdnreg . ", fleetnumer = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "' WHERE cdreg = '" . $creg . "'");
				mysql_query("DELETE FROM lvf_lists WHERE list_name = 'new' AND vid = '" . $oldvid . "'");
				mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $newvid . "')");
				$audit_text = "1-Deleting " . $oldvid . " from new list and adding  " . $newvid ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			 }
			 $audit_text = "Registration changed - Vid = " . $vid . ", registration new = " . $creg . ", old = " . $cdnreg . ", operator = " . $ooper . ", fleetnumer = " . $ofnum ;
			 mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			 mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			 mysql_query("UPDATE lvf_vehicles SET cdreg = '" . $creg . "', nfy = '1' WHERE vid= '" . $vid . "'");
			 if ( $cdnreg == $realreg ) {
				$audit_text = "Registration changed in vehicle data - Both original registrations were the same, vid = " . $vid . ", new registriation = " . $cdnreg ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			 }
		   } else {
			 $result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '98000' order by vid LIMIT 1 ");
	   		 $row = mysql_fetch_assoc($result) ;
			 $newvid = $row['vid'] - 1 ;
// not allowed to update current record so withdraw it - VID in use
			 mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $uvi . "'") ;
			 $audit_text = "Vehicle record withdrawn need vid - registration = " . $cdnreg . ", new Vid = " . $newvid . ", old VehicleId = " . $vid . ", uvi = " . $uvi . ", operator = " . $ooper . ", fleetnumber = " . $ofnum ;
			 mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			 $result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi = '" . $vid . "'");
			 if ($row = mysql_fetch_assoc($result)) {
				$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi > '20100' AND uvi < '21000' order by uvi desc limit 1  ");
				if ($row = mysql_fetch_assoc($result)) {
					$newuvi = $row['uvi'] + 1 ;
				}
			 }
			 $result = mysql_query("SELECT uvi, vid, fnum, operator FROM lvf_vehicles WHERE cdreg= '" . $creg . "'");
			 if ($row = mysql_fetch_assoc($result)) {
				if ($row['vid'] < '90000') {
// not allowed to update current record so withdraw it - registration in use
					$newvid = $newvid - 1 ;
					$wduvi = $row['uvi'] ;
					$oldvid = $row['vid'] ;
					$ofnum = $row['fnum'] ;
					$ooper = $row['operator'] ;
					mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $wduvi . "'") ;
					$audit_text = "Vehicle record withdrawn dup reg - registration = " . $creg . ", new Vid = " . $newvid . ", old VehicleId = " . $oldvid . ", uvi = " . $wduvi . ", operator = " . $ooper . ", fleetnumber = " . $ofnum ;
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
// check for preentered data
					$result = mysql_query("SELECT vid, uvi, operator, fnum FROM lvf_vehicles WHERE orig_reg= '" . $creg . "' AND cdreg= 'xxxxxxx'");
					if ($row = mysql_fetch_assoc($result)) {
						$oldvid = $row['vid'] ;
						mysql_query("DELETE FROM lvf_lists WHERE list_name = 'new' AND vid = '" . $oldvid . "'");
						mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
						$audit_text = "6-Deleting " . $oldvid . " from new list and adding  " . $vid ;
						mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
						mysql_query("UPDATE lvf_vehicles SET vid = '" . $vid . "', uvi = '" . $newuvi . "', nfy = '1', keep = 'Y' WHERE orig_reg = '" . $creg . "' AND cdreg= 'xxxxxxx'") ;
						mysql_query("UPDATE lvf_vehicles SET cdreg = '" . $creg . "' WHERE vid = '" . $vid . "'") ;
						$audit_text = "Found pre-entered data in vehicles table - Vid = " . $vid . ", uvi = " . $newuvi . ", Registration = " . $creg . ", Operator = " . $row['operator'] . ", Fleet number = " . $row['fnum'] ;
						mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					} else {
						mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
						$audit_text = $vid . " added to new list" ;
						mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
						mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep, operator ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N', '" . $ooper . "' )");
						mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
						$audit_text = "New entry in vehicles table - Vid = " . $vid . " uvi = " . $newuvi  . " Regns = " . $creg  ;
						mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					}
				} else {
					$oldvid = $row['vid'] ;
					$audit_text = "New registration already in database (prepopuated), - Old VehicleId = " . $oldvid . ", New VehicleId = " . $vid . ", reg = " . $creg . ", fleetnumber = " . $row['fnum'] ;
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("UPDATE lvf_vehicles SET vid= '" . $vid . "', uvi = '" . $newuvi . "', nfy ='1' WHERE cdreg= '" . $creg . "'") ;
					mysql_query("DELETE FROM lvf_lists WHERE list_name = 'new' AND vid = '" . $oldvid . "'");
					mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
					$audit_text = "2-Deleting " . $oldvid . " from new list and adding  " . $vid ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				}
			} else {
				mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep, operator ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N', '" . $ooper . "' )");
				mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
				$audit_text = "New entry in vehicles table - Vid = " . $vid . " uvi = " . $newuvi  . " Regns = " . $creg  ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
				$audit_text = $vid . " added to new list" ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			}
		   }
		}
	} else {
// vid not currently in lvf_vehicles
//		$audit_text = "did not read record - registration = " . $creg . ", VehicleId = " . $vid ;
//		mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
		$row = query('lvf_vehicles', 'findOne', array(array('cdreg' => $creg)));
		if (!is_null($row)) {
// pre-populated or vehicle changing operators
			$oldvid = $row['vid'] ;
			$olduvi = $row['uvi'] ;
			$nfnum = $row['fnum'] ;
			$origreg = $row['orig_reg'] ;
			$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi = '" . $vid . "'");
			if ($row = mysql_fetch_assoc($result)) {
				$result = mysql_query("SELECT uvi FROM lvf_vehicles WHERE uvi > '20100' AND uvi < '21000' order by uvi desc limit 1  ");
				if ($row = mysql_fetch_assoc($result)) {
					$newuvi = $row['uvi'] + 1 ;
				}
			}
			if ( $oldvid > 90000 ) {
// pre-populated
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $vid . "', uvi = '" . $newuvi . "', nfy = '1' WHERE cdreg = '" . $creg . "'") ;
				$audit_text = "Vehicle id changed in pre-entered vehicle data - registration = " . $creg . ", new Vid = " . $vid . ", new UVI = " . $newuvi . ", old VehicleId = " . $oldvid . ", fleetnumber = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("DELETE FROM lvf_lists WHERE list_name = 'new' AND vid = '" . $oldvid . "'");
					mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
					$audit_text = "3-Deleting " . $oldvid . " from new list and adding  " . $vid ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
			} else {
// registration changing vids
				$result = mysql_query("SELECT vid FROM lvf_vehicles WHERE vid > '98000' order by vid LIMIT 1 ");
	   			$row = mysql_fetch_assoc($result) ;
				$newvid = $row['vid'] - 1 ;
				mysql_query("UPDATE lvf_vehicles SET vid = '" . $newvid . "', cdreg = '', nfy = '1' WHERE uvi = '" . $olduvi . "'") ;
				$audit_text = "Vehicle id changed in vehicle data - registration = " . $creg . ", new Vid = " . $newvid . ", old VehicleId = " . $oldvid . ", old uvi = " . $olduvi . ", fleetnumber = " . $nfnum ;
				mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				mysql_query("DELETE FROM lvf_where_seen WHERE vid = '" . $oldvid . "'") ;
				$audit_text = "record deleted from lvf_where_seen - VehicleId = " . $oldvid ;
				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");

// check whether a pre-entered cdreg = xxxx exists
				$result = mysql_query("SELECT vid, uvi, operator, fnum FROM lvf_vehicles WHERE orig_reg= '" . $creg . "' AND cdreg= 'xxxxxxx'");
				if ($row = mysql_fetch_assoc($result)) {
					$oldvid = $row['vid'] ;
					mysql_query("DELETE FROM lvf_lists WHERE list_name = 'new' AND vid = '" . $oldvid . "'");
					mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
					$audit_text = "4-Deleting " . $oldvid . " from new list and adding  " . $vid ;
					mysql_query("UPDATE lvf_vehicles SET vid = '" . $vid . "', uvi = '" . $newuvi . "', nfy = '1', keep = 'Y' WHERE orig_reg = '" . $creg . "' AND cdreg= 'xxxxxxx'") ;
					mysql_query("UPDATE lvf_vehicles SET cdreg = '" . $creg . "' WHERE vid = '" . $vid . "'") ;
					$audit_text = "Found pre-entered data in vehicles table - Vid = " . $vid . ", uvi = " . $newuvi . ", Registration = " . $creg . ", Operator = " . $row['operator'] . ", Fleet number = " . $row['fnum'] ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				} else {
					mysql_query("INSERT INTO lvf_vehicles (cdreg, vid, uvi, cur_reg, orig_reg, note, nfy, keep ) VALUES ('" . $creg . "', '" . $vid . "', '" . $newuvi . "', '" . $creg . "', '" . $creg . "', '', '1', 'N' )");
					mysql_query("UPDATE lvf_config SET insert_veh = insert_veh + 1 ");
					$audit_text = "New entry in vehicles table - Vid = " . $vid . ", uvi = " . $newuvi . " Regns = " . $creg  ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_lists ( list_name, vid) VALUES ('new', '" . $vid . "')");
					$audit_text = "5-added to new list - vid = " . $vid ;
					mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
					mysql_query("INSERT INTO lvf_audit ( audit_datetime, Audit_text) VALUES ('" . $nowdatetime . "', '" . $audit_text . "')");
				}
			}
		}
		else {
// new vehicle record
			if (( $vid == '0' ) || ( $vid == '' )) {
				debug::info("record with null vid, second check = " . $r[0] . ", " . $r[1] . ", " . $r[2] . ", " . $r[3] . ", " . $r[4] . ", " . $r[5] . ", " . $r[6] . ", " . $r[7] . ", " . $r[8]);
			} else {
				$uvi = $vid ;
				$row = query('lvf_vehicles', 'findOne', array(array('uvi' => $vid)));
				if (!is_null($row)) {
					$c = query('lvf_vehicles', 'find', array(array('uvi' => array('$gt' => 20100, '$lt' => 21000))));
					$c->sort(array('uvi' => -1))->limit(1);
					if ($c->hasNext()) {
						$row = $c->getNext();
						$uvi = $row['uvi'] + 1 ;
					}
				}
				query(
					'lvf_vehicles',
					'insert',
					array(
						array(
							'cdreg' => $creg,
							'vid' => $vid,
							'uvi' => $uvi,
							'cur_reg' => $creg,
							'orig_reg' => $creg,
							'lists' => array('new')
						)
					)
				);
				query('lvf_config', 'update', array(array(), array('$inc' => array('insert_veh' => 1))));
				$audit_text = "New entry in vehicles table - Vid = " . $vid . ", uvi = " . $uvi . ", Regns = " . $creg  ;
				debug::info($audit_text);
				$audit_text = $vid . " added to new list" ;
				debug::info($audit_text);
			}
		}
	}
}

 function update_destrec ( $route, $lineid, $dir, $destcount, $dest ) {

	date_default_timezone_set('Europe/London');
	$nowdatetime = date( "Y-m-d H:i:s" ) ;
	$dday = date( "D"  ) ;

	// $audit_text = "counted destination data, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest ;
	// mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
	$result = mysql_query("SELECT rday, dest_cnt FROM lvf_destinations WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND rday = '" . $dday . "' AND direction = '" . $dir . "' AND destination = '" . $dest . "'" );
	if ($row = mysql_fetch_assoc($result)) {
		// $audit_text = "found destination data record matching with day set, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest . ", day = " . $row['rday'] . ", count = " . $row['dest_cnt'] ;
		// mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
		$destcount += $row['dest_cnt'] ;
		$day = $row['rday'] ;
		mysql_query("UPDATE lvf_destinations SET dest_cnt = '" . $destcount . "' WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND day = '" . $day . "' AND direction = '" . $dir . "' AND destination = '" . $dest . "'");
	} else {
		$result = mysql_query("SELECT day, dest_cnt FROM lvf_destinations WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND direction = '" . $dir . "' AND destination = '" . $dest . "'" );
		if ($row = mysql_fetch_assoc($result)) {
			// $audit_text = "found destination data record matching, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest . ", day = " . $row['day'] . ", count = " . $row['dest_cnt'] ;
			// mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
			$destcount += $row['dest_cnt'] ;
			$day = $row['day'] ;
			mysql_query("UPDATE lvf_destinations SET dest_cnt = '" . $destcount . "' WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND direction = '" . $dir . "' AND destination = '" . $dest . "'");
		} else {
			$result = mysql_query("SELECT day FROM lvf_destinations WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND direction = '" . $dir . "' order by day DESC" );
			if ($row = mysql_fetch_assoc($result)) {
				$day = $row['day'] ;
				if (( $day == "" ) || ( substr( $day, 0, 2 ) != "SD" )) {
					$newday = "SD1" ;
				} else {
					$dayseq = substr( $day, 2, 1 ) ;
					$dayseq += 1 ;
					$newday = "SD" . $dayseq ;
				}
				$day = "" ;
				mysql_query("INSERT INTO lvf_destinations ( route, Lineid, direction, day, rday, destination, dest_cnt ) VALUES ('" . $route . "', '" . $lineid . "', '" . $dir . "', '" . $newday . "', '" . $day . "', '" . $dest . "', '" . $destcount . "')");
				$audit_text = "new destination data record, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest . ", last day value = " . $day . ", new day value = " . $newday ;
				mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
				$day = $newday ;
			
			} else {
				$day = "" ;
				mysql_query("INSERT INTO lvf_destinations ( route, Lineid, direction, day, rday, destination, dest_cnt ) VALUES ('" . $route . "', '" . $lineid . "', '" . $dir . "', '" . $day . "', '" . $day . "', '" . $dest . "', '" . $destcount . "')");
				$audit_text = "no current destination data record, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest ;
				mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
			}
		}
	}
	if (( $day != "" ) && ( $day != $lineid) && ( $day != $dday )) {
//		$audit_text = "here to check whether SD record greater than base, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest . ", day = " . $day ;
//		mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
		$result = mysql_query("SELECT destination, dest_cnt, notify FROM lvf_destinations WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND direction = '" . $dir . "' AND day = ''" );
		if ($row = mysql_fetch_assoc($result)) {
//			$audit_text = "read base record, dest = " . $row['destination'] . ", count = " . $row['dest_cnt'] ;
//			mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
			if (( $destcount > ( $row['dest_cnt'] + 10 ) ) && ( $row['notify'] == '0' )) {
				$tempdest = str_replace("'", "", $row['destination'] ) ;
				$audit_text = "destination record higher usage than base, route = " . $route . ", lineid = " . $lineid  . ", dir = " . $dir . ", newdest = " . $dest . ", olddest = " . $tempdest . ", newcount = " . $destcount . ", oldcount = " . $row['dest_cnt'] ;
				mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
				mysql_query("UPDATE lvf_destinations set notify = '1' WHERE route = '" . $route . "' AND Lineid = '" . $lineid . "' AND direction = '" . $dir . "' AND day = ''");
			}
		} else {
			$audit_text = "unable to read base record, route = " . $route . ", lineid = " . $lineid . ", dir = " . $dir . ", count = " . $destcount . ", dest = " . $dest ;
			mysql_query("INSERT INTO lvf_audit_audit ( Audit_text, audit_datetime ) VALUES ('" . $audit_text . "', '" . $nowdatetime . "' )");
		}
	}
}

function update_route_day ( $vid, $lineid, $redate, $first_seen, $last_seen, $route, $reg ) {

	$result = mysql_query("SELECT last_seen, first_seen FROM lvf_route_day WHERE vehicle_id = '" . $vid . "' AND lineid = '" . $lineid . "' AND date = '" . $redate . "'");
	if ( $row = mysql_fetch_assoc($result)) {
//		$audit_text = "lvf_route_day record found, lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", new first time= " . $first_seen . ", cur first time= " . $row['first_seen'] . ", new last time= " . $last_seen . ", cur last time= " . $row['last_seen'] ;
//		mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
		if ( $last_seen > $row['last_seen'] ) {
			mysql_query("UPDATE lvf_route_day SET last_seen = '" . $last_seen . "' WHERE vehicle_id = '" . $vid . "' AND lineid = '" . $lineid . "' AND date = '" . $redate . "' ") ;
			update_route_day_audit (  "setting new last_seen in lvf_route_day record found", $vid, $lineid, $redate, $row['last_seen'], $last_seen ) ;
		}
		if ( $first_seen < $row['first_seen'] ) {
			mysql_query("UPDATE lvf_route_day SET first_seen = '" . $first_seen . "' WHERE vehicle_id = '" . $vid . "' AND lineid = '" . $lineid . "' AND date = '" . $redate . "' ") ;
			update_route_day_audit (  "setting new first_seen in lvf_route_day record found", $vid, $lineid, $redate, $first_seen, $row['first_seen'] ) ;
		}
	} else {
		mysql_query("INSERT INTO lvf_route_day (route, lineid, last_seen, first_seen, vehicle_id, date, registration) VALUES ('" . $route . "', '" . $lineid . "', '" . $last_seen . "', '" . $first_seen . "' , '" . $vid . "', '" . $redate . "', '" . $reg .  "')");
//		$audit_text = "writing new lvf_route_day record lineid= " . $lineid . ", route= " . $route . ", vid= " . $vid . ", date= " . $redate . ", first_seen = " . $first_seen . ", last_seen = " . $last_seen . ", registration = " . $reg ;
//		mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
	}

}

function update_route_day_audit (  $text, $vid, $lineid, $redate, $lower, $upper ) {
			$uppersecs = (substr( $upper, 0, 2) * 3600) + (substr( $upper, 3, 2) * 60) + (substr( $upper, 6, 2));
			$lowersecs = (substr( $lower, 0, 2) * 3600) + (substr( $lower, 3, 2) * 60) + (substr( $lower, 6, 2));
			if (( $uppersecs - $lowersecs ) < 60 ) {
//				$audit_text = "Difference less than a minute - " . $text . ", lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", lower = " . $lower . ", upper = " . $upper;
//				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
//			} elseif (( $uppersecs - $lowersecs ) < 300 ) {
//				$audit_text = "Difference < 5 minutes - " . $text . ", lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", lower = " . $lower . ", upper = " . $upper;
//				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
			} elseif (( $uppersecs - $lowersecs ) < 600 ) {
//				$audit_text = "Difference 5 - 10 minutes - " . $text . ", lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", lower = " . $lower . ", upper = " . $upper;
//				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
			} elseif (( $uppersecs - $lowersecs ) < 1200 ) {
//				$audit_text = "Difference 10 - 20 minutes - " . $text . ", lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", lower = " . $lower . ", upper = " . $upper;
//				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
			} else {
//				$audit_text = "Big Difference - " . $text . ", lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", lower = " . $lower . ", upper = " . $upper;
//				mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
			}
}

function update_route_day_route ( $route ) {

//	$audit_text = "running update_route_day_route for route "  . $route ;
//	mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
	$lastvid = 0 ;
	$lineid = ' ' ;
	$result = mysql_query("SELECT lineid, vid, redate, route, retime, reg FROM lvf_route_event WHERE redate = '" . date("Y-m-d") . "' AND route = '" . $route . "' AND used = 'N' ORDER BY vid, lineid, retime");
	$route = ' ' ;
	while ($row = mysql_fetch_assoc($result)) {
		$vid = $row['vid'] ;
	//	$audit_text = "read route_day_event record, vid = " . $vid . ", route = " . $row['route'] . ", lineid = " . $row['lineid'] . ", date = " . $row['redate'] .  ", time = " . $row['retime'];
	//	mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
		if (( $vid != $lastvid ) || ( $lineid != $row['lineid'] )) {
			if ( $lastvid != 0 ) {
				update_route_day ( $lastvid, $lineid, $redate, $first_seen, $last_seen, $route, $reg ) ;
			}
			$lastvid = $vid ;
			$route = $row['route'] ;
			$lineid = $row['lineid'] ;
			$redate = $row['redate'] ;
			$last_seen = $row['retime'] ;
			$first_seen = $row['retime'] ;
			$reg = $row['reg'] ;
		} else {
			$last_seen = $row['retime'] ;
		}
	}
	if ( $lastvid != 0 ) {
		update_route_day ( $lastvid, $lineid, $redate, $first_seen, $last_seen, $route, $reg ) ;
	}
	mysql_query("UPDATE lvf_route_event set used = 'Y' WHERE redate = '" . date("Y-m-d") . "' AND route = '" . $route . "'");
}

function update_route_day_vid ( $vid ) {

//	$audit_text = " running update_route_day_vid vid = " . $vid ;
//	mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
	$route = ' ' ;
	$result = mysql_query("SELECT lineid, vid, redate, route, retime, reg FROM lvf_route_event WHERE redate = '" . date("Y-m-d") . "' AND vid = '" . $vid . "' AND used = 'N' ORDER BY route, lineid, retime");
	while ($row = mysql_fetch_assoc($result)) {
		if (( $route != $row['route'] ) || ( $lineid != $row['lineid'] )) {
			if ( $route != ' ' ) {
//			$audit_text = "here to update lvf_route_day record , lineid= " . $lineid . ", vid= " . $vid . ", date= " . $redate . ", new first time= " . $first_seen . ", cur first time= " . $row['first_seen'] . ", new last time= " . $last_seen . ", cur last time= " . $row['last_seen'] ;
//			mysql_query("INSERT INTO lvf_audit_audit ( audit_datetime, Audit_text) VALUES ('" . date( "Y-m-d H:i:s" ) . "', '" . $audit_text . "')");
				update_route_day ( $vid, $lineid, $redate, $first_seen, $last_seen, $route, $reg ) ;
			}
			$vid = $row['vid'] ;
			$route = $row['route'] ;
			$lineid = $row['lineid'] ;
			$redate = $row['redate'] ;
			$last_seen = $row['retime'] ;
			$first_seen = $row['retime'] ;
			$reg = $row['reg'] ;
		} else {
			$last_seen = $row['retime'] ;
		}
	}
	if ( $route != ' ' ) {
		update_route_day ( $vid, $lineid, $redate, $first_seen, $last_seen, $route, $reg ) ;
	}
	mysql_query("UPDATE lvf_route_event set used = 'Y' WHERE redate = '" . date("Y-m-d") . "' AND vid = '" . $vid . "'");
}

?>
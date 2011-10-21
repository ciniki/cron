<?php
//
// Description
// -----------
// This function will calculate the next time the cron job should be executed
//
// Info
// ----
// status:		beta
//
// Arguments
// ---------
// h:
// m:
// dom:
// mon: 
// dow: 
//
function ciniki_cron_calcNextExec($ciniki, $h, $m, $dom, $mon, $dow) {

	//
	// FIXME: This needs some serious work, right now it only accepts a hour and minute
	// 		  for every day of the month
	//
	$cur_ts = date_create(NULL, timezone_open('UTC'));
	$next_exec_ts = $cur_ts;
	if( $h >= 0 && $m >= 0 && $dom == '*' && $mon == '*' && $dow == '*' ) {
		date_time_set($next_exec_ts, $h, $m);
		if( $next_exec_ts <= $cur_ts ) {
			date_modify($next_exec_ts, '+1 day');
		}
	}

	return array('stat'=>'ok', 'next'=>array('utc'=>date_format($next_exec_ts, 'Y-m-d H:i:s')));
}

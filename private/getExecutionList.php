<?php
//
// Description
// -----------
// This function will retrieve the list of cron jobs that need to be execute now.
//
// Info
// ----
// status:		beta
//
// Arguments
// ---------
//
// Returns
// -------
// <cronjobs>
//	<cronjob business_id="1" method="ciniki.wineproduction.emailXLSBackup" args="{serialized_data}"
// </cronjobs>
//
function ciniki_cron_getExecutionList($ciniki) {

	//
	// Get the list of cron job which need to be run.  These should
	// be listed in ascending order by next_exec so the oldest cron jobs
	// are run last.  That way if one cron crashes the system, it will filter
	// to the last and not interfere with new scheduled cron jobs
	//
	$strsql = "SELECT id, business_id, h, m, dom, mon, dow, method, serialized_args "
		. "FROM ciniki_cron "
		. "WHERE status = 1 "
		. "AND next_exec < UTC_TIMESTAMP() "
		. "ORDER BY next_exec ASC "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbRspQuery.php');
	$rc = ciniki_core_dbRspQuery($ciniki, $strsql, 'cron', 'cronjobs', 'cronjob', array('stat'=>'ok'));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return $rc;
}

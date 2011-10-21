<?php
//
// Description
// -----------
// This function is a generic wrapper that can call any method located in a cron folder.
// It takes an array as an argument, and withing that must
// contain api_key, and method.
//
// Info
// ----
// status:		beta
//
// Arguments
// ---------
// api_key:		The key assigned to the client application.  This
//				will be verified in the core_api_keys module
//
// auth_token:	The auth_token is assigned after authentication.  If
//				auth_token is blank, then only certain method calls are allowed.
//
// method:		The method to call.  This is a decimal notated
//
// format:		What is the requested format of the response.  This can be
//				xml, html, tmpl or hash.  If the request would like json, 
//				xml-rpc, rest or php_serial, then the format
//
function ciniki_cron_execCronMethod($ciniki, $cronjob) {

	//
	// Check the business_id has the cron module enabled
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	$strsql = "SELECT businesses.id FROM businesses "
		. "WHERE businesses.id = '" . ciniki_core_dbQuote($ciniki, $cronjob['business_id']) . "' "
		. "AND businesses.status = 1 "
		. "AND (businesses.modules & 0x1000000) = 0x1000000 "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'businesses', 'business');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['business']) || !isset($rc['business']['id']) || $rc['business']['id'] != $cronjob['business_id'] ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'444', 'msg'=>'Unable to validate business'));
	}

	//
	// Start a transaction
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'cron');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Update the status to running, if not already
	// Verify the next_exec is still < UTC_TIMESTAMP (locking check)
	//
	$strsql = "UPDATE cron "
		. "SET status = 2 "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $cronjob['id']) . "' "
		. "AND status = 1 "
		. "AND next_exec < UTC_TIMESTAMP() "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'cron');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return $rc;
	}
	if( $rc['num_affected_rows'] != 1 ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return array('stat'=>'fail', 'err'=>array('code'=>'443', 'msg'=>'Unable to lock cron'));
	}

	//
	// Parse the method, and the function name
	//
	$method_filename = $ciniki['config']['core']['modules_dir'] . '/' 
		. preg_replace('/ciniki\.(.*)\./', '\1/cron/', $cronjob['method']) . '.php';

	$method_function = preg_replace('/\./', '_', $cronjob['method']);

	//
	// Check if the method exists, after we check for authentication,
	// because we don't want people to be able to figure out valid
	// function calls by probing.
	//
	if( $method_filename == '' || $method_function == '' || !file_exists($method_filename) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return array('stat'=>'fail', 'err'=>array('code'=>'445', 'msg'=>'Method does not exist'));
	}

	//
	// Include the method function
	//
	require_once($method_filename);

	if( !is_callable($method_function) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return array('stat'=>'fail', 'err'=>array('code'=>'446', 'msg'=>'Method does not exist'));
	}

	$method_ciniki = $ciniki;
	
	$cronjob['args'] = unserialize($cronjob['serialized_args']);
	$method_rc = $method_function($ciniki, $cronjob);

	//
	// Save the result in the cron_logs table
	//
	$strsql = "INSERT INTO cron_logs (cron_id, status, result, date_added) "
		. "VALUES ('" . ciniki_core_dbQuote($ciniki, $cronjob['id']) . "' "
		. ", '" . ciniki_core_dbQuote($ciniki, $method_rc['stat']) . "' "
		. ", '" . ciniki_core_dbQuote($ciniki, serialize($method_rc)) . "' "
		. ", UTC_TIMESTAMP()) ";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'cron');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return $rc;
	}

	//
	// Calculate the next scheduled cron for this job
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/cron/private/calcNextExec.php');
	$rc = ciniki_cron_calcNextExec($ciniki, $cronjob['h'], $cronjob['m'], $cronjob['dom'], $cronjob['mon'], $cronjob['dow']);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return $rc;
	}
	if( !isset($rc['next']) || !isset($rc['next']['utc']) || $rc['next']['utc'] == '' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return array('stat'=>'fail', 'err'=>array('code'=>'447', 'msg'=>'Unable to calculate next event'));
	}
	$next_exec_utc = $rc['next']['utc'];
	
	//
	// Unlock and schedule the next transaction
	//
	$strsql = "UPDATE cron "
		. "SET status = 1 "
		. ", last_status = '" . ciniki_core_dbQuote($ciniki, $method_rc['stat']) . "' "
		. ", last_exec = UTC_TIMESTAMP() "
		. ", next_exec = '" . ciniki_core_dbQuote($ciniki, $next_exec_utc) . "' "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $cronjob['id']) . "' "
		. "AND status = 2 "
		. "";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'cron');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return $rc;
	}
	if( $rc['num_affected_rows'] != 1 ) {
		ciniki_core_dbTransactionRollback($ciniki, 'cron');
		return array('stat'=>'fail', 'err'=>array('code'=>'448', 'msg'=>'Unable to lock cron'));
	}

	//
	// Commit any transactions
	//
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'cron');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok');
}

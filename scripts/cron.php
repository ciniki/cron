<?php
//
// Description
// -----------
// The cron.php file is the entry point to start cron jobs.  The list of jobs
// to run and when is stored in the cron table in the cron module.
// 
// This script will be excluded from being able to be run from .htaccess file.
//

//
// Initialize Moss by including the ciniki_api.php
//
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
	$ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}
// loadMethod is required by all function to ensure the functions are dynamically loaded
require_once($ciniki_root . '/ciniki-api/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-api/core/private/init.php');
require_once($ciniki_root . '/ciniki-api/cron/private/execCronMethod.php');
require_once($ciniki_root . '/ciniki-api/cron/private/getExecutionList.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
	error_log("unable to initialize core");
	exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];

//
// Get list of cron jobs
//
$rc = ciniki_cron_getExecutionList($ciniki);
if( $rc['stat'] != 'ok' ) {
	error_log("unable to get cronjobs");
	exit(1);
}

if( !isset($rc['cronjobs']) ) {
	exit(0);
}

foreach($rc['cronjobs'] as $cid) {
	$rc = ciniki_cron_execCronMethod($ciniki, $cid['cronjob']);
	if( $rc['stat'] != 'ok' ) {
		print "Cronjob " . $cid['cronjob']['id'] . " failed - #" . $rc['err']['code'] . ": " . $rc['err']['msg'] . "\n";
	}
}

exit(0);
?>

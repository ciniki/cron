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
require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
	error_log("unable to initialize core");
	exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];
$ciniki['session']['user']['id'] = -3;	// Setup to Ciniki Robot

//
// Get list of cron jobs **Not currently used**
//
ciniki_core_loadMethod($ciniki, 'ciniki', 'cron', 'private', 'getExecutionList');
ciniki_core_loadMethod($ciniki, 'ciniki', 'cron', 'private', 'execCronMethod');
ciniki_core_loadMethod($ciniki, 'ciniki', 'cron', 'private', 'logMsg');
$rc = ciniki_cron_getExecutionList($ciniki);
if( $rc['stat'] != 'ok' ) {
	error_log("unable to get cronjobs");
	exit(1);
}

if( isset($rc['cronjobs']) ) {
	foreach($rc['cronjobs'] as $cid) {
		$rc = ciniki_cron_execCronMethod($ciniki, $cid['cronjob']);
		if( $rc['stat'] != 'ok' ) {
			print "CRON-ERR: " . $cid['cronjob']['id'] . " failed - #" . $rc['err']['code'] . ": " . $rc['err']['msg'] . "\n";
		}
	}
}

//
// Check for module list for all packages
//
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'getModuleList');
$rc = ciniki_core_getModuleList($ciniki);
if( $rc['stat'] != 'ok' ) {
	error_log('CRON-ERR: Unable to get module list');
	exit(1);
}

if( isset($rc['modules']) ) {
	$modules = $rc['modules'];
	foreach($modules as $module) {
		$rc = ciniki_core_loadMethod($ciniki, $module['package'], $module['name'], 'cron', 'jobs');
		if( $rc['stat'] == 'ok' ) {
			$fn = $rc['function_call'];
			$rc = $fn($ciniki);
			if( $rc['stat'] != 'ok' ) {
				ciniki_cron_logMsg($ciniki, 0, array('code'=>'2622', 'msg'=>'Unable to run jobs for : ' . $module['package'] . '.' . $module['name'],
					'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err']));
			}
		}
	}
}


//
// Check for campaign mail that is queued
//
/* Move to jobs.php
if( file_exists($ciniki_root . '/ciniki-mods/campaigns/cron/checkQueue.php') ) {
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'cron', 'checkQueue');
	$rc = ciniki_campaigns_cron_checkQueue($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.campaigns.checkQueue failed (" . serialize($rc['err']) . ")");
	}
} */

//
// Check for fatt mail that needs to be sent
//
/* moved to jobs.php
if( file_exists($ciniki_root . '/ciniki-mods/fatt/cron/sendMessages.php') ) {
	ciniki_core_loadMethod($ciniki, 'ciniki', 'fatt', 'cron', 'sendMessages');
	$rc = ciniki_fatt_cron_sendMessages($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.fatt.sendMessages failed (" . serialize($rc['err']) . ")");
	}
} */

//
// Check for mail to be sent
// 
/* Moved to jobs.php
if( file_exists($ciniki_root . '/ciniki-mods/mail/cron/checkMail.php') ) {
	ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'cron', 'checkMail');
	$rc = ciniki_mail_cron_checkMail($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.mail.checkMail failed (" . serialize($rc['err']) . ")");
	}
} */

//
// Check for updateFeeds file to update ciniki.newsaggregator feeds
//
/*
if( file_exists($ciniki_root . '/ciniki-mods/newsaggregator/cron/updateFeeds.php') ) {
	print "CRON: Updating feeds\n";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'newsaggregator', 'cron', 'updateFeeds');
	$rc = ciniki_newsaggregator_cron_updateFeeds($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.newsaggregator.updateFeeds failed (" . serialize($rc['err']) . ")");
	}
}
*/

//
// Check for recurring invoices that need to be added
//
/* Moved to jobs
if( file_exists($ciniki_root . '/ciniki-mods/sapos/cron/addRecurring.php') ) {
	print "CRON: Adding recurring invoices\n";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'cron', 'addRecurring');
	$rc = ciniki_sapos_cron_addRecurring($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.sapos.addRecurring failed (" . serialize($rc['err']) . ")");
	}
} */

//
// Check for directory updates from dropbox
//
/* Moved to jobs.php
if( file_exists($ciniki_root . '/ciniki-mods/directory/cron/dropboxUpdate.php') ) {
	print "CRON: Updating directories from Dropbox\n";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'directory', 'cron', 'dropboxUpdate');
	$rc = ciniki_directory_cron_dropboxUpdate($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.directory.dropboxUpdate failed (" . serialize($rc['err']) . ")");
	}
} */

exit(0);
?>

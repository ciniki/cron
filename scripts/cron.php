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
require_once($ciniki_root . '/ciniki-mods/cron/private/execCronMethod.php');
require_once($ciniki_root . '/ciniki-mods/cron/private/getExecutionList.php');

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
// Get list of cron jobs
//
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
// Check for campaign mail that is queued
//
if( file_exists($ciniki_root . '/ciniki-mods/campaigns/cron/checkQueue.php') ) {
	ciniki_core_loadMethod($ciniki, 'ciniki', 'campaigns', 'cron', 'checkQueue');
	$rc = ciniki_campaigns_cron_checkQueue($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.mail.checkQueue failed (" . serialize($rc['err']) . ")");
	}
}

//
// Check for mail to be sent
//
if( file_exists($ciniki_root . '/ciniki-mods/mail/cron/checkMail.php') ) {
	ciniki_core_loadMethod($ciniki, 'ciniki', 'mail', 'cron', 'checkMail');
	$rc = ciniki_mail_cron_checkMail($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.mail.checkMail failed (" . serialize($rc['err']) . ")");
	}
}

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
if( file_exists($ciniki_root . '/ciniki-mods/sapos/cron/addRecurring.php') ) {
	print "CRON: Adding recurring invoices\n";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'cron', 'addRecurring');
	$rc = ciniki_sapos_cron_addRecurring($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.sapos.addRecurring failed (" . serialize($rc['err']) . ")");
	}
}

//
// Check for directory updates from dropbox
//
if( file_exists($ciniki_root . '/ciniki-mods/directory/cron/dropboxUpdate.php') ) {
	print "CRON: Updating directories from Dropbox\n";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'directory', 'cron', 'dropboxUpdate');
	$rc = ciniki_directory_cron_dropboxUpdate($ciniki);
	if( $rc['stat'] != 'ok' ) {
		error_log("CRON-ERR: ciniki.directory.dropboxUpdate failed (" . serialize($rc['err']) . ")");
	}
}

exit(0);
?>

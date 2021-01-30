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
$ciniki['session']['user']['id'] = -3;  // Setup to Ciniki Robot

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
// Check if a specific module is to be run for this cron
//
if( isset($argv[1]) && $argv[1] != '' && $argv[1] != '-ignore' ) {
    $modules = array();
    for($i = 1; $i < count($argv); $i++ ) {
        list($pkg, $mod) = explode('.', $argv[$i]);
        $modules[] = array('package'=>$pkg, 'name'=>$mod);
    }
} else {
    //
    // Check for module list for all packages installed on this server
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'getModuleList');
    $rc = ciniki_core_getModuleList($ciniki);
    if( $rc['stat'] != 'ok' ) {
        error_log('CRON-ERR: Unable to get module list');
        exit(1);
    }

    if( isset($rc['modules']) ) {
        $modules = $rc['modules'];
    } else {
        $modules = array();
    }

    if( isset($argv[1]) && $argv[1] != '' && $argv[1] == '-ignore' && isset($argv[2]) && $argv[2] != '' ) {
        for($i = 2; $i < count($argv); $i++ ) {
            list($pkg, $mod) = explode('.', $argv[$i]);
            foreach($modules as $mod_name => $module) {
                if( $module['package'] == $pkg && $module['name'] == $mod ) {
                    unset($modules[$mod_name]);
                }
            }
        }
    }
}

//
// Check the list of modules for a cron/jobs.php script
//
foreach($modules as $mod_name => $module) {
    if( !isset($module['package']) || !isset($module['name']) ) {
        continue;
    }
    $rc = ciniki_core_loadMethod($ciniki, $module['package'], $module['name'], 'cron', 'jobs');
    if( $rc['stat'] == 'ok' ) {
        $fn = $rc['function_call'];
        $rc = $fn($ciniki);
        if( $rc['stat'] != 'ok' ) {
            ciniki_cron_logMsg($ciniki, 0, array('code'=>'ciniki.cron.8', 'msg'=>'Unable to run jobs for : ' . $module['package'] . '.' . $module['name'],
                'cron_id'=>0, 'severity'=>50, 'err'=>$rc['err']));
        }
    }
}


exit(0);
?>

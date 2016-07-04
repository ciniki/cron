<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_cron_objects($ciniki) {
    $objects['job'] = array(
        'name'=>'Cron Job',
        'sync'=>'yes',
        'table'=>'ciniki_cron',
        'fields'=>array(
            'status'=>array(),
            'm'=>array(),
            'h'=>array(),
            'dom'=>array(),
            'mon'=>array(),
            'dow'=>array(),
            'method'=>array(),
            'serialized_args'=>array(),
            ),
        );
    $objects['log'] = array(
        'name'=>'Cron Log',
        'sync'=>'yes',
        'table'=>'ciniki_cron_log',
        'fields'=>array(
            'cron_id'=>array('default'=>'0', 'ref'=>'ciniki.cron.job'),
            'severity'=>array('default'=>'10'),
            'log_date'=>array(),
            'code'=>array(),
            'msg'=>array(),
            'pmsg'=>array('default'=>''),
            'errors'=>array('default'=>''),
            ),
        );

    
    return array('stat'=>'ok', 'objects'=>$objects);
}
?>

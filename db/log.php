<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Definition of log events
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'reservation', 'action'=>'view', 'mtable'=>'reservation', 'field'=>'name'),
    array('module'=>'reservation', 'action'=>'update', 'mtable'=>'reservation', 'field'=>'name'),
    array('module'=>'reservation', 'action'=>'add', 'mtable'=>'reservation', 'field'=>'name'),
    array('module'=>'reservation', 'action'=>'grade', 'mtable'=>'reservation_request', 'field'=>'name'),
    array('module'=>'reservation', 'action'=>'reserve', 'mtable'=>'reservation_request', 'field'=>'timecreated'),
    array('module'=>'reservation', 'action'=>'delete', 'mtable'=>'reservation_request', 'field'=>'userid'),
    array('module'=>'reservation', 'action'=>'cancel', 'mtable'=>'reservation_request', 'field'=>'timecancelled'),
);

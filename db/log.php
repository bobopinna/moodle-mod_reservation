<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
    array('module' => 'reservation', 'action' => 'view', 'mtable' => 'reservation', 'field' => 'name'),
    array('module' => 'reservation', 'action' => 'update', 'mtable' => 'reservation', 'field' => 'name'),
    array('module' => 'reservation', 'action' => 'add', 'mtable' => 'reservation', 'field' => 'name'),
    array('module' => 'reservation', 'action' => 'grade', 'mtable' => 'reservation_request', 'field' => 'name'),
    array('module' => 'reservation', 'action' => 'reserve', 'mtable' => 'reservation_request', 'field' => 'timecreated'),
    array('module' => 'reservation', 'action' => 'delete', 'mtable' => 'reservation_request', 'field' => 'userid'),
    array('module' => 'reservation', 'action' => 'cancel', 'mtable' => 'reservation_request', 'field' => 'timecancelled'),
);

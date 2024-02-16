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
 * Reservation module log events definition
 *
 * @package mod_reservation
 * @copyright 2012 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = [
    ['module' => 'reservation', 'action' => 'view', 'mtable' => 'reservation', 'field' => 'name'],
    ['module' => 'reservation', 'action' => 'update', 'mtable' => 'reservation', 'field' => 'name'],
    ['module' => 'reservation', 'action' => 'add', 'mtable' => 'reservation', 'field' => 'name'],
    ['module' => 'reservation', 'action' => 'grade', 'mtable' => 'reservation_request', 'field' => 'name'],
    ['module' => 'reservation', 'action' => 'reserve', 'mtable' => 'reservation_request', 'field' => 'timecreated'],
    ['module' => 'reservation', 'action' => 'delete', 'mtable' => 'reservation_request', 'field' => 'userid'],
    ['module' => 'reservation', 'action' => 'cancel', 'mtable' => 'reservation_request', 'field' => 'timecancelled'],
];

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
 * Reservation external functions and service definitions.
 *
 * @package    mod_reservation
 * @category   external
 * @copyright  2019 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_reservation_get_requests_users' => array(
        'classname'     => 'mod_reservation\external',
        'methodname'    => 'get_requests_users',
        'description'   => 'Retrieve users ids for given requests ids.',
        'type'          => 'read',
        'capabilities'  => 'mod/reservation:viewrequest',
        'ajax'          => true,
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_reservation_get_matchvalues' => array(
        'classname'     => 'mod_reservation\external',
        'methodname'    => 'get_matchvalues',
        'description'   => 'Retrieve values from users profile given field.',
        'type'          => 'read',
        'capabilities'  => 'moodle/course:manageactivities',
        'ajax'          => true,
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_reservation_get_clashes' => array(
        'classname'     => 'mod_reservation\external',
        'methodname'    => 'get_clashes',
        'description'   => 'Retrieve time and place clashes.',
        'type'          => 'read',
        'capabilities'  => 'moodle/course:manageactivities',
        'ajax'          => true,
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_reservation_reserve_request' => array(
        'classname'     => 'mod_reservation\external',
        'methodname'    => 'reserve_request',
        'description'   => 'Add a reservation request',
        'type'          => 'write',
        'capabilities'  => 'moodle/reservation:reserve',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_reservation_cancel_request' => array(
        'classname'     => 'mod_reservation\external',
        'methodname'    => 'cancel_request',
        'description'   => 'Cancel an existing reservation request',
        'type'          => 'write',
        'capabilities'  => 'moodle/reservation:reserve',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

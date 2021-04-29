<?php
// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * Reservation module capability definition
 *
 * @package    mod_reservation
 * @copyright  2018 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_reservation' => [ // Plugin identifier.
        'handlers' => [ // Different places where the plugin will display content.
            'coursereservation' => [ // Handler unique name.
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/reservation/pix/icon.png',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate', // Delegate (where to display the link to the plugin).
                'method' => 'mobile_course_view', // Main function in \mod_reservation\output\mobile.
            ]
        ],
        'lang' => [ // Language strings that are used in all the handlers.
            ['pluginname', 'reservation'],
            ['location', 'reservation'],
            ['timestart', 'reservation'],
            ['timeend', 'reservation'],
            ['timeopen', 'reservation'],
            ['timeclose', 'reservation'],
            ['status', 'reservation'],
            ['notopened', 'reservation'],
            ['opened', 'reservation'],
            ['closed', 'reservation'],
            ['notopened', 'reservation'],
            ['reserved', 'reservation'],
            ['notreserved', 'reservation'],
            ['notgraded', 'reservation'],
            ['note', 'reservation'],
            ['yournote', 'reservation'],
            ['noterequired', 'reservation'],
            ['reserve', 'reservation'],
            ['reservecancel', 'reservation'],
            ['availablerequests', 'reservation'],
            ['overbookonly', 'reservation'],
            ['nomorerequest', 'reservation'],
            ['selectagroup', 'moodle']
        ]
    ]
];

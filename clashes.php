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

require_once('../../config.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT);
$timestartstr = required_param('timestart', PARAM_ALPHANUMEXT);
$timeendstr = optional_param('timeend', '', PARAM_ALPHANUMEXT);
$location = optional_param('location', '', PARAM_TEXT);
$reservationid = optional_param('reservation', 0, PARAM_INT);

if ($id) {
    if (! $course = $DB->get_record('course', array('id' => $id))) {
        error('Course ID is incorrect');
    }
}

require_course_login($course);

$coursecontext = context_course::instance($course->id);

require_capability('moodle/course:manageactivities', $coursecontext);

if (!isset($CFG->reservation_check_clashes)) {
    $CFG->reservation_check_clashes = 0;
}

if ($CFG->reservation_check_clashes) {

    if (!isset($CFG->reservation_min_duration)) {
        // Minimal duration an hour.
        $CFG->reservation_min_duration = 3600;
    }
    if (!empty($timestartstr)) {
        $timearray = explode('-', $timestartstr);
        $timestart = make_timestamp($timearray[0], $timearray[1], $timearray[2], $timearray[3], $timearray[4], 0, 99, true);

        $timeend = $timestart + $CFG->reservation_min_duration;

        if (!empty($timeendstr)) {
            $timearray = explode('-', $timeendstr);
            $timeend = make_timestamp($timearray[0], $timearray[1], $timearray[2], $timearray[3], $timearray[4], 0, 99, true);
        }

        if ($timestart < $timeend) {
            if ($reservations = reservation_get_reservations_by_course($course->id, $location)) {
                $strftimedaydatetime = get_string('strftimedatetime');

                $collisiontable = new html_table();
                $collisiontable->tablealign = 'center';
                $collisiontable->attributes['class'] = 'collisions ';
                $collisiontable->summary = get_string('clashesreport', 'reservation');
                $collisiontable->data = array();

                $collisiontable->head = array();
                $collisiontable->head[] = get_string('course');
                $collisiontable->head[] = get_string('modulename', 'reservation');
                $collisiontable->head[] = get_string('location', 'reservation');
                $collisiontable->head[] = get_string('timestart', 'reservation');
                $collisiontable->head[] = get_string('timeend', 'reservation');
                foreach ($reservations as $reservation) {
                    $collision = false;
                    if ($reservationid != $reservation->id) {
                        $extimestart = $reservation->timestart;
                        $extimeend = $reservation->timeend;
                        if (empty($reservation->timeend)) {
                            $extimeend = $reservation->timestart + $CFG->reservation_min_duration;
                        }

                         // Collision cases
                         //
                         // Existing       EXTS##############EXTE
                         // Test 1               TS############TE
                         // Test 2         TS############TE
                         // Test 3     TS##########################TE
                         // Test 4               TS######TE
                         // Test 5          TS################TE not checked done by others.

                        if (($timestart >= $extimestart) && ($timestart < $extimeend) && ($timeend >= $extimeend)) {
                            $collision = true;
                        }
                        if (($timestart <= $extimestart) && ($timeend > $extimestart) && ($timeend <= $extimeend)) {
                            $collision = true;
                        }
                        if (($timestart <= $extimestart) && ($timeend >= $extimeend)) {
                            $collision = true;
                        }
                        if (($timestart >= $extimestart) && ($timeend <= $extimeend)) {
                            $collision = true;
                        }
                    }
                    if ($collision) {
                        $columns = array();
                        $columns[] = format_string($reservation->coursename);
                        $columns[] = format_string($reservation->name);
                        $columns[] = format_string($reservation->location);
                        $columns[] = userdate($reservation->timestart, $strftimedaydatetime);
                        if (!empty($reservation->timeend)) {
                            $columns[] = userdate($reservation->timeend, $strftimedaydatetime);
                        } else {
                            $columns[] = html_writer::tag('em', userdate($extimeend, $strftimedaydatetime),
                                    array('class' => 'stimed'));
                        }
                        $collisiontable->data[] = $columns;
                    }
                }
                if (!empty($collisiontable->data)) {
                    echo html_writer::table($collisiontable);
                }
            }
        } else {
            echo html_writer::tag('span', get_string('err_timeendlower', 'reservation'));
        }
    }
}

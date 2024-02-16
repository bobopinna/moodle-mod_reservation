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
 * Reservation plugin view page
 *
 * @package mod_reservation
 * @copyright 2006 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('locallib.php');
require_once($CFG->libdir.'/tablelib.php');

$id = optional_param('id', null, PARAM_INT);                    // Course Module ID, or.
$r = optional_param('r', null, PARAM_INT);                      // Reservation ID.

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('reservation', $id)) {
        throw new moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', ['id' => $cm->course])) {
        throw new moodle_exception('coursemisconf');
    }
    if (! $reservation = $DB->get_record('reservation', ['id' => $cm->instance])) {
        throw new moodle_exception('invalidreservationid', 'reservation');
    }
} else if (!empty($r)) {
    if (! $reservation = $DB->get_record('reservation', ['id' => $r])) {
        throw new moodle_exception('invalidreservationid', 'reservation');
    }
    if (! $course = $DB->get_record('course', ['id' => $reservation->course])) {
        throw new moodle_exception('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
        throw new moodle_exception('invalidcoursemodule');
    }
} else {
        throw new moodle_exception('missingparam', null, null, 'id | r');
}

$userid = null;
$currentuser = null;
$status = new stdClass();
$status->view = optional_param('view', null, PARAM_ACTION);              // Full or clean view for teacher.
$status->mode = optional_param('mode', 'overview', PARAM_ACTION);        // Define the viewed tab.
$status->action = optional_param('action', null, PARAM_ACTION);          // Action on selected requests.
if (empty($status->action)) {
    if (!empty(optional_param('savegrades', null, PARAM_ACTION))) {
        $status->action = 'savegrades';                                  // Save all modified grades.
    } else if (!empty(optional_param('reserve', null, PARAM_ACTION))) {
        $status->action = 'reserve';                                     // Reserve.
        if ($status->mode != 'manage') {
            $userid = $USER->id;
        }
        if (!empty(optional_param('newparticipant', null, PARAM_INT))) {
            $status->action = 'manualreserve';
            $userid = optional_param('newparticipant', null, PARAM_INT);
        }
    } else if (!empty(optional_param('cancel', null, PARAM_ACTION))) {
        $status->action = 'cancel';                                      // Cancel a request.
    }
}
$status->download = optional_param('download', null, PARAM_ACTION);      // Null or one of available download formats.

// Check to see if groups are being used in this reservation.
$status->groupmode = groups_get_activity_groupmode($cm);
if ($status->groupmode != NOGROUPS) {
    $status->group = groups_get_activity_group($cm, true);
}

$queries = ['id' => $cm->id];
if ($status->groupmode != NOGROUPS) {
    if ($status->group !== false) {
        $queries['group'] = $status->group;
    }
}
if (!empty($status->mode)) {
    $queries['mode'] = $status->mode;
}
$url = new moodle_url('/mod/reservation/view.php', $queries);
$status->url = $url;
$PAGE->set_url($url);

require_login($course->id, false, $cm);

$now = time();

$context = context_module::instance($cm->id);

$coursecontext = context_course::instance($reservation->course);

$params = [
    'context' => $context,
    'objectid' => $reservation->id,
];
$event = \mod_reservation\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('reservation', $reservation);
$event->trigger();

// Do a manage action.
if (isset($status->action) && confirm_sesskey()) {
    switch($status->action) {
        case 'savegrades': // Store grades if set.
            if (has_capability('mod/reservation:grade', $context)) {
                $grades = optional_param_array('grades', [], PARAM_INT);
                reservation_set_grades($reservation, $USER->id, $grades);
            }
        break;
        case 'deleterequest': // Some requests need to be deleted.
            if (has_capability('mod/reservation:manualdelete', $context)) {
                $requestids = optional_param_array('requestid', [], PARAM_INT);
                reservation_delete_requests($reservation, $requestids);
            }
        break;
    }
}

// Get profile custom fields array.
$customfields = reservation_get_profilefields();

$fields = [];
if (has_capability('mod/reservation:viewrequest', $context)) {
    $fields = reservation_get_fields($customfields, $status);
}

// Set global and sublimit counters.
$counters = reservation_setup_counters($reservation, $customfields);

// Add sublimits fields to used fields.
$fields = reservation_setup_sublimit_fields($counters, $customfields, $fields);

$addableusers = [];
if ($status->mode == 'manage') {
    // Get list of users available for manual reserve.
    $addableusers = reservation_get_addableusers($reservation, $status);
}

// Get all reservation requests.
$requests = reservation_get_requests($reservation, true, $fields, $status);
$rows = [];
if (!empty($requests)) {
    // Check for requests full view.
    if (has_capability('mod/reservation:viewrequest', $context)) {
        if ($status->view == 'clean') {
            unset($_SESSION['mod_reservation'][$reservation->id]['view']);
        } else if ($status->view == 'full') {
            $_SESSION['mod_reservation'][$reservation->id]['view'] = 'full';
        } else if (isset($_SESSION['mod_reservation'][$reservation->id]['view'])) {
            $status->view = $_SESSION['mod_reservation'][$reservation->id]['view'];
        }
    } else if (has_capability('mod/reservation:reserve', $context)) {
        $status->view = 'clean';
    }

    if ($status->mode == 'manage') {
        $status->actions = [];
        if (has_capability('mod/reservation:viewrequest', $context)) {
            $status->actions['#messageselect'] = get_string('sendmessage', 'message');
        }
        if (has_capability('mod/reservation:manualdelete', $context)) {
            $status->actions['deleterequest'] = get_string('deleteselected');
        }
    }

    // Get user request information (if already reserved).
    $currentuser = reservation_get_current_user($reservation, $requests);

    $table = reservation_setup_request_table($reservation, $fields, $status);

    // Sort data as requested.
    if (has_capability('mod/reservation:viewrequest', $context)) {
        $sortby = $table->get_sort_columns();
        if (!empty($sortby)) {
            $requests = reservation_multisort($requests, $sortby);
        }
    }

    $rows = reservation_get_table_data($reservation, $requests, $addableusers, $counters, $fields, $status);
}
if (($status->view == 'full') && empty($counters[0]->deletedrequests)) {
    $status->view = 'clean';
    unset($_SESSION['mod_reservation'][$reservation->id]['view']);
}

// Set available seats in global count.
$seats = reservation_get_availability($reservation, $counters, $context);

$notice = '';
// Do a user action.
if (isset($status->action) && confirm_sesskey()) {
    $request = null;
    $redirectqueries = [];
    switch($status->action) {
        case 'manualreserve':  // Add a reservation for selected user.
            if (has_capability('mod/reservation:manualreserve', $context) && ($status->mode == 'manage') && !empty($userid)) {
                $request = new stdClass();
                $request->userid = $userid;
                $redirectqueries['mode'] = 'manage';
            } else {
                $notice = 'reservationdenied';
            }
        case 'reserve':  // Add a reservation for current user.
            if (has_capability('mod/reservation:reserve', $context) && !empty($userid)) {
                if (($now >= $reservation->timeopen) && ($now <= $reservation->timeclose)) {
                    $request = new stdClass();
                    $request->userid = $userid;
                } else {
                    $notice = 'reservationclosed';
                }
            } else {
                $notice = 'reservationdenied';
            }

            $note = optional_param('note', null, PARAM_TEXT);
            $result = reservation_reserve($reservation, $seats, $note, $userid);
            if ($result['status'] == true) {
                $redirectqueries['id'] = $cm->id;
                $redirecturl = new moodle_url('/mod/reservation/view.php', $redirectqueries);
                redirect ($redirecturl, get_string('reserved', 'reservation'), 2);
            } else {
                $notice = $result['error'];
            }
        break;
        case 'cancel':  // Cancel the reservation for current user.
            if (has_capability('mod/reservation:reserve', $context)) {
                if (reservation_cancel($reservation, $course, $cm, $context)) {
                    $strcancelled = get_string('reservationcancelled', 'reservation');
                    $redirectqueries['id'] = $cm->id;
                    $redirecturl = new moodle_url('/mod/reservation/view.php', $redirectqueries);
                    redirect ($redirecturl, $strcancelled, 2);
                } else {
                    $notice = 'notbooked';
                }
            } else {
                $notice = 'reservationdenied';
            }
        break;
    }
}

if (empty($status->download)) {
    $pagetitle = strip_tags($course->shortname.': '.format_string($reservation->name));
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);
    $renderer = $PAGE->get_renderer('mod_reservation');

    //
    // Print the page header.
    //
    echo $OUTPUT->header();

    if (!empty($notice)) {
        notice(get_string($notice, 'reservation'), 'view.php?id='.$cm->id);
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

    $renderer->print_info($reservation, $cm->id);

    if (has_capability('mod/reservation:viewrequest', $context)) {
        $renderer->print_connected($reservation);
    }

    echo $OUTPUT->box_end();

    if (has_capability('mod/reservation:viewrequest', $context)) {
        $renderer->print_counters($reservation, $counters);
        $renderer->display_tabs($reservation, $status->mode);
    }

    // Show reservation form.
    if (($status->mode == 'manage') && has_capability('mod/reservation:manualreserve', $context)) {
        if (($reservation->maxrequest == 0) || ($seats->available > 0) || ($seats->total > 0)) {
            if (isset($addableusers) && !empty($addableusers)) {
                $renderer->print_manualreserve_form($reservation, $status, $addableusers);
            }
        }
    } else if (has_capability('mod/reservation:reserve', $context)) {
        // Display general infos and student submission button.
        $renderer->print_user_request_status($reservation, $currentuser);

        $cr = reservation_reserved_on_connected($reservation, $USER->id);
        if ($cr !== false) {
            $renderer->print_reserved_on_connected($cr);
        } else if (($now >= $reservation->timeopen) && ($now <= $reservation->timeclose)) {
            // Display reservation availability.
            if (!isset($currentuser->number)) {
                echo $renderer->display_availability($reservation, $seats);
            }

            $renderer->print_reserve_form($reservation, $status, $currentuser, $seats);
        }
    }

    // Display requests table.
    $canviewlistnow = ($reservation->showrequest == 1) && ($now > $reservation->timeclose) && (is_enrolled($coursecontext));
    $canviewlistalways = ($reservation->showrequest == 2) && (is_enrolled($coursecontext));
    if (has_capability('mod/reservation:viewrequest', $context) || $canviewlistnow || $canviewlistalways) {
        echo $OUTPUT->box_start('center');

        if (has_capability('mod/reservation:viewrequest', $context)) {
            if (groups_get_all_groups($course->id) && ($status->groupmode == SEPARATEGROUPS)) {
                groups_print_activity_menu($cm, $url);
            }

            // Display view mode button.
            if (!empty($requests) && ($counters[0]->deletedrequests > 0)) {
                $renderer->print_viewtype_form($status, $counters);
            }
            echo $OUTPUT->heading(get_string('reservations', 'reservation'));
        }

        if (empty($rows)) {
            if (has_capability('mod/reservation:viewrequest', $context)) {
                echo $OUTPUT->heading(get_string('noreservations', 'reservation'));
            }
        } else {
            $renderer->print_requests_table($reservation, $table, $rows, $status, $counters, $context);
        }
        echo $OUTPUT->box_end();
    }

    // Finish the page.
    echo $OUTPUT->footer($course);
} else if (!empty($rows)) {
    $table->start_output();

    foreach ($rows as $row) {
        $table->add_data($row);
    }

    $table->finish_output();
}

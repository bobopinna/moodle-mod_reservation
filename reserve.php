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
$reserve = optional_param('reserve', null, PARAM_ALPHA);
$cancel = optional_param('cancel', null, PARAM_ALPHA);
$note = optional_param('note', null, PARAM_TEXT);
$reservationid = optional_param('reservation', null, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('reservation', $id)) {
        error('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }
    if (! $reservation = $DB->get_record('reservation', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }
} else {
    error('Bad script calling');
    exit;
}

$url = new moodle_url('/mod/reservation/reserve.php', array('id' => $cm->id));
$PAGE->set_url($url);

require_login($course->id, false, $cm);

// Update reservation.
if ($reservationid == $reservation->id) {

    $context = context_module::instance($cm->id);

    if (isset($reserve)) {
        $now = time();

        // Manage manual and students reservation.
        $request = new stdClass();
        $request->userid = null;
        $newparticipant = optional_param('newparticipant', null, PARAM_INT);

        $canreserve = has_capability('mod/reservation:reserve', $context);
        if (has_capability('mod/reservation:manualreserve', $context) && !empty($newparticipant)) {
            if ($now < $reservation->timestart) {
                $request->userid = $newparticipant;
            } else {
                $notice = 'reservationdenied';
            }
        } else if ($canreserve && ($now >= $reservation->timeopen) && ($now <= $reservation->timeclose)) {
            $request->userid = $USER->id;
        } else {
            $notice = 'reservationclosed';
        }

        // Count requests and check seats avalability.
        if (!empty($request->userid)) {
            $queryparameters = array('userid' => $request->userid, 'reservation' => $reservation->id, 'timecancelled' => '0');
            if ($DB->get_record('reservation_request', $queryparameters)) {
                $notice = 'alreadybooked';
            } else {
                $overbook = round($reservation->maxrequest * $reservation->overbook / 100);
                $available = $CFG->reservation_max_requests;

                // Get profile custom fields array.
                $customfields = reservation_get_profilefields();

                // Set counters.
                $counters = reservation_setup_counters($reservation, $customfields);
                // Set sublimits fields.
                $fields = reservation_setup_sublimit_fields($counters, $customfields);

                $requests = reservation_get_requests($reservation, false, $fields);
                if (!$requests || (count($requests) < ($reservation->maxrequest + $overbook)) || ($reservation->maxrequest == 0)) {
                    if (count($counters) - 1 > 0) {
                        if ($requests) {
                            foreach ($requests as $requestdata) {
                                $counters[0]->count++;
                                for ($i = 1; $i < count($counters); $i++) {
                                    $fieldname = $counters[$i]->field;
                                    if (($requestdata->$fieldname == $counters[$i]->matchvalue) && !$counters[$i]->operator) {
                                        $counters[$i]->count++;
                                    } else if (($requestdata->$fieldname != $counters[$i]->matchvalue) && $counters[$i]->operator) {
                                        $counters[$i]->count++;
                                    }
                                }
                            }
                        }

                        if ($reservation->maxrequest > 0) {
                            $available = min($reservation->maxrequest, ($reservation->maxrequest - $counters[0]->count));
                        }

                        if ($USER->id == $request->userid) {
                            if ($result = reservation_get_availability($reservation, $counters, $available)) {
                                $available = $result->available;
                                $overbook = $result->overbook;
                            }
                        }
                    }

                    if (($available > 0) || ($available + $overbook > 0)) {
                        $request->reservation = $reservation->id;
                        $request->timecreated = time();
                        if (isset($request->userid) && !empty($request->userid)) {
                            if ($requestid = $DB->insert_record('reservation_request', $request)) {
                                $usernote = new stdClass();
                                if (($reservation->note == 1) && (!empty($note))) {
                                    $usernote->request = $requestid;
                                    $usernote->note = strip_tags($note);
                                    $DB->insert_record('reservation_note', $usernote);
                                }
                                $request = $DB->get_record('reservation_request', array('id' => $requestid));
                                \mod_reservation\event\request_added::create_from_request($reservation, $context,
                                        $request, $usernote)->trigger();

                                // Update completion state.
                                $completion = new completion_info($course);
                                if ($completion->is_enabled($cm) && $reservation->completionreserved) {
                                    $completion->update_state($cm, COMPLETION_COMPLETE);
                                }

                                redirect ('view.php?id='.$cm->id, get_string('reserved', 'reservation'), 2);
                            } else {
                                error('Database insertion error');
                            }
                        }
                    } else {
                        $notice = 'nomorerequest';
                    }
                } else {
                    $notice = 'nomorerequest';
                }
            }
        } else {
            $notice = 'reservationdenied';
        }
    } else if (isset($cancel)) {
        $queryparameters = array('userid' => $USER->id, 'reservation' => $reservation->id, 'timecancelled' => '0');
        if ($request = $DB->get_record('reservation_request', $queryparameters)) {
            $DB->set_field('reservation_request', 'timecancelled', time(), array('id' => $request->id));

            \mod_reservation\event\request_cancelled::create_from_request($reservation, $context, $request)->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $reservation->completionreserved) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE);
            }
            redirect ('view.php?id='.$cm->id, get_string('reservationcancelled', 'reservation'), 2);
        } else {
            error('Bad script calling');
        }
    }
} else {
    error('Bad script calling');
}

// Print the page header.
$strreservations = get_string('modulenameplural', 'reservation');
$strreservation  = get_string('modulename', 'reservation');

$pagetitle = strip_tags($course->shortname.': '.format_string($reservation->name));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!empty($notice)) {
    notice(get_string($notice, 'reservation'), 'view.php?id='.$cm->id);
}

// Finish the page.
echo $OUTPUT->footer($course);

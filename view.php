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
        error('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }
    if (! $reservation = $DB->get_record('reservation', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }
} else if (!empty($r)) {
    if (! $reservation = $DB->get_record('reservation', array('id' => $r))) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $reservation->course))) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
} else {
        error('Missing required parameter');
}

$status = new stdClass();
$status->view = optional_param('view', null, PARAM_ALPHA);              // Full or clean view for teacher.
$status->mode = optional_param('mode', 'overview', PARAM_ALPHA);        // Define the viewed tab.
$status->action = optional_param('action', null, PARAM_ALPHA);          // Delete or message selected requests.
if (empty($status->action) && !empty(optional_param('savegrades', null, PARAM_ALPHA))) {
    $status->action = 'savegrades';                                     // Save all modified grades.
}
$status->download = optional_param('download', null, PARAM_ALPHA);      // Null or one of available download formats.

// Check to see if groups are being used in this reservation.
$status->groupmode = groups_get_activity_groupmode($cm);
if ($status->groupmode != NOGROUPS) {
    $status->group = groups_get_activity_group($cm, true);
}

$queries = array('id' => $cm->id);
if ($status->groupmode != NOGROUPS) {
    if ($status->group !== false) {
        $queries['group'] = $status->group;
    }
}
if (!empty($status->mode)) {
    $queries['mode'] = $status->mode;
}
$url = new moodle_url('/mod/reservation/view.php', $queries);
$PAGE->set_url($url);

require_login($course->id, false, $cm);

$now = time();

$context = context_module::instance($cm->id);

$coursecontext = context_course::instance($reservation->course);

$params = array(
    'context' => $context,
    'objectid' => $reservation->id
);
$event = \mod_reservation\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('reservation', $reservation);
$event->trigger();

// Do an action.
if (isset($status->action) && confirm_sesskey()) {
    switch($status->action) {
        case 'savegrades': // Store grades if set.
            if (has_capability('mod/reservation:grade', $context)) {
                    $grades = optional_param_array('grades', array(), PARAM_INT);
                    reservation_set_grades($reservation, $USER->id, $grades);
            } else {
                $status->action = null;
            }
        break;
        case 'delete': // Some requests need to be deleted.
            if (has_capability('mod/reservation:manualdelete', $context)) {
                $requestids = optional_param_array('requestid', array(), PARAM_INT);
                if (is_array($requestids) && !empty($requestids)) {
                    require_once('lib.php');
                    foreach ($requestids as $num => $requestid) {
                        if (!empty($requestid)) {
                            unset($requestids[$num]);
                            $request = $DB->get_record('reservation_request', array('id' => $requestid));
                            $requestnote = $DB->get_record('reservation_note', array('request' => $requestid));
                            $requestnote = !empty($requestnote) ? $requestnote : new stdClass();

                            $DB->set_field('reservation_request', 'grade', -1, array('id' => $requestid));
                            $userid = $DB->get_field('reservation_request', 'userid', array('id' => $requestid));
                            reservation_update_grades($reservation, $userid);

                            reservation_remove_user_event($reservation, $request);

                            $DB->delete_records('reservation_request', array('id' => $requestid));
                            $DB->delete_records('reservation_note', array('request' => $requestid));

                            // Update completion state.
                            $completion = new completion_info($course);
                            if ($completion->is_enabled($cm)) {
                                $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
                            }

                            \mod_reservation\event\request_deleted::create_from_request($reservation,
                                                                                        $context,
                                                                                        $request,
                                                                                        $requestnote)->trigger();
                        }
                    }
                }
            }
        break;
        case 'mail':  // Send mail to selected users.
            if (has_capability('mod/reservation:viewrequest', $context)) {
                $requestids = optional_param_array('requestid', array(), PARAM_INT);
                if (!is_array($requestids) || empty($requestids)) {
                    break;
                }

                foreach ($requestids as $num => $requestid) {
                    if (empty($requestid)) {
                        unset($requestids[$num]);
                    }
                }

                if (empty($SESSION->reservation_messageto)) {
                    $SESSION->reservation_messageto = array();
                }
                if (!array_key_exists($cm->id, $SESSION->reservation_messageto)) {
                    $SESSION->reservation_messageto[$cm->id] = array();
                }

                foreach ($requestids as $requestid) {
                    $request = $DB->get_record('reservation_request', array('id' => $requestid));
                    $user = $DB->get_record('user', array('id' => $request->userid));
                    $user->teacher = $USER->id;
                    $SESSION->reservation_messageto[$cm->id][$request->userid] = $user;
                }
                $url = new moodle_url('tool/messageselect.php', array('id' => $cm->id, 'sesskey' => $USER->sesskey));
                redirect($url);
                exit();
            }
        break;
        case 'manualreserve':  // Add a reservation for selected user.
        break;
        case 'reserve':  // Add a reservation for current user.
        break;
        case 'cancel':  // Cancel the reservation for current user.
        break;
    }
}

$strreservations = get_string('modulenameplural', 'reservation');
$strreservation  = get_string('modulename', 'reservation');

$countrynames = get_string_manager()->get_list_of_countries();

$pagetitle = strip_tags($course->shortname.': '.format_string($reservation->name));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

if (empty($status->download)) {
    //
    // Print the page header.
    //
    echo $OUTPUT->header();

    // Display Intro.
    echo $OUTPUT->heading(format_string($reservation->name));

    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

    reservation_print_info($reservation, $cm->id);

    if (has_capability('mod/reservation:viewrequest', $context)) {
        reservation_print_connected($reservation);
    }

    echo $OUTPUT->box_end();
}

if ($status->mode == 'manage') {
    // Get list of users available for manual reserve.
    $addableusers = reservation_get_addableusers($reservation, $status);
}

// Get profile custom fields array.
$customfields = reservation_get_profilefields();

$fields = array();
if (has_capability('mod/reservation:viewrequest', $context)) {
    $fields = reservation_get_fields($customfields, $status);
}

// Set global and sublimit counters.
$counters = reservation_setup_counters($reservation, $customfields);

// Add sublimits fields to used fields.
$fields = reservation_setup_sublimit_fields($counters, $customfields, $fields);

$rows = array();

// Get all reservation requests.
$requests = reservation_get_requests($reservation, true, $fields, $status);
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
        $status->actions = array();
        if (has_capability('mod/reservation:viewrequest', $context)) {
            $status->actions['mail'] = get_string('sendmessage', 'message');
        }
        if (has_capability('mod/reservation:manualdelete', $context)) {
            $status->actions['delete'] = get_string('deleteselected');
        }
    }

    // Get user request information (if already reserved).
    if (isset($requests[0])) {
        $currentuser = new stdClass();
        $currentuser->number = $requests[0]->number;
        if (($reservation->maxgrade != 0 ) && ($now > $reservation->timestart) && ($requests[0]->grade >= 0)) {
            if ($reservation->maxgrade < 0) {
                if ($scale = $DB->get_record('scale', array('id' => -$reservation->maxgrade))) {
                    $values = explode(',', $scale->scale);
                    $currentuser->grade = get_string('yourscale', 'reservation', $values[$requests[0]->grade - 1]);
                }
            } else {
                $grade = new stdClass();
                $grade->grade = $requests[0]->grade;
                $grade->maxgrade = $reservation->maxgrade;
                $currentuser->grade = get_string('yourgrade', 'reservation', $grade);
            }
        }
        if (($reservation->note) && !empty($requests[0]->note)) {
            $currentuser->note = html_writer::tag('div',
                                                  get_string('note', 'reservation').': '.format_string($requests[0]->note),
                                                  array('class' => 'note'));
        } else {
            $currentuser->note = '';
        }
        unset($requests[0]);
    }

    // Create requests table.
    $table = new flexible_table('mod-reservation-requests');

    if ($status->mode == 'overview') {
        if (has_capability('mod/reservation:downloadrequests', $context)) {
            $table->is_downloadable(true);
            $table->show_download_buttons_at(array(TABLE_P_TOP, TABLE_P_BOTTOM));
        }

        $table->is_downloading($status->download,
                               clean_filename("$course->shortname ".format_string($reservation->name, true)),
                               $strreservations);
    }

    // Define Table headers.
    if (empty($status->download)) {
        $tableheaders = array('#', '', get_string('fullname'));
        $tablecolumns = array('number', 'picture', 'fullname');
    } else {
        $tableheaders = array('#', get_string('firstname'), get_string('lastname'));
        $tablecolumns = array('number', 'firstname', 'lastname');
    }
    if (has_capability('mod/reservation:viewrequest', $context)) {
        if (!empty($fields)) {
            foreach ($fields as $fieldid => $field) {
                if (isset($field->name)) {
                      $tableheaders[] = $field->name;
                      $tablecolumns[] = $fieldid;
                }
            }
        }
        $tableheaders[] = get_string('reservedon', 'reservation');
        $tablecolumns[] = 'timecreated';
        if ($status->view == 'full') {
            $tableheaders[] = get_string('cancelledon', 'reservation');
            $tablecolumns[] = 'timecancelled';
        }

        if (has_capability('mod/reservation:viewnote', $context) && ($reservation->note == 1)) {
            $tableheaders[] = get_string('note', 'reservation');
            $tablecolumns[] = 'note';
        }
        if (($reservation->maxgrade != 0) && ($now > $reservation->timestart)) {
            $tableheaders[] = get_string('grade');
            $tablecolumns[] = 'grade';
        }
        if (empty($status->download) && !empty($status->actions)) {
            $tableheaders[] = get_string('select');
            $tablecolumns[] = 'select';
        }
    }
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($url);

    if (has_capability('mod/reservation:viewrequest', $context)) {
        $table->sortable(true);
        $table->collapsible(true);
        if (($status->mode == 'manage') && !empty($status->actions)) {
            $table->no_sorting('select');
        }
    } else {
        $table->sortable(false);
        $table->collapsible(false);
    }

    foreach ($tablecolumns as $column) {
        $table->column_class($column, $column);
    }

    $table->set_attribute('id', 'requests');
    $table->set_attribute('class', 'requests');

    // Start working -- this is necessary as soon as the niceties are over.
    $table->setup();

    // Sort data as requested.
    if (has_capability('mod/reservation:viewrequest', $context)) {
        $sortby = $table->get_sort_columns();
        if (!empty($sortby)) {
            $requests = reservation_multisort($requests, $sortby);
        }
    }

    $rows = reservation_get_table_data($reservation, $requests, $addableusers, $counters, $fields, $status);
}

// Set available seats in global count.
$maxrequests = get_config('reservation', 'max_requests');
$available = max($maxrequests, ($counters[0]->count + 1));
$overbook = 0;
if ($reservation->maxrequest > 0) {
    $available = $reservation->maxrequest;
    $overbook = round($reservation->maxrequest * $reservation->overbook / 100);
}
$available = min($available, ($available - $counters[0]->count));

if (empty($status->download) && has_capability('mod/reservation:viewrequest', $context)) {
    reservation_print_counters($reservation, $counters);
    reservation_print_tabs($reservation, $status->mode);
}

if (empty($status->download)) {
    // Show reservation form.
    if (($status->mode == 'manage') && has_capability('mod/reservation:manualreserve', $context)) {
        if (($reservation->maxrequest == 0) || ($available > 0) || ($available + $overbook > 0)) {
            if (isset($addableusers) && !empty($addableusers)) {
                $html = '';
                $reserveurl = new moodle_url('/mod/reservation/reserve.php', array('id' => $cm->id));
                $html .= html_writer::start_tag('form', array('id' => 'manualreserve',
                                                              'enctype' => 'multipart/form-data',
                                                              'method' => 'post',
                                                              'action' => $reserveurl));
                $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                               'name' => 'reservation',
                                                               'value' => $reservation->id));
                $html .= html_writer::start_tag('div');
                $html .= html_writer::tag('label',
                                          get_string('addparticipant', 'reservation').'&nbsp;',
                                          array('for' => 'newparticipant', 'class' => 'addparticipant'));
                $html .= html_writer::select($addableusers, 'newparticipant');
                $html .= html_writer::end_tag('div');

                $html .= reservation_get_note_field($reservation);

                $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                               'name' => 'reserve',
                                                               'class' => 'btn btn-primary manualreservebtn',
                                                               'value' => get_string('reserve', 'reservation')));
                $html .= html_writer::end_tag('form');

                echo html_writer::tag('div', $html, array('class' => 'manualreserve'));
            }
        }
    } else if (has_capability('mod/reservation:reserve', $context)) {
        // Display general infos messages and student submission button.
        if ($result = reservation_get_availability($reservation, $counters, $available)) {
            $available = $result->available;
            $overbook = $result->overbook;
        }
        // Display reservation availability.
        echo html_writer::start_tag('div', array('class' => 'availability'));

        $cr = reservation_reserved_on_connected($reservation, $USER->id);

        if (($now >= $reservation->timeopen) && ($now <= $reservation->timeclose) && ($cr === false)) {
            if (!isset($currentuser)) {
                $html = '';
                if (($reservation->maxrequest == 0) && ($available > 0)) {
                    $html = html_writer::tag('span', get_string('availablerequests', 'reservation'), array('class' => 'available'));
                } else if (($available > 0) || ($overbook + $available > 0)) {
                    $overbookedstring = '';
                    if ($available > 0) {
                        $html = html_writer::tag('span',
                                                  get_string('availablerequests', 'reservation').': ',
                                                  array('class' => 'available'));
                        $html .= html_writer::tag('span', $available, array('class' => 'availablenumber'));
                    } else {
                        $html = html_writer::tag('span',
                                                  get_string('overbookonly', 'reservation'),
                                                  array('class' => 'overbooked'));
                    }
                } else {
                    $html = html_writer::tag('span',
                                             get_string('nomorerequest', 'reservation'),
                                             array('class' => 'nomoreavailable'));
                }
                echo html_writer::tag('div', $html);
            }

            $html = '';
            $reserveurl = new moodle_url('/mod/reservation/reserve.php', array('id' => $cm->id));
            $html .= html_writer::start_tag('form', array('id' => 'reserve',
                                                          'enctype' => 'multipart/form-data',
                                                          'method' => 'post',
                                                          'action' => $reserveurl,
                                                          'class' => 'reserve'));
            $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                           'name' => 'reservation',
                                                           'value' => $reservation->id));
            if (isset($currentuser) && ($currentuser->number > 0)) {
                $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                               'name' => 'cancel',
                                                               'class' => 'btn btn-primary',
                                                               'value' => get_string('reservecancel', 'reservation')));
            } else if ((($reservation->maxrequest == 0) && ($available > 0)) || ($available > 0) || ($available + $overbook > 0)) {
                $html .= reservation_get_note_field($reservation);

                $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                               'name' => 'reserve',
                                                               'class' => 'btn btn-primary reservebtn',
                                                               'value' => get_string('reserve', 'reservation')));
            }
            $html .= html_writer::end_tag('form');
            echo html_writer::tag('div', $html, array('class' => 'reserve'));
        } else {
            if ($cr !== false) {
                $linktext = $cr->coursename . ': ' . $cr->name;
                $connectto = get_config('reservation', 'connect_to');
                if ($connectto == 'site') {
                    $linktext = $displaylist[$cr->category] .'/'. $linktext;
                }

                $linkurl = new moodle_url('/mod/reservation/view.php', array('id' => $cr->id));
                $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));

                $html = get_string('reservedonconnected', 'reservation', $link);
                echo html_writer::tag('p', $html);
            }
        }

        if (isset($currentuser) && ($currentuser->number > 0)) {
            $canviewnumbernow = ($reservation->showrequest == 0) && ($now > $reservation->timeclose);
            $canviewnumberalways = ($reservation->showrequest == 3);
            if ($canviewnumbernow || $canviewnumberalways) {
                $numberspan = html_writer::tag('span', $currentuser->number, array('class' => 'justbookednumber'));
                if (($reservation->maxrequest > 0) && ($currentuser->number > $reservation->maxrequest)) {
                    $strjustbooked = get_string('justoverbooked', 'reservation', html_writer::tag('span', $numberspan));
                    echo $OUTPUT->box($strjustbooked.$currentuser->note, 'center justbooked overbooked');
                } else {
                    $strjustbooked = get_string('justbooked', 'reservation', html_writer::tag('span', $numberspan));
                    echo $OUTPUT->box($strjustbooked.$currentuser->note, 'center justbooked');
                }
            } else {
                $classes = 'center alreadybooked';
                if (($reservation->maxrequest > 0) && ($currentuser->number > $reservation->maxrequest)) {
                    $classes .= ' overbooked';
                    echo $OUTPUT->box(get_string('alreadyoverbooked', 'reservation').$currentuser->note, $classes);
                } else {
                    echo $OUTPUT->box(get_string('alreadybooked', 'reservation').$currentuser->note, $classes);
                }
            }
            if (!empty($currentuser->grade)) {
                echo $OUTPUT->box($currentuser->grade, 'center graded');
            }
        }
        echo html_writer::end_tag('div');
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

            // Display teacher view mode button.
            if (!empty($requests) && ($counters[0]->deletedrequests > 0)) {
                $html = html_writer::start_tag('form', array('enctype' => 'multipart/form-data',
                                                             'method' => 'post',
                                                             'action' => $url,
                                                             'id' => 'viewtype'));
                $html .= html_writer::start_tag('fieldset');
                if ($status->view == 'full') {
                    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'clean'));
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'save',
                                                                   'class' => 'btn btn-secondary',
                                                                   'value' => get_string('cleanview', 'reservation')));
                } else if ($counters[0]->deletedrequests > 0) {
                    $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'full'));
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'save',
                                                                   'class' => 'btn btn-secondary',
                                                                   'value' => get_string('fullview', 'reservation')));
                }
                $html .= html_writer::end_tag('fieldset');
                $html .= html_writer::end_tag('form');
                echo html_writer::tag('div', $html, array('class' => 'viewtype'));
            }
            echo $OUTPUT->heading(get_string('reservations', 'reservation'));
        }

        if (empty($rows)) {
            if (has_capability('mod/reservation:viewrequest', $context)) {
                echo $OUTPUT->heading(get_string('noreservations', 'reservation'));
            }
        } else {
            echo html_writer::start_tag('div', array('id' => 'tablecontainer'));
            if (($status->mode == 'manage') && has_capability('mod/reservation:viewrequest', $context)) {
                $confirm = format_string(get_string('confirmdelete', 'reservation'));
                $onsubmit = 'return ((this.action[this.action.selectedIndex].value != "delete") || confirm("'.$confirm.'"));';
                echo html_writer::start_tag('form', array('id' => 'requestactions',
                                                          'enctype' => 'multipart/form-data',
                                                          'method' => 'post',
                                                          'action' => $url,
                                                          'onsubmit' => $onsubmit));
                echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                           'name' => 'sesskey',
                                                           'value' => $USER->sesskey));
                if (isset($status->view) && !empty($status->view)) {
                    echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                               'name' => 'view',
                                                               'value' => $status->view));
                }
            }

            $table->start_output();

            foreach ($rows as $row) {
                $table->add_data($row);
            }

            $table->finish_output();

            if (($status->mode == 'manage') && has_capability('mod/reservation:viewrequest', $context) &&
               ((($reservation->maxgrade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0))
                || ($counters[0]->count > 0) || ($counters[0]->deletedrequests > 0))) {
                if (($reservation->maxgrade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0)) {
                    $html = html_writer::empty_tag('input', array('type' => 'submit',
                                                                  'name' => 'savegrades',
                                                                  'class' => 'btn btn-primary',
                                                                  'value' => get_string('save', 'reservation')));
                    echo html_writer::tag('div', $html, array('class' => 'savegrades'));
                }
                // Print "Select all" etc.
                if (!empty($status->actions) && (($counters[0]->count > 0) ||
                                         (($status->view == 'full') && ($counters[0]->deletedrequests > 0)))) {
                    $html = '';
                    $html .= html_writer::start_tag('div', array('class' => 'btn-group'));
                    $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                    'id' => 'checkall',
                                                    'class' => 'btn btn-secondary',
                                                    'value' => get_string('selectall')));
                    $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                                   'id' => 'checknone',
                                                                   'class' => 'btn btn-secondary',
                                                                   'value' => get_string('deselectall')));
                    $html .= html_writer::end_tag('div');
                    $html .= html_writer::select($status->actions, 'action', '0',
                                                 array('0' => get_string('withselected', 'reservation')));
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'selectedaction',
                                                                   'class' => 'btn btn-secondary m-r-1',
                                                                   'value' => get_string('ok')));
                    echo html_writer::tag('div', $html, array('class' => 'form-buttons'));

                    $module = array('name' => 'modReservation', 'fullpath' => '/mod/reservation/module.js');
                    $PAGE->requires->js_init_call('M.modReservation.initView', null, false, $module);
                }

                echo html_writer::end_tag('form');
            }
            echo html_writer::end_tag('div');
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

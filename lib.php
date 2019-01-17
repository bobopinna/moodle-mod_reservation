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
 * This file contains the moodle hooks for the reservation plugin
 *
 * @package mod_reservation
 * @copyright 2006 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function reservation_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * Adds reservation instance.
 *
 * @param stdClass $reservation
 * @return int The instance id of the new reservation
 */
function reservation_add_instance($reservation) {
    global $DB;

    $reservation = reservation_postprocess($reservation);

    if ($returnid = $DB->insert_record('reservation', $reservation)) {
        $reservation->id = $returnid;

        reservation_set_sublimits($reservation);

        reservation_set_events($reservation);

        reservation_grade_item_update($reservation);
    } else {
        error('Could not insert record');
    }

    return $returnid;
}

/**
 * Updates reservation instance.
 *
 * @param stdClass $reservation
 * @return int The instance id of the updated reservation
 */
function reservation_update_instance($reservation) {
    global $DB;

    $reservation->id = $reservation->instance;

    $reservation = reservation_postprocess($reservation);

    if ($returnid = $DB->update_record('reservation', $reservation)) {

        reservation_set_sublimits($reservation);

        $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id));
        reservation_set_events($reservation);

        if (!empty($reservation->maxgrade)) {
            reservation_grade_item_update($reservation);
        } else {
            reservation_grade_item_delete($reservation);
        }
    } else {
        error('Could not update record');
    }

    return $returnid;
}

/**
 * Delete reservartion instance by activity id
 *
 * @param int $id
 * @return bool success
 */
function reservation_delete_instance($id) {
    global $DB;

    if (! $reservation = $DB->get_record('reservation', array('id' => $id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records here.

    if (! $DB->delete_records('reservation', array('id' => $reservation->id))) {
        $result = false;
    }

    $allrequestsql = 'SELECT rq.id
                      FROM {reservation_request} rq
                      WHERE reservation = ?';
    if (! $DB->delete_records_select('reservation_note', 'request IN ('.$allrequestsql.')', array($reservation->id))) {
        $result = false;
    }

    if ($requests = $DB->get_records('reservation_request', array('reservation' => $reservation->id))) {
        foreach ($requests as $request) {
            if (isset($request->eventid) && !empty($request->eventid)) {
                reservation_remove_user_event($reservation, $request);
            }
        }
        $DB->delete_records('reservation_request', array('reservation' => $reservation->id));
    }

    if (! $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id))) {
        $result = false;
    }

    reservation_grade_item_delete($reservation);

    return $result;
}

/**
 * Obtains the automatic completion state for this reservation based on the condition
 * in reservation settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function reservation_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get reservation details.
    $reservation = $DB->get_record('reservation', array('id' => $cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false.
    if ($reservation->completionreserved) {
        $params = array('userid' => $userid, 'reservation' => $reservation->id, 'timecancelled' => 0);
        return $DB->record_exists('reservation_request', $params);
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * Used for user activity reports.
 *
 * @param object $course Course
 * @param object $user User
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $reservation
 * @return object A standard object with 2 variables: info (reserved or not or grade) and time (last graded or time created)
 */
function reservation_user_outline($course, $user, $mod, $reservation) {
    global $DB;

    $return = null;
    $queryparameters = array('reservation' => $reservation->id, 'userid' => $user->id, 'timecancelled' => '0');
    if ($userrequest = $DB->get_record('reservation_request', $queryparameters)) {
        if ($userrequest->timegraded != 0) {
            $return->info = get_string('grade') . ': ' . $userrequest->grade;
            $return->time = $userrequest->timegraded;
        } else {
            $return->info = get_string('reserved', 'reservation');
            $return->time = $userrequest->timecreated;
        }
    } else {
        $return->info = get_string('noreservations', 'reservation');
    }
    return $return;
}

/**
 * Print a detailed representation of what a user has done with a reservation.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $reservation
 */
function reservation_user_complete($course, $user, $mod, $reservation) {
    global $DB;

    $queryparameters = array('reservation' => $reservation->id, 'userid' => $user->id, 'timecancelled' => '0');
    if ($userrequest = $DB->get_record('reservation_request', $queryparameters)) {
        echo get_string('reservedon', 'reservation') . ' ' .
                userdate($userrequest->timecreated, get_string('strftimedatetime')) . '<br />';
        if ($userrequest->timegraded != 0) {
            if (! $teacher = $DB->get_record('user', array('id' => $userrequest->teacher))) {
                echo 'Could not find teacher '.$userrequest->teacher."\n";
            } else {
                echo get_string('grade').': '.$userrequest->grade . ' (' .
                        userdate($userrequest->timegraded, get_string('strftimedatetime')) . ' ' .
                        get_string('by', 'reservation') . ' ' . fullname($teacher) . ')<br />';
            }
        }
    } else {
        print_string('noreservations', 'reservation');
    }

    return true;
}

/**
 * Given a course and a date, prints a summary of activity with
 * reservations activities in the course since that date
 *
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function reservation_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all notifications that have yet to be mailed out, and mails them
 * out to all teachers or students based on requirements.
 */
function reservation_cron () {
    global $CFG, $USER, $DB;

    // Notices older than 1 day will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months.
    $timenow   = time();
    $endtime   = $timenow;
    $starttime = $endtime - 24 * 3600;   // One day earlier.

    $notifieslist = get_config('reservation', 'notifies');
    if ($notifieslist === false) {
        $notifieslist = 'teachers,students,grades';
    }
    $notifies = explode(',', $notifieslist);

    // Notify request grading to students.
    if ($requests = reservation_get_unmailed_requests(0, $starttime)) {
        foreach ($requests as $key => $request) {
            if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
            }
        }
    }

    if ($requests = reservation_get_unmailed_requests($starttime, $endtime)) {
        foreach ($requests as $request) {

            mtrace('Processing reservation request '.$request->id);

            if (in_array('grades', $notifies)) {

                if (! $user = $DB->get_record('user', array('id' => $request->userid))) {
                    mtrace('Could not find user '.$request->userid);
                    continue;
                }

                $USER->lang = $user->lang;

                if (! $course = $DB->get_record('course', array('id' => $request->course))) {
                    mtrace('Could not find course '.$request->course);
                    continue;
                }

                if (! $teacher = $DB->get_record('user', array('id' => $request->teacher))) {
                    mtrace('Could not find teacher '.$request->teacher);
                    continue;
                }

                if (! $mod = get_coursemodule_from_instance('reservation', $request->reservation, $course->id)) {
                    mtrace('Could not find course module for reservation id '.$request->reservation);
                    continue;
                }

                if (! $mod->visible) {
                    // Hold mail notification for hidden reservations until later.
                    continue;
                }

                $strreservations = get_string('modulenameplural', 'reservation');
                $strreservation  = get_string('modulename', 'reservation');

                $reservationinfo = new stdClass();
                $reservationinfo->teacher = fullname($teacher);
                $reservationinfo->reservation = format_string($request->name, true);
                $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;

                $postsubject = $course->shortname.': '.$strreservations.': '.format_string($request->name, true);
                $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($request->name, true)."\n";
                $posttext .= '---------------------------------------------------------------------'."\n";
                $posttext .= get_string('gradedmail', 'reservation', $reservationinfo);
                $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                $posthtml = '';

                if ($user->mailformat == 1) {  // HTML.
                    $posthtml = '<p>';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> ->';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                            $strreservations.'</a> ->';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                            format_string($request->name, true).'</a>';
                    $posthtml .= '</p>';
                    $posthtml .= '<hr />';
                    $posthtml .= '<p>'.get_string('gradedmailhtml', 'reservation', $reservationinfo).'</p>';
                    $posthtml .= '<hr />';
                }

                if (! email_to_user($user, $teacher, $postsubject, $posttext, $posthtml)) {
                    mtrace('Error: reservation cron: Could not send out mail for request id '.$request->id.' to user '.
                           $user->id.' ('.$user->email.')');
                } else {
                    if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                        mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
                    }
                }
            } else {
                // Grades notify disabled. Mark as mailed.
                if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                    mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
                }
            }
        }
    }

    // Notify the end of reservation time with link for info to teachers and students.
    if ($reservations = reservation_get_unmailed_reservations(0, $starttime)) {
        foreach ($reservations as $key => $reservation) {
            mtrace('Set unmailed reservation id '.$reservation->id.' as mailed.');
            if (! $DB->set_field('reservation', 'mailed', '1', array('id' => $reservation->id))) {
                mtrace('Could not update the mailed field for reservation id '.$reservation->id.'.  Not mailed.');
            }
        }
    }

    if ($reservations = reservation_get_unmailed_reservations($starttime, $endtime)) {
        foreach ($reservations as $reservation) {
            mtrace('Process reservation id '.$reservation->id.'.');
            // Mark as mailed just to prevent double mail sending.
            if (! $DB->set_field('reservation', 'mailed', '1', array('id' => $reservation->id))) {
                mtrace('Could not update the mailed field for reservation id '.$reservation->id.'.');
            }

            if (! $course = $DB->get_record('course', array('id' => $reservation->course))) {
                mtrace('Could not find course '.$reservation->course);
                continue;
            }

            if (! $mod = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
                mtrace('Could not find course module for reservation id '.$reservation->id);
                continue;
            }

            if (! $mod->visible) {
                // Hold mail notification for hidden reservations until later.
                continue;
            }

            $reservationinfo = new stdClass();
            $reservationinfo->reservation = format_string($reservation->name, true);

            $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;

            if (in_array('teachers', $notifies)) {
                // Notify to teachers.
                if (!empty($reservation->teachers)) {
                    $teachers = explode(',', $reservation->teachers);
                } else {
                    // If no teachers are defined in reservation notify to all editing teachers and managers.
                    $context = context_module::instance($mod->id);

                    $teachers = array_keys(get_users_by_capability($context, 'mod/reservation:addinstance', 'u.id'));
                }
                if (!empty($teachers)) {
                    foreach ($teachers as $teacherid) {
                        mtrace('Processing reservation teacher '.$teacherid);
                        if (! $teacher = $DB->get_record('user', array('id' => $teacherid))) {
                            mtrace('Could not find user '.$teacherid);
                            continue;
                        }

                        $USER->lang = $teacher->lang;

                        $strreservations = get_string('modulenameplural', 'reservation');
                        $strreservation  = get_string('modulename', 'reservation');

                        $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name, true);
                        $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name, true)."\n";
                        $posttext .= '---------------------------------------------------------------------'."\n";
                        $posttext .= get_string('mail', 'reservation', $reservationinfo);
                        $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                        $posthtml = '';

                        if ($teacher->mailformat == 1) {  // HTML.
                            $posthtml = '<p>';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.
                                     $course->shortname.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                                     $strreservations.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                                     format_string($reservation->name, true).'</a>';
                            $posthtml .= '</p>';
                            $posthtml .= '<hr />';
                            $posthtml .= '<p>'.get_string('mailhtml', 'reservation', $reservationinfo).'</p>';
                            $posthtml .= '<hr />';
                        }

                        if (! email_to_user($teacher, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                            mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.
                                    ' to user '.$teacher->id.' ('.$teacher->email.')');
                        }
                    }
                }
            }

            if (in_array('students', $notifies)) {
                // Notify to students.
                require_once($CFG->dirroot.'/mod/reservation/locallib.php');

                $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;
                if ($requests = reservation_get_requests($reservation)) {
                    foreach ($requests as $request) {
                        mtrace('Processing reservation user '.$request->userid);
                        if (! $user = $DB->get_record('user', array('id' => $request->userid))) {
                            mtrace('Could not find user '.$user->id);
                            continue;
                        }

                        $USER->lang = $user->lang;

                        $strreservations = get_string('modulenameplural', 'reservation');
                        $strreservation  = get_string('modulename', 'reservation');

                        $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name, true);
                        $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name, true)."\n";
                        $posttext .= '---------------------------------------------------------------------'."\n";
                        $posttext .= get_string('mailrequest', 'reservation', $reservationinfo);
                        $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                        $posthtml = '';

                        if ($user->mailformat == 1) {  // HTML.
                            $posthtml = '<p>';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.
                                    $course->shortname.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                                    $strreservations.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                                    format_string($reservation->name, true).'</a>';
                            $posthtml .= '</p>';
                            $posthtml .= '<hr />';
                            $posthtml .= '<p>'.get_string('mailrequesthtml', 'reservation', $reservationinfo).'</p>';
                            $posthtml .= '<hr />';
                        }

                        if (! email_to_user($user, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                            mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.
                                    ' to user '.$user->id.' ('.$user->email.')');
                        }
                    }
                }
            }
        }
    }

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param object $reservation
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function reservation_get_user_grades($reservation, $userid=0) {
    global $DB;

    $param = array();
    $user = '';
    if ($userid) {
        $user = ' AND u.id = :userid';
        $param['userid'] = $userid;
    }

    $sql = 'SELECT u.id as userid, r.grade AS rawgrade, r.teacher AS usermodified, r.timegraded AS dategraded, '.
           'r.timecreated AS datesubmitted
            FROM {user} u, {reservation_request} r
            WHERE u.id = r.userid AND r.timegraded >0 AND r.reservation = :reservation'.$user;
    $param['reservation'] = $reservation->id;

    return $DB->get_records_sql($sql, $param);

}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $reservation null means all reservations
 * @param int $userid specific user only, 0 mean all
 * @param bool $nullifnone
 */
function reservation_update_grades($reservation=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($reservation != null) {
        if ($grades = reservation_get_user_grades($reservation, $userid)) {

            foreach ($grades as $k => $v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
            reservation_grade_item_update($reservation, $grades);
        } else if ($userid and $nullifnone) {
            $grade = new stdClass();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            reservation_grade_item_update($reservation, $grade);
        } else {
            reservation_grade_item_update($reservation);
        }

    } else {
        $sql = "SELECT r.*, cm.idnumber as cmidnumber, r.course as courseid
                  FROM {reservation} r, {course_modules} cm, {modules} m
                 WHERE m.name='reservation' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $reservation) {
                if ($reservation->maxgrade != 0) {
                    reservation_update_grades($reservation);
                } else {
                    reservation_grade_item_update($reservation);
                }
            }
            $rs->close();
        }
    }
}

/**
 * Create grade item for given reservation
 *
 * @param stdClass $reservation object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function reservation_grade_item_update($reservation, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($reservation->courseid)) {
        $reservation->courseid = $reservation->course;
    }

    $params = array('itemname' => $reservation->name, 'idnumber' => $reservation->id);

    if ($reservation->maxgrade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $reservation->maxgrade;
        $params['grademin']  = 0;

    } else if ($reservation->maxgrade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$reservation->maxgrade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/reservation', $reservation->courseid, 'mod', 'reservation', $reservation->id, 0, $grades, $params);
}

/**
 * Delete grade item for given reservation
 *
 * @param object $reservation object
 * @return object reservation
 */
function reservation_grade_item_delete($reservation) {
    global $CFG;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($reservation->courseid)) {
        $reservation->courseid = $reservation->course;
    }

    return grade_update('mod/reservation', $reservation->courseid, 'mod', 'reservation',
            $reservation->id, 0, null, array('deleted' => 1));
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every assignment event in the site is checked, else
 * only assignment events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @return bool
 */
function reservation_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid) {
        if (! $reservations = $DB->get_records('reservation', array('course' => $courseid))) {
            return true;
        }
    } else {
        if (! $reservations = $DB->get_records('reservation')) {
            return true;
        }
    }

    $events = get_config('reservation', 'events');
    foreach ($reservations as $reservation) {
        $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id));

        $reservation->coursemodule = get_coursemodule_from_instance('reservation', $reservation->id)->id;
        reservation_set_events($reservation);

        if (! $requests = $DB->get_records('reservation_request', array('reservation' => $reservation->id))) {
            $usereventsenabled = false;
            if (!empty($events)) {
                $usereventsenabled = in_array('userevent', $events);
            }
            foreach ($requests as $request) {
                if (isset($request->eventid) && !empty($request->eventid)) {
                    reservation_remove_user_event($reservation, $request);
                    if ($usereventsenabled) {
                        reservation_set_user_event($reservation, $request);
                    }
                }
            }
        }
    }
    return true;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all requests from the specified reservation
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function reservation_reset_userdata($data) {
    global $DB;

    $status = array();

    $allreservationsql = 'SELECT r.id
                         FROM {reservation} r
                         WHERE r.course = :courseid';
    $allrequestsql = 'SELECT rq.id
                      FROM {reservation_request} rq
                      WHERE reservation IN ('.$allreservationsql.')';

    if (!empty($data->reset_reservation_request)) {
        $query = 'reservation IN ('.$allreservationsql.')';
        $queryparameters = array('courseid' => $data->courseid);
        if ($requests = $DB->get_records_select('reservation_request', $query, $queryparameters)) {
            $DB->delete_records_select('reservation_request', 'reservation IN ('.$allreservationsql.')', $queryparameters);
            $DB->delete_records_select('reservation_note', 'request IN ('.$allrequestsql.')', $queryparameters);

            $reservations[] = array();
            foreach ($requests as $request) {
                if (isset($request->eventid) && !empty($request->eventid)) {
                    if (!isset($reservations[$request->reservation])) {
                        $reservations[$request->reservation] = $DB->get_record('reservation', array('id' => $request->reservation));
                    }
                    if (isset($reservations[$request->reservation])) {
                        reservation_remove_user_event($reservations[$request->reservation], $request);
                    }
                }
            }
        }

        $status[] = array(
               'component' => get_string('modulenameplural', 'reservation'),
               'item' => get_string('requests', 'reservation'),
               'error' => false
        );
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param moodleform $mform form passed by reference
 */
function reservation_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'reservationheader', get_string('modulenameplural', 'reservation'));

    $mform->addElement('checkbox', 'reset_reservation_request', get_string('requests', 'reservation'));
}

/**
 * Course reset form defaults.
 *
 * @param  object $course
 * @return array
 */
function reservation_reset_course_form_defaults($course) {
    return array('reset_reservation_request' => 1);
}

/**
 * Return list of closed reservation that have not been mailed out to assigned teachers.
 * Used by reservation_cron function
 *
 * @param int $starttime The date and time to search from
 * @param int $endtime The date and time to search to
 * @return array list of closed reservation
 */
function reservation_get_unmailed_reservations($starttime, $endtime) {
    global $DB;

    return $DB->get_records_sql('SELECT res.*
                                   FROM {reservation} res
                                  WHERE res.mailed = 0
                                    AND res.timeclose <= :endtime
                                    AND res.timeclose >= :starttime',
                                array('endtime' => $endtime, 'starttime' => $starttime));
}

/**
 * Return list of graded requests that have not been mailed out.
 * Used by reservation_cron function
 *
 * @param int $starttime The date and time to search from
 * @param int $endtime The date and time to search to
 * @return array list of graded request
 */
function reservation_get_unmailed_requests($starttime, $endtime) {
    global $DB;

    return $DB->get_records_sql('SELECT req.*, res.course, res.name
                                   FROM {reservation_request} req,
                                        {reservation} res,
                                        {user} u
                                  WHERE req.mailed = 0
                                    AND req.timecancelled = 0
                                    AND req.timegraded <= :endtime
                                    AND req.timegraded >= :starttime
                                    AND req.reservation = res.id
                                    AND req.userid = u.id',
                                array('endtime' => $endtime, 'starttime' => $starttime));
}

/**
 * Make some postprocessing settings on reservation to set defaults.
 * Used by reservation_add_instance and reservation_update_instance functions
 *
 * @param stdClass $reservation
 * @return object modified reservartion
 */
function reservation_postprocess($reservation) {
    $reservation->timemodified = time();

    if (!isset($reservation->location)) {
        $reservation->location = '';
    }

    if (isset($reservation->locationtext) && empty($reservation->location) && (trim($reservation->locationtext) != '')) {
        $reservation->location = trim($reservation->locationtext);
    }

    if (!empty($reservation->teachers)) {
        $reservation->teachers = implode(',', $reservation->teachers);
    } else {
        $reservation->teachers = '';
    }

    $reservation->mailed = 0;
    if ($reservation->timeclose < $reservation->timemodified) {
        $reservation->mailed = 1;
    }

    return $reservation;
}

/**
 * Set or update reservation sublimits.
 * Used by reservation_add_instance and reservation_update_instance functions
 *
 * @param stdClass $reservation
 * @return void
 */
function reservation_set_sublimits($reservation) {
    global $DB;

    $sublimits = get_config('reservation', 'sublimits');
    if (!empty($sublimits)) {

        $DB->delete_records('reservation_limit', array('reservationid' => $reservation->id));

        for ($i = 1; $i <= $sublimits; $i++) {
            $field = 'field_'.$i;
            $operator = 'operator_'.$i;
            $matchvalue = 'matchvalue_'.$i;
            $requestlimit = 'requestlimit_'.$i;
            if ($reservation->$field != '-') {
                $reservationlimit = new stdClass();
                $reservationlimit->reservationid = $reservation->id;
                $reservationlimit->field = $reservation->$field;
                $reservationlimit->operator = $reservation->$operator;
                $reservationlimit->matchvalue = $reservation->$matchvalue;
                $reservationlimit->requestlimit = $reservation->$requestlimit;

                if (!$DB->insert_record('reservation_limit', $reservationlimit)) {
                    error('Could not insert sublimit rule '.$i);
                }
            }
        }
    }

    return $reservation;
}

/**
 * Set or update reservation events.
 * Used by reservation_add_instance, reservation_update_instance and reservation_refresh_events functions
 *
 * @param stdClass $reservation
 * @return void
 */
function reservation_set_events($reservation) {
    global $CFG;

    $events = get_config('reservation', 'events');
    if ($events === false) {
        $events = 'reservation,event';
    }

    if (!empty($events)) {
        require_once($CFG->dirroot.'/calendar/lib.php');

        $events = explode(',', $events);
        $event = new stdClass();
        $event->name        = $reservation->name;
        $event->description = format_module_intro('reservation', $reservation, $reservation->coursemodule);
        $event->courseid    = $reservation->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'reservation';
        $event->instance    = $reservation->id;
        $event->eventtype   = 'start';
        $event->timestart   = $reservation->timestart;
        $event->visible     = instance_is_visible('reservation', $reservation);
        $event->timeduration = max($reservation->timeend - $reservation->timestart, 0);

        $event2 = clone($event);

        if (in_array('event', $events)) {
            calendar_event::create($event);
        }

        $event2->name .= ' ('.get_string('reservations', 'reservation').')';
        $event2->description           = array();
        $event2->description['format'] = FORMAT_HTML;
        $event2->description['text']   = userdate($reservation->timestart,
                get_string('strftimedaydatetime')).'<br />'.$reservation->location.'<br />'.
                format_module_intro('reservation', $reservation, $reservation->coursemodule);
        $event2->eventtype    = 'reservation';
        $event2->timestart    = ($reservation->timeopen) != 0 ? $reservation->timeopen : $reservation->timemodified;
        $duration = $reservation->timeclose - $event2->timestart;
        $event2->timeduration = $duration > 0 ? $duration : 0;

        if (in_array('reservation', $events)) {
            calendar_event::create($event2);
        }

    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function reservation_get_view_actions() {
    return array('view', 'view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function reservation_get_post_actions() {
    return array('reserve', 'cancel'. 'grade');
}

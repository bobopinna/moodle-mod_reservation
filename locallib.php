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
 * Reservation plugin local lib functions
 *
 * @package mod_reservation
 * @copyright 2011 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Gets a full reservation record
 *
 * @param integer $reservationid reservation id
 * @return object|bool The reservation or false
 */
function reservation_get_reservation($reservationid) {
    global $DB;

    if ($reservation = $DB->get_record('reservation', array('id' => $reservationid))) {
        if ($sublimits = $DB->get_records('reservation_limit', array('reservationid' => $reservationid), 'id')) {
            $i = 1;
            foreach ($sublimits as $sublimit) {
                $reservation->sublimits[$i] = new stdClass();
                $reservation->sublimits[$i]->field = $sublimit->field;
                $reservation->sublimits[$i]->operator = $sublimit->operator;
                $reservation->sublimits[$i]->matchvalue = $sublimit->matchvalue;
                $reservation->sublimits[$i]->limit = $sublimit->requestlimit;
            }
            return $reservation;
        }
    }
    return false;
}

/**
 * Return a list of reservations in specified course or with specified location
 *
 * @param integer $courseid A course id
 * @param string  $location The location name
 * @return array|bool The reservations list or false
 */
function reservation_get_reservations_by_course($courseid, $location='') {
    global  $DB;

    $searchfields = array('courseid' => $courseid);

    $locationquery = ')';
    if (!empty($location)) {
        $locationquery = ' OR res.location = :location)';
        $searchfields['location'] = $location;
    }

    return $DB->get_records_sql('SELECT res.*, c.fullname as coursename
                                   FROM {reservation} res,
                                        {course} c
                                  WHERE res.course = c.id
                                        AND (res.course = :courseid'.$locationquery,
                                $searchfields);
}

/**
 * Return a menu list of parentable reservation
 *
 * @param integer $reservationid A reservation id
 * @return array  The reservations menu list or false
 */
function reservation_get_parentable($reservationid) {
    global $DB, $CFG, $COURSE;

    $searchfields = array();
    $additionalquery = '';

    if (!isset($CFG->reservation_connect_to) || ($CFG->reservation_connect_to == 'course')) {
        $searchfields['courseid'] = $COURSE->id;
        $additionalquery .= ' AND (res.course = :courseid)';
    }

    if (!empty($reservationid)) {
        $searchfields['reservationid'] = $reservationid;
        $additionalquery .= ' AND (res.id <> :reservationid)';
        if ($DB->get_records('reservation', array('parent' => $reservationid))) {
            return array();
        }
    }

    return $DB->get_records_sql('SELECT res.id, c.fullname as coursename, c.category as category, res.name as name
                FROM {reservation} res,
                     {course} c
               WHERE res.course = c.id
                     AND (res.parent = 0)' . $additionalquery . ' ORDER BY category, coursename, name', $searchfields);
}

/**
 * Returns a list of reservations connected to the passed one
 *
 * @param stdClass $reservation A reservation object
 * @return array the reservations list
 */
function reservation_get_connected($reservation) {
    global $DB;

    $searchfields = array('reservationid' => $reservation->id);
    $additionalquery = '';

    if (!empty($reservation->parent)) {
        $additionalquery .= ' AND ((res.parent = :parent) OR (res.id = :parent2))';
        $searchfields['parent'] = $reservation->parent;
        $searchfields['parent2'] = $reservation->parent;
    } else {
        $additionalquery .= 'AND (res.parent = :reservationid2)';
        $searchfields['reservationid2'] = $reservation->id;
    }

    return $DB->get_records_sql('SELECT res.id, c.fullname as coursename, c.category as category, res.name as name
                FROM {reservation} res,
                     {course} c
               WHERE res.course = c.id
                     AND (res.id <> :reservationid)'.$additionalquery.
          ' ORDER BY category, coursename, name', $searchfields);
}

/**
 * Return a connected reservation where the user is already reserved, if exists
 *
 * @param stdClass $reservation reservation object
 * @param integer $userid
 * @return object with course name, reservation name, reservation id
 */
function reservation_reserved_on_connected($reservation, $userid) {
    global $DB;

    $searchfields = array('reservationid' => $reservation->id, 'userid' => $userid);
    $additionalquery = '';

    if (!empty($reservation->parent)) {
        $additionalquery .= ' AND ((res.parent = :parent) OR (res.id = :parent2))';
        $searchfields['parent'] = $reservation->parent;
        $searchfields['parent2'] = $reservation->parent;
    } else {
        $additionalquery .= ' AND (res.parent = :reservationid2)';
        $searchfields['reservationid2'] = $reservation->id;
    }

    $res = $DB->get_record_sql('SELECT res.*
                FROM {reservation} res,
                     {reservation_request} req
               WHERE (res.id = req.reservation)
                     AND (req.userid = :userid)
                     AND (req.timecancelled = 0)
                     AND (res.id <> :reservationid)'.$additionalquery, $searchfields);

    if ($res) {
        if ($course = $DB->get_record('course', array('id' => $res->course))) {
            if ($cm = get_coursemodule_from_instance('reservation', $res->id, $res->course)) {
                $result = new stdClass();
                $result->id = $cm->id;
                $result->name = $res->name;
                $result->coursename = $course->fullname;
                $result->category = $course->category;

                return $result;
            }
        }
    } else {
        return false;
    }
}

/**
 * Return all requests user data
 *
 * @param stdClass $reservation reservation object
 * @param boolean  $full        define if return a full list or active only requests
 * @param array    $fields      which data field but returned
 * @param integer  $groupid     define if return all users or only members of specified group (NOT USED)
 * @param integer  $groupmode   how groups are showed
 * @return array|bool list of request for that reservation
 */
function reservation_get_requests($reservation, $full=false, $fields=null, $groupid=0, $groupmode=NOGROUPS) {

    global $CFG, $DB, $USER;

    $clear = '';
    if (!$full) {
        $clear = ' AND r.timecancelled=0';
    }

    $requests = $DB->get_records_sql('SELECT u.*, r.*'.
                                     ' FROM {reservation_request} r, {user} u'.
                                     ' WHERE u.deleted = 0 AND r.reservation = :reservationid'.$clear.
                                     ' AND r.userid = u.id ORDER BY r.id', array('reservationid' => $reservation->id));

    if (!empty($requests)) {
        if (!empty($fields)) {
            require_once($CFG->dirroot.'/user/profile/lib.php');
        }

        $number = 1;
        foreach ($requests as $requestid => $request) {
            // Add request order numbers.
            $requests[$requestid]->number = $number;
            if ($request->timecancelled == 0) {
                $number++;
            }

            // Set current user information.
            if (($request->userid == $USER->id) && ($request->timecancelled == '0')) {
                $requests[0] = $request;
            }

            // Fill extra fields.
            if (!empty($fields)) {
                $userdata = new stdClass();
                $userdata->id = $request->userid;
                $userdata = profile_user_record($request->userid);
                foreach ($fields as $fieldid => $field) {
                    if (($field->custom !== false) && ($field->custom !== 'groups')) {
                        $requests[$requestid]->$fieldid = format_string($userdata->$fieldid);
                    }
                }
            }

            // Add user note.
            if ($reservation->note == 1) {
                $requests[$requestid]->note = $DB->get_field('reservation_note', 'note', array('request' => $requestid));
            }

            // Set user groups.
            if ($groupmode != NOGROUPS) {
                if (($groupid == 0) || groups_is_member($groupid, $request->userid)) {
                    $groups = groups_get_user_groups($reservation->course, $request->userid);
                    if (!empty($groups['0'])) {
                        $usergroups = array();
                        foreach ($groups['0'] as $group) {
                            $usergroups[] = format_string(groups_get_group_name($group));
                        }
                        $requests[$requestid]->groups = implode(', ', $usergroups);
                    } else {
                        $requests[$requestid]->groups = '';
                    }
                } else {
                    unset($requests[$requestid]);
                    continue;
                }
            }
        }
    }
    return $requests;
}

/**
 * Sorts an array (you know the kind) by key
 * and by the comparison operator you prefer.
 * Note that instead of most important criteron first, it's
 * least important criterion first.
 * The default sort order is ascending, and the default sort
 * type is strnatcmp.
 *
 * @param array $array
 * @param array $sortorders Associative array with attribute names as keys and ASC or DESC as values
 * @return array sorted array
 */
function reservation_multisort($array, $sortorders) {
    if (!empty($sortorders)) {
        $orders = array_reverse($sortorders, true);
        foreach ($orders as $key => $order) {
            $callback = function($a, $b) use ($order, $key) {
                 $o = 1;
                 if ($order == SORT_DESC) {
                     $o = -1;
                 }
                 if (is_numeric($a->$key) && is_numeric($b->$key)) {
                     return ($a->$key - $b->$key) * $o; 
                 } else {
                     return strnatcasecmp($a->$key, $b->$key) * $o;
                 }
            };
            uasort($array, $callback);
        }
    }

    return $array;
}

/**
 * Set grades for a give reservation
 *
 * @param stdClass $reservation
 * @param integer $teacherid
 * @param array $grades Associative array with requestids as keys and grades as value
 */
function reservation_set_grades($reservation, $teacherid, $grades) {
    global $DB;

    if (!empty($grades)) {
        $now = time();
        $requests = $DB->get_records('reservation_request', array('reservation' => $reservation->id));
        foreach ($grades as $requestid => $grade) {
            $request = $requests[$requestid];
            if ($grade != $request->grade) {
                $request->teacher = $teacherid;
                $request->grade = $grade;
                $request->mailed = 0;
                $request->timegraded = $now;
                $DB->update_record('reservation_request', $request);
                require_once('lib.php');
                reservation_update_grades($reservation, $request->userid);
            }
        }
    }
}

/**
 * Get list of teachers for this reservation
 *
 * @param stdClass $reservation
 * @param integer $cmid
 * @return array list of fullnames of teachers
 */
function reservation_get_teacher_names($reservation, $cmid=null) {
    global $DB;

    $teachernames = array();
    if (strlen($reservation->teachers) > 0) {
        $context = context_course::instance($reservation->course);
        $capability = 'moodle/course:viewhiddenactivities';
        if (isset($reservation->coursemodule) && !empty($reservation->coursemodule)) {
            $cmid = $reservation->coursemodule;
        }
        if ($cmid != null) {
            $context = context_module::instance($cmid);
            $capability = 'mod/reservation:addinstance';
        }
        $teachers = explode(',', $reservation->teachers);
        foreach ($teachers as $teacherid) {
            if (!empty($teacherid)) {
                if ($teacher = $DB->get_record('user', array('id' => $teacherid))) {
                    if (has_capability($capability, $context, $teacherid)) {
                        $teachernames[] = fullname($teacher);
                    }
                }
            }
        }
    }
    return implode(', ', $teachernames);
}

/**
 * Get list of users custom profile fields
 *
 * @return array list of custom profile fields
 */
function reservation_get_profilefields() {
    global $DB;

    $infofields = $DB->get_records('user_info_field');
    $customfields = array();
    foreach ($infofields as $infofield) {
        $customfields[$infofield->shortname] = new stdClass();
        $customfields[$infofield->shortname]->name = $infofield->name;
        $customfields[$infofield->shortname]->id = $infofield->id;
    }
    return $customfields;
}

/**
 * Get complete user profile fields (standard + custom fields)
 *
 * @param integer $userid
 * @return stdClass User data
 */
function reservation_get_userdata($userid) {
    global $DB, $CFG;

    if ($userdata = $DB->get_record('user', array('id' => $userid))) {
        require_once($CFG->dirroot.'/user/profile/lib.php');
        $profiledata = profile_user_record($userid);
        if (!empty($profiledata)) {
            foreach ($profiledata as $fieldname => $value) {
                $userdata->{$fieldname} = $value;
            }
        }
    }
    return $userdata;
}

/**
 * Setup array of required counters for sublimit check
 *
 * @param stdClass $reservation
 * @param array $customfields
 * @return array Counters
 */
function reservation_setup_counters($reservation, $customfields) {
    global $DB;

    $counters = array();
    $counters[0] = new stdClass();
    $counters[0]->count = 0;
    $counters[0]->deletedrequests = 0;
    if ($reservationlimits = $DB->get_records('reservation_limit', array('reservationid' => $reservation->id))) {
        $i = 1;
        foreach ($reservationlimits as $reservationlimit) {
            $counters[$i] = $reservationlimit;
            $counters[$i]->count = 0;
            if (isset($customfields[$reservationlimit->field])) {
                $counters[$i]->field = $reservationlimit->field;
                $counters[$i]->fieldname = format_string($customfields[$reservationlimit->field]->name);
            } else {
                $counters[$i]->field = $reservationlimit->field;
                $counters[$i]->fieldname = get_string($reservationlimit->field);
            }
            $i++;
        }
    }
    return $counters;
}

/**
 * Update counters for the given reservation request
 *
 * @param array $counters
 * @param stdClass $request
 * @return array Updated counters
 */
function reservation_update_counters($counters, $request) {

    $counters[0]->overbooked = 0;
    $counters[0]->matchlimit = 0;
    if ($request->timecancelled != '0') {
        $counters[0]->deletedrequests++;
    } else {
        $counters[0]->count++;
        for ($i = 1; $i < count($counters); $i++) {
            $fieldname = $counters[$i]->field;
            if (($request->$fieldname == $counters[$i]->matchvalue) && !$counters[$i]->operator) {
                $counters[$i]->count++;
                $counters[0]->matchlimit++;
                if ($counters[$i]->count > $counters[$i]->requestlimit) {
                    $counters[0]->overbooked++;
                }
            } else if (($request->$fieldname != $counters[$i]->matchvalue) && $counters[$i]->operator) {
                $counters[$i]->count++;
                $counters[0]->matchlimit++;
                if ($counters[$i]->count > $counters[$i]->requestlimit) {
                    $counters[0]->overbooked++;
                }
            }
        }
    }
    return $counters;
}

/**
 * Get list of sublimit fields
 *
 * @param array $counters
 * @param array $customfields
 * @param array $fields
 * @return array Sublimits fields
 */
function reservation_setup_sublimit_fields($counters, $customfields, $fields = array()) {
    foreach ($counters as $counter) {
        if (isset($counter->field)) {
            if (isset($customfields[$counter->field])) {
                if (!isset($fields[$counter->field])) {
                    $fields[$counter->field] = new stdClass();
                    $fields[$counter->field]->custom = $customfields[$counter->field]->id;
                }
            } else if ($counter->field != 'group') {
                if (!isset($fields[$counter->field])) {
                    $fields[$counter->field] = new stdClass();
                    $fields[$counter->field]->custom = false;
                }
            }
        }
    }
    return $fields;
}

/**
 * Calculate available seat for USER.
 *
 * @param stdClass $reservation
 * @param array $counters
 * @param integer $available
 * @return bool seat availability
 */
function reservation_get_availability($reservation, $counters, $available) {
    global $USER;

    $availablesublimit = 0;
    $limitoverbook = 0;
    $nolimit = true;
    $totalrequestlimit = 0;
    $totalrequestcount = 0;

    $userdata = reservation_get_userdata($USER->id);

    if (count($counters) - 1 > 0) {
        for ($i = 1; $i < count($counters); $i++) {
            if ((($userdata->{$counters[$i]->field} == $counters[$i]->matchvalue) && !$counters[$i]->operator) ||
               (($userdata->{$counters[$i]->field} != $counters[$i]->matchvalue) && $counters[$i]->operator)) {
                if ($availablesublimit <= ($counters[$i]->requestlimit - $counters[$i]->count)) {
                    $availablesublimit = $counters[$i]->requestlimit - $counters[$i]->count;
                    $limitoverbook = round($counters[$i]->requestlimit * $reservation->overbook / 100);
                }
                $nolimit = false;
            }
            $totalrequestlimit += $counters[$i]->requestlimit;
            $totalrequestcount += $counters[$i]->count;
        }

        if ($nolimit) {
            $availablesublimit = $available - $totalrequestlimit + $totalrequestcount;
        }

        if ($available > $availablesublimit) {
            $result = new stdClass();
            $result->available = $availablesublimit;
            $result->overbook = $limitoverbook;
            return $result;
        }
    }
    return false;
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 *
 * @param csv_import_reader $cir
 * @param array $fields standard user fields
 * @param array $requiredfields mandatory user fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function reservation_validate_upload_columns(csv_import_reader $cir, $fields, $requiredfields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < count($requiredfields)) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // Test columns.
    $processed = array();
    $required = 0;
    foreach ($columns as $key => $field) {
        $lcfield = core_text::strtolower($field);
        if (in_array($field, $fields) or in_array($lcfield, $fields)) {
            // Standard fields are only lowercase.
            $newfield = $lcfield;
        } else if (preg_match('/^(field|operator|matchvalue|sublimit)\d+$/', $lcfield)) {
            // Sublimit fields - not used.
            $newfield = $lcfield;

        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        if (in_array($newfield, $requiredfields)) {
            $required++;
        }
        $processed[$key] = $newfield;
    }

    if ($required < count($requiredfields)) {
        $cir->close();
        $cir->cleanup();
        print_error('missingrequiredfield', 'error', $returnurl);
    }

    return $processed;
}

/**
 * Print view page tabs
 *
 * @param stdClass $reservation
 * @param string $mode the current selected tab (overview or manage)
 */
function reservation_print_tabs($reservation, $mode) {
    $tabs = array();
    $row = array();

    $baseurl = 'view.php';
    $queries = array('r' => $reservation->id);

    $queries['mode'] = 'overview';
    $url = new moodle_url($baseurl, $queries);
    $row[] = new tabobject('overview', $url, get_string('overview', 'reservation'));

    $queries['mode'] = 'manage';
    $url = new moodle_url($baseurl, $queries);
    $row[] = new tabobject('manage', $url, get_string('manage', 'reservation'));

    $tabs[] = $row;

    // Print out the tabs and continue!
    print_tabs($tabs, $mode, null, null);
}

/**
 * Creates an user event for given reservation
 *
 * @param stdClass $reservation
 * @param stdClass $request
 */
function reservation_set_user_event($reservation, $request) {
    global $CFG, $DB;

    if (!isset($CFG->reservation_events)) {
        $CFG->reservation_events = 'reservation,event';
    }

    if ($DB->get_record('user', array('id' => $request->userid)) && !empty($CFG->reservation_events)) {
        $enabledevents = explode(',', $CFG->reservation_events);
        if (in_array('userevent', $enabledevents)) {
            require_once($CFG->dirroot.'/calendar/lib.php');

            $event = new stdClass();
            $event->name        = get_string('eventreminder', 'reservation', $reservation->name);
            $coursemodule = get_coursemodule_from_instance('reservation', $reservation->id)->id;
            $event->description = format_module_intro('reservation', $reservation, $coursemodule);
            $event->userid      = $request->userid;
            $event->modulename  = '';
            $event->instance    = 0;
            $event->eventtype   = 'user';
            $event->timestart   = $reservation->timestart;
            $event->visible     = instance_is_visible('reservation', $reservation);
            $event->timeduration = max($reservation->timeend - $reservation->timestart, 0);

            $newevent = calendar_event::create($event);

            $DB->set_field('reservation_request', 'eventid', $newevent->id, array('id' => $request->id));
        }
    }
}

/**
 * Remove the user event for given reservation
 *
 * @param stdClass $reservation
 * @param stdClass $request
 */
function reservation_remove_user_event($reservation, $request) {
    global $CFG, $DB;

    if (!isset($CFG->reservation_events)) {
        $CFG->reservation_events = 'reservation,event';
    }

    if ($DB->get_record('user', array('id' => $request->userid)) && !empty($CFG->reservation_events)) {
        $enabledevents = explode(',', $CFG->reservation_events);
        if (in_array('userevent', $enabledevents)) {
            require_once($CFG->dirroot.'/calendar/lib.php');

            $events = calendar_get_events_by_id(array($request->eventid));
            if (!empty($events)) {
                $deleted = false;
                foreach ($events as $event) {
                    if (!$deleted) {
                        calendar_event::load($event)->delete();
                        $deleted = true;
                    } else {
                        print_error('Found more than one user event for reservation '. $reservation->id);
                    }
                }
            }
        }
    }
}

/**
 * Print html formatted reservation info
 *
 * @param stdClass $reservation
 * @param stdClass $cmid
 */
function reservation_print_info($reservation, $cmid) {
    $now = time();
 
    $coursecontext = context_course::instance($reservation->course);

    echo html_writer::tag('div', format_module_intro('reservation', $reservation, $cmid), array('class' => 'intro'));
    // Retrive teachers list.
    $teachername = reservation_get_teacher_names($reservation, $cmid);
    if (!empty($teachername)) {
        $teacherroles = get_archetype_roles('editingteacher');
        $teacherrole = array_shift($teacherroles);
        $teacherstr = role_get_name($teacherrole, $coursecontext);
        echo html_writer::start_tag('div');
        echo html_writer::tag('label', $teacherstr.': ', array('class' => 'bold'));
        echo html_writer::tag('span', $teachername);
        echo html_writer::end_tag('div');
    }
    if (!empty($reservation->location)) {
        echo html_writer::start_tag('div');
        echo html_writer::tag('label', get_string('location', 'reservation').': ', array('class' => 'bold'));
        echo html_writer::tag('span', $reservation->location);
        echo html_writer::end_tag('div');
    }
    $strftimedaydatetime = get_string('strftimedaydatetime');
    echo html_writer::start_tag('div');
    if (!empty($reservation->timeend)) {
        echo html_writer::tag('label', get_string('timestart', 'reservation').': ', array('class' => 'bold'));
    } else {
        echo html_writer::tag('label', get_string('date').': ', array('class' => 'bold'));
    }
    echo html_writer::tag('span', userdate($reservation->timestart, $strftimedaydatetime));
    echo html_writer::end_tag('div');
    if (!empty($reservation->timeend)) {
        echo html_writer::start_tag('div');
        echo html_writer::tag('label', get_string('timeend', 'reservation').': ', array('class' => 'bold'));
        echo html_writer::tag('span', userdate($reservation->timeend, $strftimedaydatetime));
        echo html_writer::end_tag('div');
    }

    echo html_writer::empty_tag('hr', array('class' => 'clearfloat'));

    if (!empty($reservation->timeopen)) {
        echo html_writer::start_tag('div');
        echo html_writer::tag('label', get_string('timeopen', 'reservation').': ', array('class' => 'bold'));
        if ($now < $reservation->timeopen) {
            echo html_writer::tag('span',
                                  userdate($reservation->timeopen, $strftimedaydatetime),
                                  array('class' => 'notopened'));
            echo html_writer::tag('span', ' '.get_string('reservationnotopened', 'reservation'),
                                  array('class' => 'alert bg-warning'));
        } else {
            echo html_writer::tag('span', userdate($reservation->timeopen, $strftimedaydatetime));
        }
        echo html_writer::end_tag('div');
    }
    echo html_writer::start_tag('div');
    echo html_writer::tag('label', get_string('timeclose', 'reservation').': ', array('class' => 'bold'));
    if ($now > $reservation->timeclose) {
        echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime), array('class' => 'notopened'));
        echo html_writer::tag('span', ' '.get_string('reservationclosed', 'reservation'), array('class' => 'alert bg-warning'));
    } else {
        echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime));
    }
    echo html_writer::end_tag('div');
}

/**
 * Print connected reservations
 *
 * @param stdClass $reservation
 */
function reservation_print_connected($reservation) {
    global $CFG;

    if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
        require_once($CFG->libdir.'/coursecatlib.php');
        $displaylist = coursecat::make_categories_list();
    }
    // Show connected reservations.
    if ($connectedreservations = reservation_get_connected($reservation)) {
        $connectedlist = html_writer::tag('label',
                                           get_string('connectedto', 'reservation').': ',
                                           array('class' => 'bold'));
        $connectedlist .= html_writer::start_tag('ul', array('class' => 'connectedreservations'));
        foreach ($connectedreservations as $cr) {
            $linktext = $cr->coursename . ': ' . $cr->name;
            if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                $linktext = $displaylist[$cr->category] .'/'. $linktext;
            }
            $linkurl = new moodle_url('/mod/reservation/view.php', array('r' => $cr->id));
            $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));
            $connectedlist .= html_writer::tag('li', $link);
        }
        $connectedlist .= html_writer::end_tag('ul');

        echo html_writer::tag('div', $connectedlist, array('class' => 'connected'));
    }
}

/**
 * Get array of defined table fields
 *
 * @param int $group
 * @param int $groupmode
 * @return array
 */
function reservation_get_fields($customfields, $status) {
    global $CFG;

    $fields = array();

    // Get request table display fields.
    if (!isset($CFG->reservation_fields)) {
        $CFG->reservation_fields = '';
    }

    // Add fields to requests table.
    $field = strtok($CFG->reservation_fields, ',');
    while ($field !== false) {
        if (isset($customfields[$field])) {
            if (!isset($fields[$field])) {
                $fields[$field] = new stdClass();
                $fields[$field]->custom = $customfields[$field]->id;
            }
            $fields[$field]->name = format_string($customfields[$field]->name);
        } else {
            if (!isset($fields[$field])) {
                $fields[$field] = new stdClass();
                $fields[$field]->custom = false;
            }
            if ($field == 'phone1') {
                $fields[$field]->name = get_string('phone');
            } else {
                $fields[$field]->name = get_string($field);
            }
        }
        $field = strtok(',');
    }

    if (($status->groupmode == VISIBLEGROUPS) || (($status->groupmode == SEPARATEGROUPS) && ($status->group == 0))) {
        $fields['groups'] = new stdClass();
        $fields['groups']->name = get_string('group');
        $fields['groups']->custom = 'groups';
    }

    return $fields;
}

/**
 * Get list of addable users for manual reservation
 *
 *
 * @param context $context
 * @param context $coursecontext
 * @param int $group
 * @param int $groupmode
 * @return array
 */
function reservation_get_addableusers($reservation, $status) {
    global $CFG, $DB, $USER, $PAGE;

    $context = $PAGE->context;

    $coursecontext = context_course::instance($reservation->course);

    // Get list of users available for manual reserve.
    $addableusers = array();

    if (has_capability('mod/reservation:manualreserve', $context)) {
        $participants = array();
        if (!isset($CFG->reservation_manual_users)) {
            $CFG->reservation_manual_users = 'course';
        }

        if ($CFG->reservation_manual_users == 'site') {
            $participants = $DB->get_records('user', array('deleted' => 0, 'suspended' => 0), 'lastname ASC', '*');
        } else {
            $participants = get_enrolled_users($coursecontext, null, 0, 'u.*', 'u.lastname ASC');
        }
        if (!empty($participants)) {
            foreach ($participants as $participant) {
                if (!in_array($participant->username, array('guest', 'admin'))) {
                    if ($status->groupmode == SEPARATEGROUPS) {
                        if (($status->group != 0) && (has_capability('mod/reservation:viewrequest', $context))) {
                            $groups = groups_get_user_groups($reservation->course, $participant->id);
                            if (!empty($groups) && (array_search($status->group, $groups['0']) === false)) {
                                continue;
                            }
                        } else if (!has_capability('mod/reservation:viewrequest', $context)) {
                            $mygroups = groups_get_user_groups($reservation->course, $USER->id);
                            if (!empty($mygroups['0'])) {
                                $notmember = true;
                                $i = 0;
                                while (($i < count($mygroups['0'])) && (!groups_is_member($mygroups['0'][$i], $participant->id))) {
                                    $i++;
                                }
                                if ($i == count($mygroups['0'])) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                    $addableusers[$participant->id] = fullname($participant);
                }
            }
        }
    }
    return $addableusers;
}

function reservation_get_table_data($reservation, $requests, &$addableusers, &$counters, $fields, $status) {
    global $USER, $DB, $PAGE, $OUTPUT;

    $context = $PAGE->context;

    $now = time();

    $rows = array();
    if (!empty($requests)) {
        foreach ($requests as $request) {
            $row = array();
            $rowclasses = array();

            // Remove already reserved users from manual reservation list of users.
            if (($status->mode == 'manage') && isset($addableusers) &&
                    isset($addableusers[$request->userid]) && ($request->timecancelled == 0)) {
                unset($addableusers[$request->userid]);
            }

            // Highlight current user request.
            if (($USER->id == $request->userid) && ($request->timecancelled == '0')) {
                $rowclasses[] = 'yourreservation';
            }

            $counters = reservation_update_counters($counters, $request);
            // Set row data.
            if (($counters[0]->matchlimit > 0) && ($counters[0]->matchlimit == $counters[0]->overbooked)
                    && ($request->timecancelled == 0)) {
                $rowclasses[] = 'overbooked';
            }
            if (($reservation->maxrequest < $request->number) && ($reservation->maxrequest > 0) && ($request->timecancelled == 0)) {
                $rowclasses[] = 'overbooked';
            }
            if (has_capability('mod/reservation:viewrequest', $context)) {
                if ($request->timecancelled != '0') {
                    $rowclasses[] = 'cancelled';
                }
            }

            // Check for group (TODO: check if it can be moved in get_all_requests).
            if ($status->groupmode == SEPARATEGROUPS) {
                if (($status->group != 0) && (has_capability('mod/reservation:viewrequest', $context))) {
                    $groups = groups_get_user_groups($reservation->course, $request->userid);
                    if (!empty($groups) && (array_search($status->group, $groups['0']) === false)) {
                        continue;
                    }
                } else if (!has_capability('mod/reservation:viewrequest', $context)) {
                    $mygroups = groups_get_user_groups($reservation->course, $USER->id);
                    if (!empty($mygroups['0'])) {
                        $notmember = true;
                        $i = 0;
                        while (($i < count($mygroups['0'])) && (!groups_is_member($mygroups['0'][$i], $request->userid))) {
                            $i++;
                        }
                        if ($i == count($mygroups['0'])) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
            }

            $rowclass = implode(' ', $rowclasses);

            if (($request->timecancelled == '0') || (has_capability('mod/reservation:viewrequest', $context)
                    && ($status->view == 'full'))) {
                if ($request->timecancelled == '0') {
                    $row[] = $request->number;
                } else {
                    $row[] = '';
                }

                if (empty($status->download)) {
                    $userlink = new moodle_url('/user/view.php', array('id' => $request->userid, 'course' => $reservation->course));
                    $user = $DB->get_record('user', array('id' => $request->userid));
                    $row[] = $OUTPUT->user_picture($user, array('courseid' => $reservation->course));
                    $attributes = array('href' => $userlink, 'class' => 'fullname '.$rowclass);
                    $row[] = html_writer::tag('a', fullname($request), $attributes);
                } else {
                    $row[] = $request->firstname;
                    $row[] = $request->lastname;
                }

                if (has_capability('mod/reservation:viewrequest', $context)) {
                    if (!empty($fields)) {
                        foreach ($fields as $fieldid => $field) {
                            if (isset($field->name)) {
                                switch ($fieldid) {
                                    case 'email':
                                        if (empty($status->download)) {
                                            $fieldvalue = obfuscate_mailto($request->$fieldid);
                                        } else {
                                            $fieldvalue = $request->$fieldid;
                                        }
                                    break;
                                    case 'country':
                                        $fieldvalue = $countrynames[$request->$fieldid];
                                    break;
                                    default:
                                        $fieldvalue = format_string($request->$fieldid);
                                    break;
                                }

                                if (empty($status->download)) {
                                    $row[] = html_writer::tag('div', $fieldvalue, array('class' => $fieldid.' '.$rowclass));
                                } else {
                                    $row[] = $fieldvalue;
                                }
                            }
                        }
                    }
                    // Add reservation request time.
                    if (empty($status->download)) {
                        $row[] = html_writer::tag('div',
                                                  trim(userdate($request->timecreated, get_string('strftimedatetime'))),
                                                  array('class' => 'timecreated '.$rowclass));
                    } else {
                        $row[] = trim(userdate($request->timecreated, get_string('strftimedatetime')));
                    }

                    // If full view display also request revocation time.
                    if ($status->view == 'full') {
                        if ($request->timecancelled != '0') {
                            if (empty($status->download)) {
                                $row[] = html_writer::tag('div',
                                                          trim(userdate($request->timecancelled, get_string('strftimedatetime'))),
                                                          array('class' => 'timecancelled '.$rowclass));
                            } else {
                                $row[] = trim(userdate($request->timecancelled, get_string('strftimedatetime')));
                            }
                        } else {
                            $row[] = '';
                        }
                    }
                    // Add reservation request note.
                    if ($reservation->note) {
                        if (($status->view == 'full') || ($request->timecancelled == 0)) {
                            if (isset($request->note) && !empty($request->note)) {
                                if (empty($status->download)) {
                                    $row[] = html_writer::tag('div', $request->note, array('class' => 'note '.$rowclass));
                                } else {
                                    $row[] = $request->note;
                                }
                            } else {
                                if (empty($status->download)) {
                                    $row[] = html_writer::tag('div', '', array('class' => 'note'));
                                } else {
                                    $row[] = '';
                                }
                            }
                        }
                    }
                    // Display grade or grading dropdown menu.
                    if (($reservation->maxgrade != 0) && ($now > $reservation->timestart)) {
                        if (($status->mode == 'manage') && ($request->timecancelled == 0)) {
                            $row[] = html_writer::select(make_grades_menu($reservation->maxgrade),
                                                         'grades['.$request->id.']',
                                                         $request->grade,
                                                         array(-1 => get_string('nograde')));
                        } else {
                            if ($request->timegraded != 0) {
                                $usergrade = $request->grade;
                                if ($reservation->maxgrade < 0) {
                                    if ($scale = $DB->get_record('scale', array('id' => -$reservation->maxgrade))) {
                                        $values = explode(',', $scale->scale);
                                        $usergrade = $values[$request->grade - 1];
                                    }
                                }
                                if (empty($status->download)) {
                                    $row[] = html_writer::tag('div', $usergrade, array('class' => 'grade '.$rowclass));
                                } else {
                                    $row[] = $usergrade;
                                }
                            } else {
                                if (empty($status->download)) {
                                    $row[] = html_writer::tag('div', '', array('class' => 'grade '.$rowclass));
                                } else {
                                    $row[] = '';
                                }
                            }
                        }
                    }
                    // If some actions are available, display the selection checkbox.
                    if (($status->mode == 'manage') && !empty($status->actions) && !empty($row)) {
                        $row[] = html_writer::empty_tag('input', array('type' => 'checkbox',
                                                                       'name' => 'requestid[]',
                                                                       'class' => 'request',
                                                                       'value' => $request->id));
                    }
                }
            }
            // Add row to the table.
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
    }
    return $rows;
}

/**
 * Tracking of processed reservation.
 *
 * This class prints reservation information into a html table.
 *
 * @package    mod_reservation
 * @copyright  2007 Petr Skoda {@link http://skodak.org}
 * @copyright  2012 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ur_progress_tracker {
    /** @var array The row */
    private $_row;

    /** @var array $columns List of columns */
    public $columns = array('status', 'line', 'course', 'section', 'name', 'timestart', 'timeclose');

    /**
     * Print table header.
     *
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="urresults" class="generaltable boxaligncenter flexible-wrap" summary="'.
                get_string('uploadreservationsresult', 'reservation').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('linenumber', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('course').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('section').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('name').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timestart', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timeclose', 'reservation').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     *
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number.
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal' => '', 'info' => '', 'warning' => '', 'error' => '');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $field) {
            $types = array_keys((array) $field);
            foreach ($types as $type) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="ur'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal' => '', 'info' => '', 'warning' => '', 'error' => '');
        }
    }

    /**
     * Add tracking info
     *
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); // Init arrays.
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .= '<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     *
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

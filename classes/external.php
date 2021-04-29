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
 * This is the external API for this tool.
 *
 * @package    mod_reservation
 * @copyright  2019 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_reservation;
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * This is the external API for this tool.
 *
 * @copyright  2019 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Returns the get_requests_users() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_requests_users_parameters() {
        return new \external_function_parameters(
            array(
                'reservationid' => new \external_value(PARAM_INT, 'Reservation id'),
                'requestids' => new \external_multiple_structure(
                       new \external_value(PARAM_INT, 'Request ids')
                )
            )
        );
    }

    /**
     * Retrieve users ids from requests ids.
     *
     * @param int $reservationid Reservation id.
     * @param array $requestids Requests ids.
     *
     * @return array
     */
    public static function get_requests_users($reservationid, $requestids) {
        global $DB;

        $params = array(
            'reservationid' => $reservationid,
            'requestids' => $requestids,
        );
        self::validate_parameters(self::get_requests_users_parameters(), $params);

        if ($reservation = $DB->get_record('reservation', array('id' => $reservationid))) {
            if ($course = $DB->get_record('course', array('id' => $reservation->course))) {
                if ($cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
                    self::validate_context(\context_module::instance($cm->id));
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        if (!is_array($requestids) || empty($requestids)) {
            return false;
        }

        foreach ($requestids as $num => $requestid) {
            if (empty($requestid)) {
                unset($requestids[$num]);
            }
        }

        $userids = array();
        foreach ($requestids as $requestid) {
            $request = $DB->get_record('reservation_request', array('id' => $requestid));
            if ($request && ($request->reservation == $reservationid)) {
                $user = $DB->get_record('user', array('id' => $request->userid));
                if ($user) {
                    $userids[] = $user->id;
                }
            }
        }

        if (!empty($userids)) {
            return $userids;
        } else {
            return false;
        }
    }

    /**
     * Returns the get_requests_users result value.
     *
     * @return \external_value
     */
    public static function get_requests_users_returns() {
        return new \external_multiple_structure(new \external_value(PARAM_INT, 'User id'));
    }

    /**
     * Returns the get_matchvalues() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_matchvalues_parameters() {
        return new \external_function_parameters(
            array(
                'courseid' => new \external_value(PARAM_INT, 'Course id'),
                'fieldname' => new \external_value(PARAM_ALPHANUMEXT, 'Field name')
            )
        );
    }

    /**
     * Retrieve values of users profile given field.
     *
     * @param int $courseid Course id.
     * @param string $fieldname Field name.
     *
     * @return array
     */
    public static function get_matchvalues($courseid, $fieldname) {
        global $DB;

        $params = array(
            'courseid' => $courseid,
            'fieldname' => $fieldname,
        );
        self::validate_parameters(self::get_matchvalues_parameters(), $params);

        self::validate_context(\context_course::instance($courseid));

        require_once(__DIR__ . '/../locallib.php');

        $values = array();

        $customfields = reservation_get_profilefields();

        // Get the list of used values for requested field.
        if (isset($customfields[$fieldname])) {
            // Retrieve custom field values.
            $queryparameters = array('fieldid' => $customfields[$fieldname]->id);
            if ($datas = $DB->get_records('user_info_data', $queryparameters, 'data ASC', 'DISTINCT data')) {
                foreach ($datas as $data) {
                    if (!empty($data->data)) {
                        $values[] = $data->data;
                    }
                }
            }
        } else if ($fieldname == 'group') {
            // Get groups list.
            $groups = groups_get_all_groups($courseid);
            if (!empty($groups)) {
                foreach ($groups as $group) {
                    $values[] = $group->name;
                }
            }
        } else {
            // One of standard fields.
            if (in_array($fieldname, array('city', 'institution', 'department', 'address'))) {
                $datas = $DB->get_records_select('user', 'deleted=0 AND '.$fieldname.'<>""', null,
                        $fieldname.' ASC', 'DISTINCT '.$fieldname);
                foreach ($datas as $data) {
                    if (!empty($data->{$fieldname})) {
                        $values[] = $data->{$fieldname};
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Returns the get_matchvalues result value.
     *
     * @return \external_value
     */
    public static function get_matchvalues_returns() {
        return new \external_multiple_structure(new \external_value(PARAM_NOTAGS, 'User field value'));
    }

    /**
     * Returns the get_clashes() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_clashes_parameters() {
        return new \external_function_parameters(
            array(
                'courseid' => new \external_value(PARAM_INT, 'Course id'),
                'place' => new \external_value(PARAM_NOTAGS, 'Location string'),
                'timestart' => new \external_value(PARAM_ALPHANUMEXT, 'Timestamp of event start time'),
                'timeend' => new \external_value(PARAM_ALPHANUMEXT, 'Timestamp of event end time'),
                'reservationid' => new \external_value(PARAM_ALPHANUM, 'Reservation id'),
            )
        );
    }

    /**
     * Return if current reservation settings clash with others by time and place.
     *
     * @param int $courseid Course id.
     * @param string $place Event location.
     * @param string $timestartstr Event start time.
     * @param string $timeendstr Event end time.
     * @param int $reservationid Reservation id.
     *
     * @return string HTML that show clashes or errors.
     */
    public static function get_clashes($courseid, $place, $timestartstr, $timeendstr='', $reservationid=0) {
        if (empty($reservationid)) {
            $reservationid = 0;
        }

        $params = array(
            'courseid' => $courseid,
            'place' => $place,
            'timestart' => $timestartstr,
            'timeend' => $timeendstr,
            'reservationid' => $reservationid,
        );

        self::validate_parameters(self::get_clashes_parameters(), $params);

        self::validate_context(\context_course::instance($courseid));

        require_once(__DIR__ . '/../locallib.php');

        $checkclashes = get_config('reservation', 'check_clashes');
        $minduration = get_config('reservation', 'min_duration');
        if ($checkclashes) {
            if ($minduration === false) {
                // Minimal duration an hour.
                $minduration = 3600;
            }
            if (!empty($timestartstr)) {
                $times = explode('-', $timestartstr);
                $timestart = make_timestamp($times[0], $times[1], $times[2], $times[3], $times[4], 0, 99, true);

                $timeend = $timestart + $minduration;

                if (!empty($timeendstr)) {
                    $times = explode('-', $timeendstr);
                    $timeend = make_timestamp($times[0], $times[1], $times[2], $times[3], $times[4], 0, 99, true);
                }
                if ($timestart < $timeend) {
                    if ($reservations = reservation_get_reservations_by_course($courseid, $place)) {
                        $strftimedaydatetime = get_string('strftimedatetime');

                        $collisiontable = new \html_table();
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
                                $extimeend = $reservation->timestart + $minduration;
                                if (!empty($reservation->timeend)) {
                                    $extimeend = $reservation->timeend;
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
                                    $columns[] = \html_writer::tag('em', userdate($extimeend, $strftimedaydatetime),
                                            array('class' => 'stimed'));
                                }
                                $collisiontable->data[] = $columns;
                            }
                        }
                        if (!empty($collisiontable->data)) {
                            return \html_writer::table($collisiontable);
                        }
                    } else {
                        return \html_writer::tag('span', get_string('noclashes', 'reservation'));
                    }
                } else {
                    return \html_writer::tag('span', get_string('err_timeendlower', 'reservation'));
                }
            } else {
                return \html_writer::tag('span', get_string('err_notimestart', 'reservation'));
            }
        } else {
            return \html_writer::tag('span', get_string('noclashcheck', 'reservation'));
        }
    }

    /**
     * Returns the get_clashes result value.
     *
     * @return \external_value
     */
    public static function get_clashes_returns() {
        return new \external_value(PARAM_RAW, 'Clashes table or messages');
    }

    /* 
     * Returns the cancel_request() parameters.
     *
     * @return \external_function_parameters
     */
    public static function cancel_request_parameters() {
        return new \external_function_parameters(
            array(
                'reservationid' => new \external_value(PARAM_INT, 'Reservation id'),
            )
        );
    }

    /**
     * Try to cancel user current request.
     *
     * @param int $reservationid Reservation id.
     *
     * @return bool True on success or false on fail.
     */
    public static function cancel_request($reservationid) {
        global $DB, $USER;

        // Generate the result.
        $result = array();
        $result['error'] = '';

        $params = array(
            'reservationid' => $reservationid,
        );
        self::validate_parameters(self::cancel_request_parameters(), $params);

        $reservation = $DB->get_record('reservation', array('id' => $reservationid));
        $course = $DB->get_record('course', array('id' => $reservation->course));
        $cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id);

        $context = \context_module::instance($cm->id);

        self::validate_context($context);

        require_once(__DIR__ . '/../locallib.php');
        if (reservation_cancel($reservation, $course, $cm, $context)) {
            $result['status'] = true;
        } else {
            $result['status'] = false;
            $result['error'] = 'notreserved';
        }
        return $result;
    }


    /**
     * Returns the cancel_request result value.
     *
     * @return \external_value
     */
    public static function cancel_request_returns() {
        return new \external_single_structure(
            array(
                'status' => new \external_value(PARAM_BOOL, 'status: true if success'),
                'error' => new \external_value(PARAM_ALPHANUMEXT, 'Message on failure')
            )
        );
    }

    /* 
     * Returns the reserve_request() parameters.
     *
     * @return \external_function_parameters
     */
    public static function reserve_request_parameters_with_note() {
        return new \external_function_parameters(
            array(
                'reservationid' => new \external_value(PARAM_INT, 'Reservation id'),
                'userid' => new \external_value(PARAM_INT, 'User id'),
                'note' => new \external_value(PARAM_NOTAGS, 'User note'),
            )
        );
    }

    /* 
     * Returns the reserve_request() parameters.
     *
     * @return \external_function_parameters
     */
    public static function reserve_request_parameters() {
        return new \external_function_parameters(
            array(
                'reservationid' => new \external_value(PARAM_INT, 'Reservation id'),
                'userid' => new \external_value(PARAM_INT, 'User id'),
            )
        );
    }

    /**
     * Try to reserve a user request.
     *
     * @param int $reservationid Reservation id.
     * @param int $userid User id.
     * @param string $note User note.

     *
     * @return bool True on success or false on fail.
     */
    public static function reserve_request($reservationid, $userid=0, $note='') {
        global $DB, $USER;

        $reservation = $DB->get_record('reservation', array('id' => $reservationid));
        $course = $DB->get_record('course', array('id' => $reservation->course));
        $cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id);

        $context = \context_module::instance($cm->id);

        self::validate_context($context);

        if (empty($userid) || !has_capability('mod/reservation:manualreserve', $context)) {
            $userid = $USER->id;
        }

        $params = array(
            'reservationid' => $reservationid,
            'userid' => $userid,
        );
        if ($reservation->note > 0) {
            $params['note'] = $note;
            self::validate_parameters(self::reserve_request_parameters_with_note(), $params);
        } else {
            self::validate_parameters(self::reserve_request_parameters(), $params);
        }

        require_once(__DIR__ . '/../locallib.php');

        $status = new \stdClass();
        $status->mode = 'overview';
        $status->view = '';
        // Check to see if groups are being used in this reservation.
        $status->groupmode = groups_get_activity_groupmode($cm);
        if ($status->groupmode != NOGROUPS) {
            $status->group = groups_get_activity_group($cm, true);
        }

        // Get profile custom fields array.
        $customfields = reservation_get_profilefields();

        // Set global and sublimit counters.
        $counters = reservation_setup_counters($reservation, $customfields);

        // Add sublimits fields to used fields.
        $fields = reservation_setup_sublimit_fields($counters, $customfields);

        // Get all reservation requests.
        $requests = reservation_get_requests($reservation, true, $fields, $status);
        $rows = array();
        if (!empty($requests)) {
            $rows = reservation_get_table_data($reservation, $requests, $addableusers, $counters, $fields, $status);
        }

        // Set available seats in global count.
        $seats = reservation_get_availability($reservation, $counters, $context);

        $result = reservation_reserve($reservation, $seats, $note, $userid);
       
        return $result;
        
    }


    /**
     * Returns the cancel_request result value.
     *
     * @return \external_value
     */
    public static function reserve_request_returns() {
        return new \external_single_structure(
            array(
                'status' => new \external_value(PARAM_BOOL, 'status: true if success'),
                'error' => new \external_value(PARAM_ALPHANUMEXT, 'Message string on failure')
            )
        );
    }
}

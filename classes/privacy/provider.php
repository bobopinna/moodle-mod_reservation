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
 * Privacy Subsystem implementation for mod_reservation.
 *
 * @package    mod_reservation
 * @copyright  2018 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reservation\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Implementation of the privacy subsystem plugin provider for the reservation activity module.
 *
 * @copyright  2018 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // This plugin stores personal data.
        \core_privacy\local\metadata\provider,

        // This plugin is a core_user_data_provider.
        \core_privacy\local\request\plugin\provider,

        // This plugin is capable of determining which users have data within it.
        \core_privacy\local\request\core_userlist_provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'reservation_request',
            [
                'userid' => 'privacy:metadata:reservation_request:userid',
                'timecreated' => 'privacy:metadata:reservation_request:timecreated',
                'timecancelled' => 'privacy:metadata:reservation_request:timecancelled',
                'teacher' => 'privacy:metadata:reservation_request:grader',
                'grade' => 'privacy:metadata:reservation_request:grade',
                'timegraded' => 'privacy:metadata:reservation_request:timegraded',
                'mailed' => 'privacy:metadata:reservation_request:mailed',
            ],
            'privacy:metadata:reservation_request'
        );
        $items->add_database_table(
            'reservation_note',
            [
                'note' => 'privacy:metadata:reservation_note:note',
            ],
            'privacy:metadata:reservation_note'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {reservation} r ON r.id = cm.instance
            INNER JOIN {reservation_request} rr ON rr.reservation = r.id AND
                        (rr.userid = :userid OR rr.teacher = :graderid)";

        $params = [
            'modname'       => 'reservation',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
            'graderid'       => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // Fetch all reservation requests.
        $sql = "SELECT rr.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {reservation} r ON r.id = cm.instance
                  JOIN {reservation_request} rr ON rr.reservation = r.id
                 WHERE cm.id = :cmid";

        $params = [
            'cmid'      => $context->instanceid,
            'modname'   => 'reservation',
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       rr.userid as userid,
                       rr.timecreated as timecreated,
                       n.note as note,
                       rr.timecancelled as timecancelled,
                       rr.teacher as grader,
                       rr.grade as grade,
                       rr.timegraded as timegraded
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {reservation} r ON r.id = cm.instance
            INNER JOIN {reservation_request} rr ON rr.reservation = cm.instance
            INNER JOIN {reservation_note} n ON n.request = rr.id
                 WHERE c.id {$contextsql}
                       AND (rr.userid = :userid OR rr.teacher = :graderid)
              ORDER BY cm.id";

        $params = ['modname' => 'reservation',
                   'contextlevel' => CONTEXT_MODULE,
                   'userid' => $user->id,
                   'graderid' => $user->id] + $contextparams;

        $lastcmid = null;

        $reservationrequests = $DB->get_recordset_sql($sql, $params);
        foreach ($reservationrequests as $reservationrequest) {
            if ($lastcmid != $reservationrequest->cmid) {
                if (!empty($reservationdata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_reservation_data_for_user($reservationdata, $context, $user);
                }
                $reservationdata = [
                    'requests' => []
                ];
            }
            $requestdata = new \stdClass();
            $requestdata->userid = $reservationrequest->userid;
            $requestdata->timecreated = \core_privacy\local\request\transform::datetime($reservationrequest->timecreated);
            $requestdata->note = $reservationrequest->note;
            $requestdata->timecancelled = \core_privacy\local\request\transform::datetime($reservationrequest->timecancelled);
            $requestdata->grader = $reservationrequest->grader;
            $requestdata->grade = $reservationrequest->grade;
            $requestdata->timegraded = \core_privacy\local\request\transform::datetime($reservationrequest->timegraded);
            $reservationdata['requests'][] = $requestdata;
            $lastcmid = $reservationrequest->cmid;
        }
        $reservationrequests->close();

        if (!empty($reservationdata)) {
            $context = \context_module::instance($lastcmid);
            self::export_reservation_data_for_user($reservationdata, $context, $user);
        }
    }

    /**
     * Export the supplied personal data for a single reservation activity, along with any generic data or area files.
     *
     * @param array $reservationdata the personal data to export for the reservation.
     * @param \context_module $context the context of the reservation.
     * @param \stdClass $user the user record
     */
    protected static function export_reservation_data_for_user(array $reservationdata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the reservation.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with reservation data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $reservationdata);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('reservation', $context->instanceid)) {
            $instanceid = $cm->instance;
            $requests = $DB->get_records('reservation_request', ['reservation' => $instanceid]);
            if (!empty($requests)) {
                foreach ($requests as $request) {
                    $DB->delete_records('reservation_note', ['request' => $request->id]);
                }
                $DB->delete_records('reservation_request', ['reservation' => $instanceid]);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {

            if (!$context instanceof \context_module) {
                continue;
            }
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $requests = $DB->get_records('reservation_request', ['reservation' => $instanceid, 'userid' => $userid]);
            if (!empty($requests)) {
                foreach ($requests as $request) {
                    $DB->delete_records('reservation_note', ['request' => $request->id]);
                    $DB->delete_records('reservation_request', ['id' => $request->id]);
                }
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('reservation', $context->instanceid);

        if (!$cm) {
            // Only choice module will be handled.
            return;
        }

        $userids = $userlist->get_userids();
        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "reservation = :reservationid AND userid $usersql";
        $params = ['reservationid' => $cm->instance] + $userparams;
        $requests = $DB->get_records_select('reservation_request', $select, $params);
        if (!empty($requests)) {
            foreach ($requests as $request) {
                $DB->delete_records('reservation_note', ['request' => $request->id]);
                $DB->delete_records('reservation_request', ['id' => $request->id]);
            }
        }
    }
}

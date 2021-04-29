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
 * Contains the mobile output class for reservation.
 *
 * @package   mod_reservation
 * @copyright 2018 Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reservation\output;

defined('MOODLE_INTERNAL') || die();

use context_module;
use mod_reservation_external;
require_once($CFG->dirroot . '/mod/reservation/locallib.php');
/**
 * Mobile output class for reservation.
 *
 * @copyright 2019 Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_course_view($args) {
        global $OUTPUT, $DB, $USER;

        $args = (object) $args;

        $cmid = $args->cmid;
        $groupid = empty($args->group) ? 0 : $args->group; // By default, group 0.

        // Capabilities check.
        $cm = get_coursemodule_from_id('reservation', $cmid);
        $context = \context_module::instance($cm->id);
        self::require_capability($cm, $context, 'mod/reservation:view');

        // Set some variables we are going to be using.
        $course = $DB->get_record('course', array('id' => $cm->course));
        $reservation = $DB->get_record('reservation', ['id' => $cm->instance], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($reservation->course);
        $now = time();

        $status = new \stdClass();
        $status->mode = 'overview';
        $status->view = '';
        $status->canmanage = has_capability('mod/reservation:manage', $context);
        $status->canreserve = has_capability('mod/reservation:reserve', $context);

        $status->notopened = $now < $reservation->timeopen;
        $status->closed = $now > $reservation->timeclose;
        $status->opened = ($now >= $reservation->timeopen) && ($now <= $reservation->timeclose);


        $status->showreport = false;
        $canviewlistnow = ($reservation->showrequest == 1) && ($now > $reservation->timeclose) && (is_enrolled($coursecontext));
        $canviewlistalways = ($reservation->showrequest == 2) && (is_enrolled($coursecontext));
        if (has_capability('mod/reservation:viewrequest', $context) || $canviewlistnow || $canviewlistalways) {
            $status->showreport = true;
        }

        // Get the groups (if any) to display - also sets active group.
        $status->groups = self::get_groups($cm, $groupid, $USER->id);
        $status->group = $groupid;
        $status->groupmode = groups_get_activity_groupmode($cm);
        if (has_capability('moodle/site:accessallgroups', $context)) {
            $status->groupmode = 'aag';
        }
        $status->showgroups = !empty($groups);

        $params = array(
            'context' => $context,
            'objectid' => $reservation->id
        );
        $event = \mod_reservation\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('reservation', $reservation);
        $event->trigger();

        $reservation->name = format_string($reservation->name);
        list($reservation->intro, $reservation->introformat) = external_format_text($reservation->intro,
            $reservation->introformat, $context->id, 'mod_reservation', 'intro');
        $reservation->location = format_string($reservation->location);

        $reservation->teachername = reservation_get_teacher_names($reservation, $cmid);
        if (!empty($reservation->teachername)) {
            $teacherroles = get_archetype_roles('editingteacher');
            $teacherrole = array_shift($teacherroles);
            $reservation->teacherstr = role_get_name($teacherrole, $coursecontext);
        }
        $status->noterequired = ($reservation->note == 2);

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

        $status->currentuser = new \stdClass();
        // Get all reservation requests.
        $requests = reservation_get_requests($reservation, true, $fields, $status);
        $rows = array();
        if (!empty($requests)) {
            $request = reset($requests);
            $rows = reservation_get_table_data($reservation, $requests, $addableusers, $counters, $fields, $status);

            // Get user request information (if already reserved).
            $status->currentuser = reservation_get_current_user($reservation, $requests);
        }

        // Set available seats in global count.
        $seats = reservation_get_availability($reservation, $counters, $context);

        $status->reserved = isset($status->currentuser->number);

        $data = [
            'reservation' => $reservation,
            'seats' => $seats,
            'requests' => $rows,
            'status' => $status,
            'cmid' => $cm->id,
            'courseid' => $args->courseid,
            'currenttimestamp' => time(),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_reservation/mobile_view_page', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => ''
        ];
    }

    /**
     * Returns an array of groups to be displayed (if applicable) for the activity.
     *
     * The groups API is a mess hence the hackiness.
     *
     * @param \stdClass $cm The course module
     * @param int $groupid The group id
     * @param int $userid The user id
     * @return array The array of groups, may be empty.
     */
    protected static function get_groups($cm, $groupid, $userid) {
        $arrgroups = [];
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            if ($groups = groups_get_activity_allowed_groups($cm, $userid)) {
                $context = \context_module::instance($cm->id);
                if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
                    $allparticipants = new \stdClass();
                    $allparticipants->id = 0;
                    $allparticipants->name = get_string('allparticipants');
                    $allparticipants->selected = $groupid === 0;
                    $arrgroups[0] = $allparticipants;
                }
                self::update_active_group($groupmode, $groupid, $groups, $cm);
                // Detect which group is selected.
                foreach ($groups as $gid => $group) {
                    $group->selected = $gid == $groupid;
                    $arrgroups[] = $group;
                }
            }
        }

        return $arrgroups;
    }

    /**
     * Update the active group in the session.
     *
     * This is a hack. We can't call groups_get_activity_group to update the active group as it relies
     * on optional_param('group' .. which we won't have when using the mobile app.
     *
     * @param int $groupmode The group mode we are in, eg. NOGROUPS, VISIBLEGROUPS
     * @param int $groupid The id of the group that has been selected
     * @param array $allowedgroups The allowed groups this user can access
     * @param \stdClass $cm The course module
     */
    private static function update_active_group($groupmode, $groupid, $allowedgroups, $cm) {
        global $SESSION;

        $context = \context_module::instance($cm->id);

        if (has_capability('moodle/site:accessallgroups', $context)) {
            $groupmode = 'aag';
        }

        if ($groupid == 0) {
            // The groups are only all visible in VISIBLEGROUPS mode or if the user can access all groups.
            if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
                $SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid] = 0;
            }
        } else {
            if ($allowedgroups && array_key_exists($groupid, $allowedgroups)) {
                $SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid] = $groupid;
            }
        }
    }

    /**
     * Confirms the user is logged in and has the specified capability.
     *
     * @param \stdClass $cm
     * @param \context $context
     * @param string $cap
     */
    protected static function require_capability(\stdClass $cm, \context $context, string $cap) {
        require_login($cm->course, false, $cm, true, true);
        require_capability($cap, $context);
    }
}

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
 * Define all the restore steps that will be used by the restore_reservation_activity_task
 *
 * @package   mod_reservation
 * @copyright 2012 onwards Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one reservation activity
 *
 * @package   mod_reservation
 * @copyright 2012 onwards Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_reservation_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('reservation', '/activity/reservation');
        $paths[] = new restore_path_element('reservation_limit', '/activity/reservation/limits/limit');
        if ($userinfo) {
            $paths[] = new restore_path_element('reservation_request', '/activity/reservation/requests/request');
            $paths[] = new restore_path_element('reservation_note', '/activity/reservation/requests/request/notes/note');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process an assign restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reservation($data) {
        global $DB;

        // Hack to get if this restore is part of duplicate action.
        $duplicate = false;
        $backtraces = debug_backtrace();
        foreach($backtraces as $i => $backtrace) {
             if ($backtrace['function'] == 'duplicate_module') {
                 $duplicate = true;
             }
        }

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->teachers = '';
        $newteachers = array();
        if (!empty($data->teachers)) {
            $teachers = explode(',', $data->teachers);
            foreach ($teachers as $teacher) {
                $newteachers = $this->get_mappingid('user', $teacher);
            }
        }
        if (!empty($newteachers)) {
            $data->teachers = implode(',', $newteachers);
        }

        if ((!empty($data->parent)) && (!$duplicate)) {
            $data->parent = -$data->parent;
        }

        if (!isset($data->grade) && isset($data->maxgrade)) {
            $data->grade = $data->maxgrade;
        }

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the reservation record.
        $newitemid = $DB->insert_record('reservation', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);

        $this->set_mapping('reservation', $oldid, $newitemid);
    }

    /**
     * Process a sublimit restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reservation_limit($data) {
        global $DB;

        $data = (object)$data;

        $data->reservationid = $this->get_new_parentid('reservation');

        $DB->insert_record('reservation_limit', $data);
    }

    /**
     * Process a reservation request restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reservation_request($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->reservation = $this->get_new_parentid('reservation');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timecancelled = $this->apply_date_offset($data->timecancelled);
        $data->teacher = $this->get_mappingid('user', $data->teacher);
        $data->timegraded = $this->apply_date_offset($data->timegraded);

        $newitemid = $DB->insert_record('reservation_request', $data);
        $this->set_mapping('reservation_request', $oldid, $newitemid);
    }

    /**
     * Process a reservation note restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reservation_note($data) {
        global $DB;

        $data = (object)$data;

        $data->request = $this->get_new_parentid('reservation_request');

        $DB->insert_record('reservation_note', $data);
    }

    /**
     * Once the database tables have been fully restored, restore the files
     * @return void
     */
    protected function after_execute() {

        // Add reservation related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_reservation', 'intro', null);
    }

    /**
     * Once the database tables have been restored, restore the reservations connections
     * @return void
     */
    protected function after_restore() {
        global $DB, $OUTPUT;

        // Now that all the reservation have been restored,
        // let's process the reservation connections.
        $rs = $DB->get_recordset_sql("
                SELECT r.id, r.parent
                  FROM {reservation} r
                  JOIN {backup_ids_temp} bi ON bi.newitemid = r.id
                 WHERE bi.backupid = ?
                   AND bi.itemname = 'reservation'",
                array($this->get_restoreid()));

        foreach ($rs as $rec) {
            if ($rec->parent < 0) {
                $newparentid = $this->get_mappingid('reservation', -$rec->parent);
                if ($newparentid !== false) {
                    $DB->set_field('reservation', 'parent', $newparentid, array('id' => $rec->id));
                } else {
                    $DB->set_field('reservation', 'parent', 0, array('id' => $rec->id));
                    $OUTPUT->notification(get_string('badparent', 'reservation'));
                }
            }
        }
    }
}

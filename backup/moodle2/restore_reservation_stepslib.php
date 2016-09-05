<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_reservation_activity_task
 */

/**
 * Structure step to restore one reservation activity
 */
class restore_reservation_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('reservation', '/activity/reservation');
        $paths[] = new restore_path_element('reservation_limit', '/activity/reservation/limits/limit');
        if ($userinfo) {
            $paths[] = new restore_path_element('reservation_request', '/activity/reservation/requests/request');
            $paths[] = new restore_path_element('reservation_note', '/activity/reservation/requests/request/notes/note');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_reservation($data) {
        global $DB;

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

        if (!empty($data->parent)) {
            $data->parent = -$data->parent;
        }

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the reservation record
        $newitemid = $DB->insert_record('reservation', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);

        $this->set_mapping('reservation', $oldid, $newitemid);
    }

    protected function process_reservation_limit($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->reservationid = $this->get_new_parentid('reservation');

        $newitemid = $DB->insert_record('reservation_limit', $data);
    }

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

    protected function process_reservation_note($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->request = $this->get_new_parentid('reservation_request');

        $newitemid = $DB->insert_record('reservation_note', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {

        // Add reservation related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_reservation', 'intro', null);
    }

    protected function after_restore() {
        global $DB;


        // Now that all the questions have been restored, let's process
        // the created question_multianswer sequences (list of question ids).
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
                    notify(get_string('badparent', 'reservation'));
                }
            }
        }
    }
}

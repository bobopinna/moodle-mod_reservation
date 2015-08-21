<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_reservation_activity_task
 */

/**
 * Define the complete reservation structure for backup, with file and id annotations
 */
class backup_reservation_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $reservation = new backup_nested_element('reservation', array('id'), array(
            'name', 'intro', 'introformat', 'teachers',
            'location', 'timestart', 'timeend', 'maxgrade',
            'timeopen', 'timeclose', 'note', 'maxrequest',
            'overbook', 'showrequest', 'parent', 'mailed',
            'completionreserved', 'timemodified'));

        $limits = new backup_nested_element('limits');

        $limit = new backup_nested_element('limit', array('id'), array(
            'field', 'operator', 'matchvalue',
            'requestlimit'));

        $requests = new backup_nested_element('requests');

        $request = new backup_nested_element('request', array('id'), array(
            'userid', 'timecreated', 'timecancelled',
            'teacher', 'grade', 'timegraded', 'mailed'));

        $notes = new backup_nested_element('notes');

        $note = new backup_nested_element('note', array('id'), array(
            'request', 'note'));

        // Build the tree
        $reservation->add_child($limits);
        $limits->add_child($limit);

        $reservation->add_child($requests);
        $requests->add_child($request);

        $request->add_child($notes);
        $notes->add_child($note);

        // Define sources
        $reservation->set_source_table('reservation', array('id' => backup::VAR_ACTIVITYID));

        $limit->set_source_sql('
            SELECT *
              FROM {reservation_limit}
              WHERE reservationid = ?',
            array(backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $request->set_source_sql('
                SELECT *
                  FROM {reservation_request}
                  WHERE reservation = ?',
                array(backup::VAR_PARENTID));

            // Define id annotations
            $request->annotate_ids('user', 'userid');

            $note->set_source_sql('
                SELECT *
                  FROM {reservation_note}
                  WHERE request = ?',
                array(backup::VAR_PARENTID));

           // $note->set_source_table('reservation_note', array('' => '../../id'));
        }

        // Define id annotations
        $reservation->annotate_ids('reservation', 'parent');

        // Define file annotations
        $reservation->annotate_files('mod_reservation', 'intro', null); // This file area hasn't itemid

        // Return the root element (reservation), wrapped into standard activity structure
        return $this->prepare_activity_structure($reservation);
    }
}

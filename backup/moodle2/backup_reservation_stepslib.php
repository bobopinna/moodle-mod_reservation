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
 * Define all the backup steps that will be used by the backup_reservation_activity_task
 *
 * @package   mod_reservation
 * @copyright 2012 onwards Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete reservation structure for backup, with file and id annotations
 *
 * @package   mod_reservation
 * @copyright 2012 onwards Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_reservation_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the reservation activity
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $reservation = new backup_nested_element('reservation', ['id'], [
            'name', 'intro', 'introformat', 'teachers',
            'location', 'timestart', 'timeend', 'grade',
            'timeopen', 'timeclose', 'note', 'maxrequest',
            'overbook', 'showrequest', 'parent', 'mailed',
            'completionreserved', 'timemodified', ]);

        $limits = new backup_nested_element('limits');

        $limit = new backup_nested_element('limit', ['id'], ['field', 'operator', 'matchvalue', 'requestlimit']);

        $requests = new backup_nested_element('requests');

        $request = new backup_nested_element('request', ['id'], [
            'userid', 'timecreated', 'timecancelled',
            'teacher', 'grade', 'timegraded', 'mailed', ]);

        $notes = new backup_nested_element('notes');

        $note = new backup_nested_element('note', ['id'], ['request', 'note']);

        // Build the tree.
        $reservation->add_child($limits);
        $limits->add_child($limit);

        $reservation->add_child($requests);
        $requests->add_child($request);

        $request->add_child($notes);
        $notes->add_child($note);

        // Define sources.
        $reservation->set_source_table('reservation', ['id' => backup::VAR_ACTIVITYID]);

        $limit->set_source_sql('
            SELECT *
              FROM {reservation_limit}
              WHERE reservationid = ?',
            [backup::VAR_PARENTID]);

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $request->set_source_sql('
                SELECT *
                  FROM {reservation_request}
                  WHERE reservation = ?',
                [backup::VAR_PARENTID]);

            // Define id annotations.
            $request->annotate_ids('user', 'userid');

            $note->set_source_sql('
                SELECT *
                  FROM {reservation_note}
                  WHERE request = ?',
                [backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $reservation->annotate_ids('reservation', 'parent');

        // Define file annotations.
        $reservation->annotate_files('mod_reservation', 'intro', null); // This file area hasn't itemid.

        // Return the root element (reservation), wrapped into standard activity structure.
        return $this->prepare_activity_structure($reservation);
    }
}

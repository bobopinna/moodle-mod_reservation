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
 * This file keeps track of upgrades to the Reservation plugin
 *
 * @package mod_reservation
 * @copyright 2007 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Reservation module upgrade task
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_reservation_upgrade($oldversion=0) {

    global $DB;

    // Loads ddl manager and xmldb classes.
    $dbman = $DB->get_manager();

    if ($oldversion < 2016051800) {
        set_config('reservation_download', null);

        upgrade_mod_savepoint(true, 2016051800, 'reservation');
    }
    if ($oldversion < 2016071100) {
        upgrade_mod_savepoint(true, 2016071100, 'reservation');
    }
    if ($oldversion < 2017022100) {
        upgrade_mod_savepoint(true, 2017022100, 'reservation');
    }
    if ($oldversion < 2017022101) {
        // Define field eventid to be added to reservation_request.
        $table = new xmldb_table('reservation_request');
        $field = new xmldb_field('eventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'mailed');

        // Conditionally launch add field parent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2017022101, 'reservation');
    }
    if ($oldversion < 2017051500) {
        upgrade_mod_savepoint(true, 2017051500, 'reservation');
    }
    if ($oldversion < 2017110300) {
        upgrade_mod_savepoint(true, 2017110300, 'reservation');
    }
    if ($oldversion < 2018100600) {
        upgrade_mod_savepoint(true, 2018100600, 'reservation');
    }
    if ($oldversion < 2018111400) {
        $currentsettings = array(
            'max_requests',
            'connect_to',
            'check_clashes',
            'min_duration',
            'max_overbook',
            'overbook_step',
            'sublimits',
            'list_sort',
            'publiclists',
            'deltatime',
            'fields',
            'manual_users',
            'notifies',
            'events'
        );
        $reservationsettings = $DB->get_records_select('config', 'name like ?', array('reservation_%'));
        if (!empty($reservationsettings)) {
            foreach ($reservationsettings as $reservationsetting) {
                $settingname = substr($reservationsetting->name, strlen('reservation_') + 1);
                if (in_array($settingname, $currentsettings)) {
                    set_config($settingname, $reservationsetting->value, 'reservation');
                }
                $DB->delete_records('config', array('id' => $reservationsetting->id));
            }
        }
        upgrade_mod_savepoint(true, 2018111400, 'reservation');
    }
    if ($oldversion < 2019022000) {
        upgrade_mod_savepoint(true, 2019022000, 'reservation');
    }
}

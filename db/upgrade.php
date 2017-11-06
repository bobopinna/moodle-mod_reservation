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

    global $CFG, $THEME, $DB;

    // Loads ddl manager and xmldb classes.
    $dbman = $DB->get_manager();

    if ($oldversion < 2009051301) {
        $DB->delete_records_select('reservation_note', 'request NOT IN ( SELECT rq.id FROM {reservation_request} rq WHERE 1)');
        upgrade_mod_savepoint(true, 2009051301, 'reservation');
    }

    if ($oldversion < 2009052000) {

        // Changing sign of field grade on table reservation_request to signed.
        $table = new xmldb_table('reservation_request');
        $field = new xmldb_field('grade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '-1', 'teacher');

        // Launch change of sign for field grade.
        $dbman->change_field_unsigned($table, $field);
        upgrade_mod_savepoint(true, 2009052000, 'reservation');
    }

    if ($oldversion < 2009052001) {

        // Changing sign of field maxgrade on table reservation to signed.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('maxgrade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeend');

        // Launch change of sign for field maxgrade.
        $dbman->change_field_unsigned($table, $field);
        upgrade_mod_savepoint(true, 2009052001, 'reservation');
    }

    if ($oldversion < 2010081801) {

        // Rename field description on table reservation to intro.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

        // Launch rename field description.
        $dbman->rename_field($table, $field, 'intro');
        upgrade_mod_savepoint(true, 2010081801, 'reservation');
    }

    if ($oldversion < 2010081802) {

        // Rename field description on table reservation to intro.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

        // Launch change of nullability for field intro.
        $dbman->change_field_notnull($table, $field);

        upgrade_mod_savepoint(true, 2010081802, 'reservation');
    }

    if ($oldversion < 2010082200) {

        // Define table reservation_limit to be created.
        $table = new xmldb_table('reservation_limit');

        // Adding fields to table reservation_limit.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reservationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('operator', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('matchvalue', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('requestlimit', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table reservation_limit.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Launch create table for reservation_limit.
        $dbman->create_table($table);
        upgrade_mod_savepoint(true, 2010082200, 'reservation');
    }

    if ($oldversion < 2010100400) {

        // Define field overbook to be added to reservation.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('overbook');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'maxrequest');

        // Launch add field overbook.
        $dbman->add_field($table, $field);
        upgrade_mod_savepoint(true, 2010100400, 'reservation');
    }

    if ($oldversion < 2011032200) {

        // Define field introformat to be added to reservation.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

        // Conditionally launch add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Reservation savepoint reached.
        upgrade_mod_savepoint(true, 2011032200, 'reservation');
    }

    if ($oldversion < 2011040601) {

        // Define key reservation_id (foreign) to be added to reservation_limit.
        $table = new xmldb_table('reservation_limit');
        $key = new xmldb_key('reservation_id', XMLDB_KEY_FOREIGN, array('reservationid'), 'reservation', array('id'));

        // Launch add key reservation_id.
        $dbman->add_key($table, $key);

        // Reservation savepoint reached.
        upgrade_mod_savepoint(true, 2011040601, 'reservation');
    }

    if ($oldversion < 2011061600) {

        // Define field mailed to be added to reservation.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('mailed', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0', 'showrequest');

        // Conditionally launch add field mailed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2011061600, 'reservation');
    }

    if ($oldversion < 2012061200) {

        if ($availability = $DB->get_record('config', array('name' => 'reservation_check_availability'))) {
            $availability->name = 'reservation_check_clashes';
            $DB->update_record('config', $availability);
        }

        upgrade_mod_savepoint(true, 2012061200, 'reservation');
    }

    if ($oldversion < 2012082000) {
        upgrade_mod_savepoint(true, 2012082000, 'reservation');
    }
    if ($oldversion < 2012112800) {
        upgrade_mod_savepoint(true, 2012112800, 'reservation');
    }
    if ($oldversion < 2012120600) {

        // Define field parent to be added to reservation.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('parent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'showrequest');

        // Conditionally launch add field parent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2012120600, 'reservation');
    }
    if ($oldversion < 2013011800) {
        upgrade_mod_savepoint(true, 2013011800, 'reservation');
    }
    if ($oldversion < 2013011801) {
        upgrade_mod_savepoint(true, 2013011801, 'reservation');
    }
    if ($oldversion < 2013011900) {
        upgrade_mod_savepoint(true, 2013011900, 'reservation');
    }
    if ($oldversion < 2013020400) {
        upgrade_mod_savepoint(true, 2013020400, 'reservation');
    }
    if ($oldversion < 2013072900) {
        upgrade_mod_savepoint(true, 2013072900, 'reservation');
    }
    if ($oldversion < 2013100800) {
        $reservationfields = get_config('core', 'reservation_fields');
        if (!empty($reservationfields)) {
            $fields = explode(',', $reservationfields);
            $newfields = array();
            foreach ($fields as $field) {
                if ($field == 'state') {
                    $newfields[] = 'country';
                    mtrace('Fixing Reservation display fields names ...');
                } else {
                    $newfields[] = $field;
                }
            }
            $updatedfields = implode(',', $newfields);

            set_config('reservation_fields', $updatedfields);
        }

        upgrade_mod_savepoint(true, 2013100800, 'reservation');
    }
    if ($oldversion < 2014031900) {
        upgrade_mod_savepoint(true, 2014031900, 'reservation');
    }
    if ($oldversion < 2014071500) {
        upgrade_mod_savepoint(true, 2014071500, 'reservation');
    }
    if ($oldversion < 2015031100) {
        upgrade_mod_savepoint(true, 2015031100, 'reservation');
    }
    if ($oldversion < 2015031101) {
        // Define field conditionreserved to be added to reservation.
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('completionreserved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mailed');

        // Conditionally launch add field parent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015031101, 'reservation');
    }
    if ($oldversion < 2015111600) {
        upgrade_mod_savepoint(true, 2015111600, 'reservation');
    }
    if ($oldversion < 2015111601) {
        upgrade_mod_savepoint(true, 2015111601, 'reservation');
    }
    if ($oldversion < 2015112600) {
        upgrade_mod_savepoint(true, 2015112600, 'reservation');
    }
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
    if ($oldversion < 2017022102) {
        upgrade_mod_savepoint(true, 2017022102, 'reservation');
    }
}

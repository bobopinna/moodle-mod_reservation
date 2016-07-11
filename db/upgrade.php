<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file keeps track of upgrades to 
// the scorm module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_reservation_upgrade($oldversion=0) {

    global $CFG, $THEME, $DB;

    $dbman = $DB->get_manager(); /// loads ddl manager and xmldb classes

    if ($oldversion < 2009051301) {
        $DB->delete_records_select('reservation_note','request NOT IN ( SELECT rq.id FROM {reservation_request} rq WHERE 1)');
        upgrade_mod_savepoint(true, 2009051301, 'reservation');
    }

    if ($oldversion < 2009052000) {

    /// Changing sign of field grade on table reservation_request to signed
        $table = new xmldb_table('reservation_request');
        $field = new xmldb_field('grade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '-1', 'teacher');

    /// Launch change of sign for field grade
        $dbman->change_field_unsigned($table, $field);
        upgrade_mod_savepoint(true, 2009052000, 'reservation');
    }

    if ($oldversion < 2009052001) {

    /// Changing sign of field maxgrade on table reservation to signed
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('maxgrade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,'0', 'timeend');

    /// Launch change of sign for field maxgrade
        $dbman->change_field_unsigned($table, $field);
        upgrade_mod_savepoint(true, 2009052001, 'reservation');
    }

    if ($oldversion < 2010081801) {

        // Rename field description on table reservation to intro
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

        // Launch rename field description
        $dbman->rename_field($table, $field, 'intro');
        upgrade_mod_savepoint(true, 2010081801, 'reservation');
    }

    if ($oldversion < 2010081802) {

        // Rename field description on table reservation to intro
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

       // Launch change of nullability for field intro
        $dbman->change_field_notnull($table, $field);

        upgrade_mod_savepoint(true, 2010081802, 'reservation');
    }

    if ($oldversion < 2010082200) {

    /// Define table reservation_limit to be created
        $table = new xmldb_table('reservation_limit');

    /// Adding fields to table reservation_limit
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reservationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('operator', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('matchvalue', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('requestlimit', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

    /// Adding keys to table reservation_limit
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for reservation_limit
        $dbman->create_table($table);
        upgrade_mod_savepoint(true, 2010082200, 'reservation');
    }

    if ($oldversion < 2010100400) {

    /// Define field overbook to be added to reservation
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('overbook');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'maxrequest');

    /// Launch add field overbook
        $dbman->add_field($table, $field);
        upgrade_mod_savepoint(true, 2010100400, 'reservation');
    }

    if ($oldversion < 2011032200) {

        // Define field introformat to be added to reservation
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

        // Conditionally launch add field introformat
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // reservation savepoint reached
        upgrade_mod_savepoint(true, 2011032200, 'reservation');
    }

    if ($oldversion < 2011040601) {

        // Define key reservation_id (foreign) to be added to reservation_limit
        $table = new xmldb_table('reservation_limit');
        $key = new xmldb_key('reservation_id', XMLDB_KEY_FOREIGN, array('reservationid'), 'reservation', array('id'));

        // Launch add key reservation_id
        $dbman->add_key($table, $key);

        // reservation savepoint reached
        upgrade_mod_savepoint(true, 2011040601, 'reservation');
    }

    if ($oldversion < 2011061600) {

        // Define field mailed to be added to reservation
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('mailed', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0', 'showrequest');

        // Conditionally launch add field mailed
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

        // Define field parent to be added to reservation
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('parent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'showrequest');

        // Conditionally launch add field parent
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
        // Define field conditionreserved to be added to reservation
        $table = new xmldb_table('reservation');
        $field = new xmldb_field('completionreserved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mailed');

        // Conditionally launch add field parent
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
        set_config('reservation_download', NULL);

        upgrade_mod_savepoint(true, 2016051800, 'reservation');
    }
    if ($oldversion < 2016071100) {
        upgrade_mod_savepoint(true, 2016071100, 'reservation');
    }
}

?>

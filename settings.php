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
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings->add(new admin_setting_heading('reservation_tools', get_string('tools', 'reservation'), ''));
if (!empty($USER->sesskey)) {
    $url = new moodle_url('/mod/reservation/locations.php', array('sesskey' => $USER->sesskey));
    $settings->add(new \mod_reservation\admin_setting_link('reservationlocations',
            get_string('locations', 'reservation'), get_string('configlocations', 'reservation'),
            get_string('locations', 'reservation'), $url, ''));
}
$settings->add(new \mod_reservation\admin_setting_link('reservationupload',
        get_string('upload', 'reservation'), get_string('configupload', 'reservation'),
        get_string('upload', 'reservation'), new moodle_url('/mod/reservation/upload.php'), ''));

$settings->add(new admin_setting_heading('reservation_settings', get_string('reservation_settings', 'reservation'), ''));

$settings->add(new admin_setting_configtext('reservation_max_requests', get_string('maxrequest', 'reservation'),
        get_string('configmaxrequests', 'reservation'), '100'), PARAM_INT, 5);

unset($choices);
$choices = array();
$choices['course'] = get_string('course');
$choices['site'] = get_string('site');
$settings->add(new admin_setting_configselect('reservation_connect_to', get_string('connectto', 'reservation'),
        get_string('configconnectto', 'reservation'), 'course', $choices));

$settings->add(new admin_setting_configcheckbox('reservation_check_clashes', get_string('checkclashes', 'reservation'),
        get_string('configcheckclashes', 'reservation'), '0'));

unset($choices);
$choices = array();
$choices['300'] = get_string('duration5min', 'reservation');
$choices['600'] = get_string('duration10min', 'reservation');
$choices['900'] = get_string('duration15min', 'reservation');
$choices['1200'] = get_string('duration20min', 'reservation');
$choices['1800'] = get_string('duration30min', 'reservation');
$choices['2700'] = get_string('duration45min', 'reservation');
$choices['3600'] = get_string('duration60min', 'reservation');
$choices['5400'] = get_string('duration90min', 'reservation');
$choices['7200'] = get_string('duration2h', 'reservation');
$choices['10800'] = get_string('duration3h', 'reservation');
$choices['14400'] = get_string('duration4h', 'reservation');
$choices['18000'] = get_string('duration5h', 'reservation');
$choices['21600'] = get_string('duration6h', 'reservation');
$choices['25200'] = get_string('duration7h', 'reservation');
$choices['28800'] = get_string('duration8h', 'reservation');
$choices['32400'] = get_string('duration9h', 'reservation');
$choices['36000'] = get_string('duration10h', 'reservation');
$choices['39600'] = get_string('duration11h', 'reservation');
$choices['43200'] = get_string('duration12h', 'reservation');
$settings->add(new admin_setting_configselect('reservation_min_duration', get_string('minduration', 'reservation'),
        get_string('configminduration', 'reservation'), '3600', $choices));

$settings->add(new admin_setting_configtext('reservation_max_overbook', get_string('maxoverbook', 'reservation'),
        get_string('configmaxoverbook', 'reservation'), '100%'), PARAM_INT, 5);

$settings->add(new admin_setting_configtext('reservation_overbook_step', get_string('overbookstep', 'reservation'),
        get_string('configoverbookstep', 'reservation'), '5'), PARAM_INT, 5);

$settings->add(new admin_setting_configtext('reservation_sublimits', get_string('sublimits', 'reservation'),
        get_string('configsublimits', 'reservation'), '5'), PARAM_INT, 5);

$settings->add(new admin_setting_heading('reservation_listing', get_string('reservation_listing', 'reservation'), ''));
unset($choices);
$choices = array();
$choices['section'] = get_string('bysection', 'reservation');
$choices['date'] = get_string('bydate', 'reservation');
$choices['name'] = get_string('byname', 'reservation');
$settings->add(new admin_setting_configselect('reservation_list_sort', get_string('sortby', 'reservation'),
        get_string('configsortby', 'reservation'), 'section', $choices));

$settings->add(new admin_setting_configcheckbox('reservation_publiclists', get_string('publiclists', 'reservation'),
        get_string('configpubliclists', 'reservation'), '0'));

unset($choices);
$choices = array();
$choices['-1'] = get_string('never');
$choices['0'] = get_string('atstart', 'reservation');
$choices['360'] = get_string('after5min', 'reservation');
$choices['720'] = get_string('after10min', 'reservation');
$choices['1800'] = get_string('after30min', 'reservation');
$choices['3600'] = get_string('after1h', 'reservation');
$choices['7200'] = get_string('after2h', 'reservation');
$choices['14400'] = get_string('after4h', 'reservation');
$choices['21600'] = get_string('after6h', 'reservation');
$choices['43200'] = get_string('after12h', 'reservation');
$choices['86400'] = get_string('after1d', 'reservation');
$choices['172800'] = get_string('after2d', 'reservation');
$choices['604800'] = get_string('after1w', 'reservation');
$choices['1209600'] = get_string('after2w', 'reservation');
$choices['1814400'] = get_string('after3w', 'reservation');
$choices['2419200'] = get_string('after4w', 'reservation');
$settings->add(new admin_setting_configselect('reservation_deltatime', get_string('autohide', 'reservation'),
        get_string('configautohide', 'reservation'), '-1', $choices));

$settings->add(new admin_setting_heading('reservation_view', get_string('reservation_view', 'reservation'), ''));
unset($choices);
$choices = array();
$choices['email'] = get_string('email');
$choices['city'] = get_string('city');
$choices['country'] = get_string('state');
$choices['idnumber'] = get_string('idnumber');
$choices['institution'] = get_string('institution');
$choices['department'] = get_string('department');
$choices['phone'] = get_string('phone');
$choices['phone2'] = get_string('phone2');
$choices['address'] = get_string('address');

$customfields = $DB->get_records('user_info_field');
if (!empty($customfields)) {
    foreach ($customfields as $customfield) {
        $choices[$customfield->shortname] = $customfield->name;
    }
}
$defaultfields = array();
$settings->add(new admin_setting_configmulticheckbox('reservation_fields', get_string('fields', 'reservation'),
        get_string('configfields', 'reservation'), $defaultfields, $choices));

unset($choices);
$choices = array();
$choices['course'] = get_string('course');
$choices['site'] = get_string('site');
$settings->add(new admin_setting_configselect('reservation_manual_users', get_string('manualusers', 'reservation'),
        get_string('configmanualusers', 'reservation'), 'course', $choices));

$settings->add(new admin_setting_heading('reservation_other', get_string('reservation_other', 'reservation'), ''));
unset($choices);
$choices = array();
$choices['teachers'] = get_string('notifyteachers', 'reservation');
$choices['students'] = get_string('notifystudents', 'reservation');
$choices['grades'] = get_string('notifygrades', 'reservation');
$defaultnotifies = 'teachers,students,grades';
$settings->add(new admin_setting_configmulticheckbox('reservation_notifies', get_string('notifies', 'reservation'),
        get_string('confignotifies', 'reservation'), $defaultnotifies, $choices));

unset($choices);
$choices = array();
$choices['reservation'] = get_string('reservationevent', 'reservation');
$choices['event'] = get_string('eventevent', 'reservation');
$choices['userevent'] = get_string('userevent', 'reservation');
$defaultevents = 'reservation,event';
$settings->add(new admin_setting_configmulticheckbox('reservation_events', get_string('events', 'reservation'),
        get_string('configevents', 'reservation'), $defaultevents, $choices));


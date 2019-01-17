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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package mod_reservation
 * @copyright 2011 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/reservation/locallib.php');

/**
 * Reservation settings form
 *
 * @package mod_reservation
 * @copyright 2011 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_reservation_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $CFG, $COURSE, $DB;
        $mform    =& $this->_form;

        $reservationconfig = get_config('reservation');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description.
        $this->standard_intro_elements(get_string('description', 'reservation'));

        // Event Settings.
        $mform->addElement('header', 'eventsettings', get_string('eventsettings', 'reservation'));
        $mform->setExpanded('eventsettings');

        $context = context_course::instance($COURSE->id);

        $capability = 'moodle/course:viewhiddenactivities';
        if (!empty($this->_cm)) {
            $context = context_module::instance($this->_cm->id);
            // Fix for tutors.
            $capability = 'mod/reservation:addinstance';
        }
        if ($teacherusers = get_users_by_capability($context, $capability, 'u.*', 'u.lastname ASC')) {
            $availableteachers = array();
            foreach ($teacherusers as $teacheruser) {
                $availableteachers[$teacheruser->id] = fullname($teacheruser);
            }
            $teacherselect = &$mform->addElement('select', 'teachers', get_string('teachers'), $availableteachers);
            $teacherselect->setMultiple(true);
        } else {
            $mform->addElement('static', 'noteachers', get_string('teachers'), get_string('noteachers', 'reservation'));
            $mform->addElement('hidden', 'teachers');
            $mform->setType('teachers', PARAM_TEXT);
        }

        $locationgrp = array();
        $locationsize = 60;
        if ($locations = $DB->get_records_menu('reservation_location')) {
            $associativelocations = array();
            foreach ($locations as $location) {
                $associativelocations[$location] = $location;
            }
            natsort($associativelocations);
            $locations = array_merge(array(0 => get_string('otherlocation', 'reservation')), $associativelocations);
            $locationgrp[] = &$mform->createElement('select', 'location', null, $locations,
                    'onchange="getElementById(\'id_locationtext\').value=\'\'"');
            $locationsize = 40;
        }
        $locationgrp[] = &$mform->createElement('text', 'locationtext', 'size="'.$locationsize.'"');
        $mform->setType('locationtext', PARAM_TEXT);
        $mform->disabledIf('locationtext', 'location', 'ne', '0');
        $mform->addGroup($locationgrp, 'locationgrp', get_string('location', 'reservation'), ' ', false);

        $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'reservation'));
        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'reservation'), array('optional' => true));

        if (!empty($reservationconfig->checkclashes)) {
            $reportdiv = '<div id="collision_report" class="collision"></div>'."\n";
            $collisiondiv = '<div id="collision_list" class="collision"></div>'."\n";

            $strclashesfound = get_string('clashesfound', 'reservation');
            $strnoclashes = get_string('noclashes', 'reservation');

            $script = '<script type="text/javascript">
function checkClashes() {

    var reportdiv = document.getElementById("collision_report");
    var clashesdiv = document.getElementById("collision_list");

    var timestart_day_element = document.getElementsByName("timestart[day]")[0];
    var timestart_day = timestart_day_element.options[timestart_day_element.selectedIndex].value;
    var timestart_month_element = document.getElementsByName("timestart[month]")[0];
    var timestart_month = timestart_month_element.options[timestart_month_element.selectedIndex].value;
    var timestart_year_element = document.getElementsByName("timestart[year]")[0];
    var timestart_year = timestart_year_element.options[timestart_year_element.selectedIndex].value;
    var timestart_hour_element = document.getElementsByName("timestart[hour]")[0];
    var timestart_hour = timestart_hour_element.options[timestart_hour_element.selectedIndex].value;
    var timestart_minute_element = document.getElementsByName("timestart[minute]")[0];
    var timestart_minute = timestart_minute_element.options[timestart_minute_element.selectedIndex].value;
    var timestart = "&timestart="+timestart_year+"-"+timestart_month+"-"+timestart_day+"-"+timestart_hour+"-"+timestart_minute;

    var timeend = "";
    if (document.getElementsByName("timeend[enabled]")[0].checked == true) {
        var timeend_day_element = document.getElementsByName("timeend[day]")[0];
        var timeend_day = timeend_day_element.options[timeend_day_element.selectedIndex].value;
        var timeend_month_element = document.getElementsByName("timeend[month]")[0];
        var timeend_month = timeend_month_element.options[timeend_month_element.selectedIndex].value;
        var timeend_year_element = document.getElementsByName("timeend[year]")[0];
        var timeend_year = timeend_year_element.options[timeend_year_element.selectedIndex].value;
        var timeend_hour_element = document.getElementsByName("timeend[hour]")[0];
        var timeend_hour = timeend_hour_element.options[timeend_hour_element.selectedIndex].value;
        var timeend_minute_element = document.getElementsByName("timeend[minute]")[0];
        var timeend_minute = timeend_minute_element.options[timeend_minute_element.selectedIndex].value;
        timeend = "&timeend="+timeend_year+"-"+timeend_month+"-"+timeend_day+"-"+timeend_hour+"-"+timeend_minute;
    }

    var reservationid = "";
    if (document.getElementsByName("instance")[0].value != "") {
        reservationid = "&reservation="+document.getElementsByName("instance")[0].value;
    }

    var location = "";
    var location_element = document.getElementsByName("location")[0];
    if ((typeof location_element != "undefined") && (location_element.selectedIndex != 0)) {
        location = "&location="+location_element.options[location_element.selectedIndex].value;
    } else {
       if (document.getElementsByName("locationtext")[0].value != "") {
           location = "&location="+document.getElementsByName("locationtext")[0].value;
       }
    }

    var sUrl = "'.$CFG->wwwroot.'/mod/reservation/tool/clashes.php?id='.$COURSE->id.'"+timestart+timeend+location+reservationid;

    YUI().use("io-base", "node",
        function(Y) {
            var handleSuccess = function(id, o){
                if (o.responseText !== undefined) {
                    if (o.responseText !== "") {
                        reportdiv.innerHTML = \'<span class="clashesfound">'.$strclashesfound.'</span>\';
                        clashesdiv.innerHTML = o.responseText;
                        clashesdiv.style.display = "block";
                    } else {
                        reportdiv.innerHTML = \'<span class="timeavailable">'.$strnoclashes.'</span>\';
                        clashesdiv.style.display = "none";
                    }
                }
            }

            var handleFailure = function(id, o){
                if (o.responseText !== undefined) {
                    return false;
                }
            }

            var cfg = {
                on: {
                    success: handleSuccess,
                    failure: handleFailure
                }
            };

            Y.io(sUrl, cfg);
        }
    );
}

</script>
';

            $mform->addElement('static', 'collision', '',
                    '<input type="button" id="checkclashes" onclick="checkClashes()" value="'.
                    get_string('checkclashes', 'reservation').'" class="btn btn-primary" />'.$reportdiv.$collisiondiv.$script);
        }

        $mform->addElement('modgrade', 'maxgrade', get_string('grade'));
        $mform->setDefault('maxgrade', 0);

        // Reservation Settings.
        $mform->addElement('header', 'reservationsettings', get_string('reservationsettings', 'reservation'));
        $mform->setExpanded('reservationsettings');

        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'reservation'), array('optional' => true));
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'reservation'));

        $mform->addElement('selectyesno', 'note', get_string('enablenote', 'reservation'));

        if (!isset($reservationconfig->maxrequests)) {
            $reservationconfig->maxrequests = '100';
        }
        $values = array(0 => get_string('nolimit', 'reservation'));
        for ($i = 1; $i <= $reservationconfig->maxrequests; $i++) {
             $values[$i] = "$i";
        }
        $mform->addElement('select', 'maxrequest', get_string('maxrequest', 'reservation'), $values);

        $choices = array();
        $choices[0] = get_string('numberafterclose', 'reservation');
        $choices[1] = get_string('listafterclose', 'reservation');
        $choices[2] = get_string('listalways', 'reservation');
        $choices[3] = get_string('numberalways', 'reservation');
        $choices[4] = get_string('none', 'reservation');
        $mform->addElement('select', 'showrequest', get_string('showuserrequest', 'reservation'), $choices);

        $reservationid = $this->_instance;
        if ($reservations = reservation_get_parentable($reservationid)) {
            if (isset($reservationconfig->connectto) && ($reservationconfig->connectto == 'site')) {
                require_once($CFG->libdir.'/coursecatlib.php');
                $displaylist = coursecat::make_categories_list();
            }
            $values = array(0 => get_string('noparent', 'reservation'));
            foreach ($reservations as $reservation) {
                $value = $reservation->coursename.': '.$reservation->name;
                if (isset($reservationconfig->connectto) && ($reservationconfig->connectto == 'site')) {
                    $value = $displaylist[$reservation->category] .'/'. $value;
                }
                $values[$reservation->id] = $value;
            }

            $attrs = array();
            if ($reservation = $DB->get_record('reservation', array('id' => $reservationid))) {
                if (reservation_get_requests($reservation)) {
                    // Set read only if exists requests to avoid multiple request on connected reservations.
                    $attrs['readonly'] = 'readonly';
                }
            }

            $mform->addElement('select', 'parent', get_string('parent', 'reservation'), $values, $attrs);
            $mform->setAdvanced('parent');
        }

        if (!isset($reservationconfig->maxoverbook)) {
            $reservationconfig->maxoverbook = 100;
        } else {
            $reservationconfig->maxoverbook = intval($reservationconfig->maxoverbook);
        }
        if (!isset($reservationconfig->overbookstep)) {
            $reservationconfig->overbookstep = 5;
        }

        $values = array(0 => get_string('nooverbook', 'reservation'));
        $step = $reservationconfig->overbookstep;
        for ($i = $step; $i <= $reservationconfig->maxoverbook; $i += $step) {
             $values[$i] = "$i%";
        }
        $mform->addElement('select', 'overbook', get_string('overbook', 'reservation'), $values);
        $mform->disabledIf('overbook', 'maxrequest', 'eq', '0');
        $mform->setAdvanced('overbook');

        if (!empty($reservationconfig->sublimits)) {

            $matchdiv = '<div id="matchvalue_list" class="matchlist"></div>'."\n";

            $script = '<script type="text/javascript">
    function selectMatchValue(matchvalueid, fieldid) {

        var div = document.getElementById("matchvalue_list");

        var matchvalue = document.getElementById(matchvalueid);

        var field = document.getElementById(fieldid);
        var fieldvalue = field.options[field.selectedIndex].value;
        var sUrl = "'.$CFG->wwwroot.'/mod/reservation/tool/matchlist.php?id='.$COURSE->id
                .'&field="+fieldvalue+"&match="+matchvalueid;

        YUI().use("io-base", "node",
            function(Y) {
                var handleSuccess = function(id, o){
                    if (o.responseText !== undefined) {
                        div.innerHTML = o.responseText;
                        div.style.display = "block";
                    }
                }

                var handleFailure = function(id, o){
                    if (o.responseText !== undefined) {
                        return false;;
                    }
                }

                var cfg = {
                    on: {
                        success: handleSuccess,
                        failure: handleFailure
                    }
                };

                Y.io(sUrl, cfg);
            }
        );

        //matchvalue.value = fieldid;

    }
</script>
';

            $mform->addElement('static', 'matchvalues', get_string('sublimitrules', 'reservation'), $matchdiv.$script);
            $mform->setAdvanced('matchvalues');

            $sublimitgrps = array();
            for ($i = 1; $i <= $reservationconfig->sublimits; $i++) {
                $sublimitgrps[$i] = array();

                $values = array();
                for ($j = 0; $j <= $reservationconfig->maxrequests; $j++) {
                     $values[$j] = "$j";
                }
                $sublimitgrps[$i][] = &$mform->createElement('select', 'requestlimit_'.$i, null, $values);

                $sublimitgrps[$i][] = &$mform->createElement('static', 'with_'.$i, null, get_string('with', 'reservation'));

                unset($fields);
                $fields = array('-' => get_string('choose'));
                $fields['group'] = get_string('group');
                $fields['city'] = get_string('city');
                $fields['institution'] = get_string('institution');
                $fields['department'] = get_string('department');
                $fields['address'] = get_string('address');

                $customfields = $DB->get_records('user_info_field');
                if (!empty($customfields)) {
                    foreach ($customfields as $customfield) {
                        $fields[$customfield->shortname] = $customfield->name;
                    }
                }
                $onchange = 'onchange="getElementById(\'id_matchvalue_'.$i.'\').value=\'\'"';
                $sublimitgrps[$i][] = &$mform->createElement('select', 'field_'.$i, null, $fields, $onchange);

                unset($operators);
                $operators = array();
                $operators[] = get_string('equal', 'reservation');
                $operators[] = get_string('notequal', 'reservation');
                $sublimitgrps[$i][] = &$mform->createElement('select', 'operator_'.$i, null, $operators);

                $onfocus = 'onfocus="selectMatchValue(\'id_matchvalue_'.$i.'\',\'id_field_'.$i.'\')"';
                $sublimitgrps[$i][] = &$mform->createElement('text', 'matchvalue_'.$i, null, $onfocus);
                $mform->setType('matchvalue_'.$i, PARAM_TEXT);
                $mform->disabledIf('matchvalue_'.$i, 'field_'.$i, 'eq', '-');

                $mform->addGroup($sublimitgrps[$i], 'sublimitgrp_'.$i, get_string('sublimit', 'reservation', $i), ' ', false);
                $mform->setAdvanced('sublimitgrp_'.$i);
                /*
                if ($i > 1) {
                    $prev = $i - 1;
                    $mform->disabledIf('sublimitgrp_'.$i, 'field_'.$prev, 'eq', '-');
                }
                */
            }
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        if (!empty($defaultvalues['instance'])) {
            if (!empty($defaultvalues['teachers'])) {
                $teachers = explode(',', $defaultvalues['teachers']);
                $defaultvalues['teachers'] = $teachers;
            }
            if (!$locations = $DB->get_records_menu('reservation_location')) {
                $locations = array();
            }
            if (! in_array($defaultvalues['location'], $locations)) {
                $defaultvalues['locationtext'] = $defaultvalues['location'];
                $defaultvalues['location'] = null;
            } else {
                $defaultvalues['locationtext'] = '';
            }
            $queryparameters = array('reservationid' => $defaultvalues['instance']);
            if ($reservationlimits = $DB->get_records('reservation_limit', $queryparameters, 'id')) {
                $i = 1;
                foreach ($reservationlimits as $reservationlimit) {
                    $defaultvalues['field_'.$i] = $reservationlimit->field;
                    $defaultvalues['operator_'.$i] = $reservationlimit->operator;
                    $defaultvalues['matchvalue_'.$i] = $reservationlimit->matchvalue;
                    $defaultvalues['requestlimit_'.$i] = $reservationlimit->requestlimit;
                    $i++;
                }
                $sublimits = get_config('reservation', 'sublimits');
                if ($i > $sublimits) {
                    set_config('sublimits', $i, 'reservation');
                }
            }
        }
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (!$autocompletion || empty($data->completionreserved)) {
                $data->completionreserved = 0;
            }
        }
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['timeend']) && !empty($data['timeend']) && ($data['timeend'] < $data['timestart'])) {
            $errors['timeend'] = get_string('err_timeendlower', 'reservation');
        }
        if (isset($data['timeopen']) && !empty($data['timeopen']) && ($data['timeclose'] < $data['timeopen'])) {
            $errors['timeopen'] = get_string('err_timeopengreater', 'reservation');
        }
        if (($data['maxrequest'] > 0) && !empty($reservationconfig->sublimits)) {
            $sublimitsum = 0;
            for ($i = 1; ($data['field_'.$i] != '-') && ($i <= $reservationconfig->sublimits); $i++) {
                $sublimitsum += $data['requestlimit_'.$i];
            }
            if ($sublimitsum > $data['maxrequest']) {
                $errors['maxrequest'] = get_string('err_sublimitsgreater', 'reservation');
            }
        }
        return $errors;
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox',
                           'completionreserved',
                           '',
                           get_string('completionreserved', 'reservation'));
        return array('completionreserved');
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionreserved']);
    }

}

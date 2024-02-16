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

        global $CFG, $COURSE, $DB, $PAGE;
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
            $capability = 'mod/reservation:viewrequest';
        }
        if ($teacherusers = get_enrolled_users($context, $capability, 0, 'u.*', 'u.lastname ASC')) {
            $availableteachers = [];
            foreach ($teacherusers as $teacheruser) {
                if (! has_capability('mod/reservation:reserve', $context, $teacheruser)) {
                    $availableteachers[$teacheruser->id] = fullname($teacheruser);
                }
            }
            $teacherselect = &$mform->addElement('select', 'teachers', get_string('teachers'), $availableteachers);
            $teacherselect->setMultiple(true);
        } else {
            $mform->addElement('static', 'noteachers', get_string('teachers'), get_string('noteachers', 'reservation'));
            $mform->addElement('hidden', 'teachers');
            $mform->setType('teachers', PARAM_TEXT);
        }

        $locationgrp = [];
        $locationsize = 60;
        if ($locations = $DB->get_records_menu('reservation_location')) {
            $associativelocations = [];
            foreach ($locations as $location) {
                $associativelocations[$location] = $location;
            }
            natsort($associativelocations);
            $locations = array_merge([0 => get_string('otherlocation', 'reservation')), $associativelocations];
            $onchange = 'onchange="getElementById(\'id_locationtext\').value=\'\'"';
            $locationgrp[] = &$mform->createElement('select', 'location', null, $locations, $onchange);
            $locationsize = 40;
        }
        $locationgrp[] = &$mform->createElement('text', 'locationtext', 'size="'.$locationsize.'"');
        $mform->setType('locationtext', PARAM_TEXT);
        $mform->disabledIf('locationtext', 'location', 'ne', '0');
        $mform->addGroup($locationgrp, 'locationgrp', get_string('location', 'reservation'), ' ', false);

        $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'reservation'));
        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'reservation'), ['optional' => true]);

        if (!empty($reservationconfig->check_clashes)) {
            $mform->addElement('static', 'collision', '',
                    '<button type="button" id="checkclashes" class="btn btn-primary">' .
                    get_string('checkclashes', 'reservation') . '</button>');
        }

        // Reservation Settings.
        $mform->addElement('header', 'reservationsettings', get_string('reservationsettings', 'reservation'));
        $mform->setExpanded('reservationsettings');

        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'reservation'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'reservation'));

        $choices = [];
        $choices[0] = get_string('no');
        $choices[1] = get_string('optional', 'reservation');
        $choices[2] = get_string('required', 'reservation');
        $mform->addElement('select', 'note', get_string('enablenote', 'reservation'), $choices);

        if (!isset($reservationconfig->max_requests)) {
            $reservationconfig->max_requests = '100';
        }
        $values = [0 => get_string('nolimit', 'reservation')];
        for ($i = 1; $i <= $reservationconfig->max_requests; $i++) {
             $values[$i] = "$i";
        }
        $mform->addElement('select', 'maxrequest', get_string('maxrequest', 'reservation'), $values);

        $choices = [];
        $choices[0] = get_string('numberafterclose', 'reservation');
        $choices[1] = get_string('listafterclose', 'reservation');
        $choices[2] = get_string('listalways', 'reservation');
        $choices[3] = get_string('numberalways', 'reservation');
        $choices[4] = get_string('none', 'reservation');
        $mform->addElement('select', 'showrequest', get_string('showuserrequest', 'reservation'), $choices);

        $reservationid = $this->_instance;
        if ($reservations = reservation_get_parentable($reservationid)) {
            if (isset($reservationconfig->connect_to) && ($reservationconfig->connect_to == 'site')) {
                $displaylist = [];
                if (class_exists('core_course_category')) {
                    $displaylist = core_course_category::make_categories_list();
                } else {
                    require_once($CFG->libdir. '/coursecatlib.php');
                    $displaylist = coursecat::make_categories_list();
                }
            }
            $values = [0 => get_string('noparent', 'reservation')];
            foreach ($reservations as $reservation) {
                $value = $reservation->coursename.': '.$reservation->name;
                if (isset($reservationconfig->connect_to) && ($reservationconfig->connect_to == 'site')) {
                    $value = $displaylist[$reservation->category] .'/'. $value;
                }
                $values[$reservation->id] = $value;
            }

            $attrs = [];
            if (!empty($reservationid)) {
                if ($reservation = $DB->get_record('reservation', ['id' => $reservationid])) {
                    if (reservation_get_requests($reservation)) {
                        // Set read only if exists requests to avoid multiple request on connected reservations.
                        $attrs['readonly'] = 'readonly';
                    }
                }
            }

            $mform->addElement('select', 'parent', get_string('parent', 'reservation'), $values, $attrs);
            $mform->setAdvanced('parent');
        }

        if (!isset($reservationconfig->max_overbook)) {
            $reservationconfig->max_overbook = 100;
        } else {
            $reservationconfig->max_overbook = intval($reservationconfig->max_overbook);
        }
        if (!isset($reservationconfig->overbook_step)) {
            $reservationconfig->overbook_step = 5;
        }

        $values = [0 => get_string('nooverbook', 'reservation')];
        $step = $reservationconfig->overbook_step;
        for ($i = $step; $i <= $reservationconfig->max_overbook; $i += $step) {
             $values[$i] = "$i%";
        }
        $mform->addElement('select', 'overbook', get_string('overbook', 'reservation'), $values);
        $mform->disabledIf('overbook', 'maxrequest', 'eq', '0');
        $mform->setAdvanced('overbook');

        if (!empty($reservationconfig->sublimits)) {
            $sublimitgrps = [];
            for ($i = 1; $i <= $reservationconfig->sublimits; $i++) {
                $sublimitgrps[$i] = [];

                $values = [];
                for ($j = 0; $j <= $reservationconfig->max_requests; $j++) {
                     $values[$j] = "$j";
                }
                $sublimitgrps[$i][] = &$mform->createElement('select', 'requestlimit_'.$i, null, $values);

                $sublimitgrps[$i][] = &$mform->createElement('static', 'with_'.$i, null, get_string('with', 'reservation'));

                unset($fields);
                $fields = [];
                $fields['-'] = get_string('choose');
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
                $attributes = 'class="field"';
                $sublimitgrps[$i][] = &$mform->createElement('select', 'field_'.$i, null, $fields, $attributes);

                unset($operators);
                $operators = [];
                $operators[] = get_string('equal', 'reservation');
                $operators[] = get_string('notequal', 'reservation');
                $sublimitgrps[$i][] = &$mform->createElement('select', 'operator_'.$i, null, $operators);

                $attributes = 'class="matchvalue"';
                $sublimitgrps[$i][] = &$mform->createElement('text', 'matchvalue_'.$i, null, $attributes);
                $mform->setType('matchvalue_'.$i, PARAM_TEXT);
                $mform->disabledIf('matchvalue_'.$i, 'field_'.$i, 'eq', '-');

                $mform->addGroup($sublimitgrps[$i], 'sublimitgrp_'.$i, get_string('sublimit', 'reservation', $i), ' ', false);
                $mform->setAdvanced('sublimitgrp_'.$i);
            }
        }

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        $options = new stdClass();
        $options->courseid = $COURSE->id;
        $PAGE->requires->js_call_amd('mod_reservation/reservationedit', 'init', [$options]);
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
                $locations = [];
            }
            if (! in_array($defaultvalues['location'], $locations)) {
                $defaultvalues['locationtext'] = $defaultvalues['location'];
                $defaultvalues['location'] = null;
            } else {
                $defaultvalues['locationtext'] = '';
            }
            $queryparameters = ['reservationid' => $defaultvalues['instance']];
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
        return ['completionreserved'];
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

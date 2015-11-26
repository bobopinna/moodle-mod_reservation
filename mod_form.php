<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once ($CFG->dirroot.'/mod/reservation/locallib.php');

class mod_reservation_mod_form extends moodleform_mod {

    function definition() {

        global $CFG, $COURSE, $DB, $PAGE;
        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

// Name
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

// Summary
        if ($CFG->branch < 29) {
            $this->add_intro_editor(true, get_string('description', 'reservation'));
        } else {
            $this->standard_intro_elements(get_string('description', 'reservation'));
        }
//-------------------------------------------------------------------------------
// Event Settings
        $mform->addElement('header', 'eventsettings', get_string('eventsettings', 'reservation'));
        $mform->setExpanded('eventsettings');

        $context = context_course::instance($COURSE->id);

        $capability = 'moodle/course:viewhiddenactivities';
        if (!empty($this->_cm)) {
            $context = context_module::instance($this->_cm->id);
// Fix for tutors
            $capability = 'mod/reservation:addinstance';
        }
        if ($teacherusers = get_users_by_capability($context, $capability, 'u.*', 'u.lastname ASC')) {
            $availableteachers = array();
            foreach ($teacherusers as $teacheruser) {
                $availableteachers[$teacheruser->id] =  fullname($teacheruser);
            }
            $teacherselect = &$mform->addElement('select', 'teachers', get_string('teachers'), $availableteachers);
            $teacherselect->setMultiple(true);
        } else {
            $mform->addElement('static', 'noteachers', get_string('teachers'), get_string('noteachers', 'reservation'));
            $mform->addElement('hidden','teachers');
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
            $locations = array_merge(array(0 => get_string('otherlocation','reservation')), $associativelocations);
            $locationgrp[] = &$mform->createElement('select','location', null, $locations, 'onchange="getElementById(\'id_locationtext\').value=\'\'"');
            $locationsize = 40;
        }
        $locationgrp[] = &$mform->createElement('text','locationtext','size="'.$locationsize.'"');
        $mform->setType('locationtext', PARAM_TEXT);
        $mform->disabledIf('locationtext', 'location', 'ne', '0');
        $mform->addGroup($locationgrp, 'locationgrp', get_string('location', 'reservation'), ' ', false);

        $mform->addElement('date_time_selector','timestart', get_string('timestart', 'reservation'));
        $mform->addElement('date_time_selector','timeend', get_string('timeend', 'reservation'), array('optional'=>true));

        if (!isset($CFG->reservation_check_clashes)) {
            $CFG->reservation_check_clashes = 0;
        }

        if ($CFG->reservation_check_clashes) {

            $reportdiv = '<div id="collision_report" class="collision"></div>'."\n";
            $collisiondiv = '<div id="collision_list" class="collision"></div>'."\n";

            $script = '<script type="text/javascript">

    function checkClashes() {

        var reportdiv = document.getElementById("collision_report");
        var clashesdiv = document.getElementById("collision_list");

        var  timestart_day = document.getElementsByName("timestart[day]")[0].options[document.getElementsByName("timestart[day]")[0].selectedIndex].value;
        var  timestart_month = document.getElementsByName("timestart[month]")[0].options[document.getElementsByName("timestart[month]")[0].selectedIndex].value;
        var  timestart_year = document.getElementsByName("timestart[year]")[0].options[document.getElementsByName("timestart[year]")[0].selectedIndex].value;
        var  timestart_hour = document.getElementsByName("timestart[hour]")[0].options[document.getElementsByName("timestart[hour]")[0].selectedIndex].value;
        var  timestart_minute = document.getElementsByName("timestart[minute]")[0].options[document.getElementsByName("timestart[minute]")[0].selectedIndex].value;
        var  timestart = "&timestart="+timestart_year+"-"+timestart_month+"-"+timestart_day+"-"+timestart_hour+"-"+timestart_minute;

        var timeend = "";
        if (document.getElementsByName("timeend[enabled]")[0].checked == true) {
            var  timeend_day = document.getElementsByName("timeend[day]")[0].options[document.getElementsByName("timeend[day]")[0].selectedIndex].value;
            var  timeend_month = document.getElementsByName("timeend[month]")[0].options[document.getElementsByName("timeend[month]")[0].selectedIndex].value;
            var  timeend_year = document.getElementsByName("timeend[year]")[0].options[document.getElementsByName("timeend[year]")[0].selectedIndex].value;
            var  timeend_hour = document.getElementsByName("timeend[hour]")[0].options[document.getElementsByName("timeend[hour]")[0].selectedIndex].value;
            var  timeend_minute = document.getElementsByName("timeend[minute]")[0].options[document.getElementsByName("timeend[minute]")[0].selectedIndex].value;
            timeend = "&timeend="+timeend_year+"-"+timeend_month+"-"+timeend_day+"-"+timeend_hour+"-"+timeend_minute;
        }
       
        var reservationid = "";
        if (document.getElementsByName("instance")[0].value != "") {
            reservationid = "&reservation="+document.getElementsByName("instance")[0].value;
        }

        var location = "";
        if ((typeof document.getElementsByName("location")[0] != "undefined") && (document.getElementsByName("location")[0].selectedIndex != 0)) {
            location = "&location="+document.getElementsByName("location")[0].options[document.getElementsByName("location")[0].selectedIndex].value;
        } else {
           if (document.getElementsByName("locationtext")[0].value != "") {
               location = "&location="+document.getElementsByName("locationtext")[0].value;
           }
        }

        var sUrl = "'.$CFG->wwwroot.'/mod/reservation/clashes.php?id='.$COURSE->id.'"+timestart+timeend+location+reservationid;

        YUI().use("io-base", "node",
            function(Y) {
                var handleSuccess = function(id, o){ 
                    if (o.responseText !== undefined) { 
                        if (o.responseText !== "") {
                            reportdiv.innerHTML = \'<span class="clashesfound">'.get_string('clashesfound', 'reservation').'</span>\';
                            clashesdiv.innerHTML = o.responseText;
                            clashesdiv.style.display = "block";
                        } else {
                            reportdiv.innerHTML = \'<span class="timeavailable">'.get_string('noclashes', 'reservation').'</span>\';
                            clashesdiv.style.display = "none";
                        }
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
    }

</script>
';

            $mform->addElement('static','collision', '', '<input type="button" id="checkclashes" onclick="checkClashes()" value="'.get_string('checkclashes','reservation').'" />'.$reportdiv.$collisiondiv.$script);
            //$mform->setAdvanced('collision');
       }

        $mform->addElement('modgrade','maxgrade', get_string('grade'));
        $mform->setDefault('maxgrade', 0);

//-------------------------------------------------------------------------------
// Reservation Settings
        $mform->addElement('header', 'reservationsettings', get_string('reservationsettings', 'reservation'));
        $mform->setExpanded('reservationsettings');

        $reservationid = $this->_instance;
        if ($reservations = reservation_get_parentable($reservationid)) {
            if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                require_once($CFG->libdir.'/coursecatlib.php');  
                $displaylist = coursecat::make_categories_list();
            }
            $values = array(0 => get_string('noparent', 'reservation'));
            foreach($reservations as $reservation) {
                $value = $reservation->coursename.': '.$reservation->name;
                if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                    $value = $displaylist[$reservation->category] .'/'. $value;
                }
                $values[$reservation->id] = $value;
            }

            $attrs = array();
            if ($reservation = $DB->get_record('reservation', array('id' => $reservationid))) {
                if (reservation_get_requests($reservation)) {
                    // Set read only if exists requests to avoid multiple request on connected reservations
                    $attrs['readonly'] = 'readonly';
                }
            }

            $mform->addElement('select','parent', get_string('parent', 'reservation'), $values, $attrs);
            $mform->setAdvanced('parent');
        } 

        $mform->addElement('date_time_selector','timeopen', get_string('timeopen', 'reservation'), array('optional'=>true));
        $mform->addElement('date_time_selector','timeclose', get_string('timeclose', 'reservation'));

        $mform->addElement('selectyesno','note', get_string('enablenote', 'reservation'));

        if (empty($CFG->reservation_max_requests)) {
            $CFG->reservation_max_requests = '100';
        }
        $values = array(0 => get_string('nolimit', 'reservation'));
        for ($i=1;$i<=$CFG->reservation_max_requests;$i++) {
             $values[$i] = "$i";
        }
        $mform->addElement('select','maxrequest', get_string('maxrequest', 'reservation'),$values);

        $mform->addElement('selectyesno','showrequest', get_string('showrequest', 'reservation'));

        if (empty($CFG->reservation_max_overbook)) {
            $CFG->reservation_max_overbook = 100;
        } else {
            $CFG->reservation_max_overbook = intval($CFG->reservation_max_overbook);
        }
        if (empty($CFG->reservation_overbook_step)) {
            $CFG->reservation_overbook_step = 5;
        }

        $values = array(0 => get_string('nooverbook', 'reservation'));
        for ($i=$CFG->reservation_overbook_step;$i<=$CFG->reservation_max_overbook;$i+=$CFG->reservation_overbook_step) {
             $values[$i] = "$i%";
        }
        $mform->addElement('select','overbook', get_string('overbook', 'reservation'),$values);
        $mform->setAdvanced('overbook');

        if (isset($CFG->reservation_sublimits) && !empty($CFG->reservation_sublimits)) {

            $matchdiv = '<div id="matchvalue_list" class="matchlist"></div>'."\n";

            $script = '<script type="text/javascript">
    function selectMatchValue(matchvalueid, fieldid) {

        var div = document.getElementById("matchvalue_list");

        var matchvalue = document.getElementById(matchvalueid);

        var field = document.getElementById(fieldid);
        var fieldvalue = field.options[field.selectedIndex].value;
        var sUrl = "'.$CFG->wwwroot.'/mod/reservation/matchlist.php?id='.$COURSE->id.'&field="+fieldvalue+"&match="+matchvalueid;

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

            $mform->addElement('static','matchvalues', get_string('sublimitrules', 'reservation'), $matchdiv.$script);
            $mform->setAdvanced('matchvalues');

            $sublimitgrps = array();
            for($i=1;$i<=$CFG->reservation_sublimits;$i++) {
                $sublimitgrps[$i] = array();

                $values = array();
                for ($j=0;$j<=$CFG->reservation_max_requests;$j++) {
                     $values[$j] = "$j";
                }
                $sublimitgrps[$i][] = &$mform->createElement('select','requestlimit_'.$i, null, $values);

                $sublimitgrps[$i][] = &$mform->createElement('static','with_'.$i, null, get_string('with', 'reservation'));

                unset($fields);
                $fields = array('-' => get_string('choose'));
                $fields['group'] = get_string('group');
                $fields['city'] = get_string('city');
                $fields['institution'] = get_string('institution');
                $fields['department'] = get_string('department');
                $fields['address'] = get_string('address');
                
                $customfields = $DB->get_records('user_info_field');
                if (!empty($customfields)) {
                   foreach($customfields as $customfield) {
                       $fields[$customfield->shortname] = $customfield->name;
                   }
                }
                $sublimitgrps[$i][] = &$mform->createElement('select','field_'.$i, null, $fields, 'onchange="getElementById(\'id_matchvalue_'.$i.'\').value=\'\'"');
        
                unset($operators);
                $operators = array();
                $operators[] = get_string('equal', 'reservation');
                $operators[] = get_string('notequal', 'reservation');
                $sublimitgrps[$i][] = &$mform->createElement('select','operator_'.$i, null, $operators);


                $sublimitgrps[$i][] = &$mform->createElement('text','matchvalue_'.$i, null,'onfocus="selectMatchValue(\'id_matchvalue_'.$i.'\',\'id_field_'.$i.'\')"');
                $mform->setType('matchvalue_'.$i, PARAM_TEXT);
                $mform->disabledIf('matchvalue_'.$i, 'field_'.$i, 'eq', '-');

                $mform->addGroup($sublimitgrps[$i], 'sublimitgrp_'.$i, get_string('sublimit', 'reservation', $i), ' ', false);
                $mform->setAdvanced('sublimitgrp_'.$i);
                if ($i > 1) {
                    $prev = $i-1;
                    $mform->disabledIf('sublimitgrp_'.$i, 'field_'.$prev, 'eq', '-');
                }
            }
        }

//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values) {
        global $CFG, $DB;

        if (!empty($default_values['instance'])) {
            if (!empty($default_values['teachers'])) {
                $teachers = explode(',',$default_values['teachers']);
                $default_values['teachers'] = $teachers;
            }
            if (!$locations = $DB->get_records_menu('reservation_location')) {
                $locations = array();
            }
            if (! in_array($default_values['location'], $locations)) {
                $default_values['locationtext'] = $default_values['location'];
                $default_values['location'] = null;
            } else {
                $default_values['locationtext'] = '';
            }
            if ($reservation_limits = $DB->get_records('reservation_limit', array('reservationid' => $default_values['instance']), 'id')) {
                $i = 1;
                foreach($reservation_limits as $reservation_limit) {
                    $default_values['field_'.$i] = $reservation_limit->field;
                    $default_values['operator_'.$i] = $reservation_limit->operator;
                    $default_values['matchvalue_'.$i] = $reservation_limit->matchvalue;
                    $default_values['requestlimit_'.$i] = $reservation_limit->requestlimit;
                    $i++;
                }
                if ($i > $CFG->reservation_sublimits) {
                    $CFG->reservation_sublimits = $i;
                }
            }
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            if (!empty($data->completionunlocked)) {
                // Turn off completion settings if the checkboxes aren't ticked
                $autocompletion = !empty($data->completion) &&
                    $data->completion == COMPLETION_TRACKING_AUTOMATIC;
                if (!$autocompletion || empty($data->completionreserved)) {
                    $data->completionreserved=0;
                }
            }
        }

        return $data;
    }

    function validation($data, $files) {
        global $CFG;

        $errors = parent::validation($data, $files);

        if (isset($data['timeend']) && !empty($data['timeend']) && ($data['timeend'] < $data['timestart'])) {
           $errors['timeend'] = get_string('err_timeendlower', 'reservation');
        }
        if (isset($data['timeopen']) && !empty($data['timeopen']) && ($data['timeclose'] < $data['timeopen'])) {
           $errors['timeopen'] = get_string('err_timeopengreater', 'reservation');
        }
        if (($data['maxrequest'] > 0) && isset($CFG->reservation_sublimits) && !empty($CFG->reservation_sublimits)) {
            $sublimitsum = 0;
            for ($i=1;($data['field_'.$i] != '-') && ($i<=$CFG->reservation_sublimits);$i++) {
                $sublimitsum += $data['requestlimit_'.$i];
            }
            if ($sublimitsum > $data['maxrequest']) {
                $errors['maxrequest'] = get_string('err_sublimitsgreater', 'reservation');
            }
        }
        return $errors;
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox',
                           'completionreserved',
                           '',
                           get_string('completionreserved', 'reservation'));
        return array('completionreserved');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionreserved']);
    }

}

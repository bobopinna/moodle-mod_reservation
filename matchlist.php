<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('locallib.php');

    $id = required_param('id', PARAM_INT);                     // Course ID
    $fieldname = required_param('field', PARAM_ALPHANUM);      // Field name
    $matchvalue = required_param('match', PARAM_ALPHANUMEXT);  // Return field id

    if ($id) {
        if (! $course = $DB->get_record('course', array('id' => $id))) {
            error('Course ID is incorrect');
        }
    }

    require_course_login($course);

    $coursecontext = context_course::instance($course->id);

    require_capability('moodle/course:manageactivities', $coursecontext);

    $values = array();

    $customfields = reservation_get_profilefields();

    // Get the list of used values for requested field
    if (isset($customfields[$fieldname])) {
        // Retrieve custom field values
        if ($datas = $DB->get_records('user_info_data',array('fieldid' => $customfields[$fieldname]->id),'data ASC', 'DISTINCT data')) {
            foreach ($datas as $data) {
                if (!empty($data->data)) {
                    $value = new stdClass();
                    $value->$fieldname = $data->data;
                    $values[] = $value;
                }
            }
        }
    } else if ($fieldname == 'group') {
        // Get groups list
        $groups = groups_get_all_groups($id);
        if (!empty($groups)) {
            foreach($groups as $group) {
                $value = new stdClass();
                $value->group = $group->name;
                $values[] = $value;
            }
        }
    } else {
        // One of standard fields
        if (in_array($fieldname,array('city','institution','department','address'))) {
            $values = $DB->get_records_select('user', 'deleted=0 AND '.$fieldname.'<>""', null, $fieldname.' ASC','DISTINCT '.$fieldname);
        }
    }

   echo '<div style="position: relative; float:right; text-align: right;"><a href="javascript:void(0)" onclick="document.getElementById(\'matchvalue_list\').style.display=\'none\';">'.get_string('close', 'reservation').'&#9746;</a></div>'."\n";
    // Generate inner div code
    if (!empty($values)) {
        echo '<strong>'.get_string('selectvalue', 'reservation').'</strong><br />'."\n";
        foreach ($values as $value) {
            if (!empty($value->$fieldname)) {
                echo '<a href="javascript:void(0)" onclick="document.getElementById(\''.$matchvalue.'\').value=\''.addslashes(htmlentities($value->$fieldname)).'\'; document.getElementById(\'matchvalue_list\').style.display=\'none\';">'.$value->$fieldname.'</a><br />'."\n";
            }
        }
    } else {
        echo '<br /><strong>'.get_string('novalues', 'reservation').'</strong><br />'."\n";
    }


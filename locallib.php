<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Gets a full reservation record
 *
 * @global object
 * @param int $reservationid
 * @return object|bool The reservation or false
 */
function reservation_get_reservation($reservationid) {
    global $DB;

    if ($reservation = $DB->get_record('reservation', array('id' => $reservationid))) {
        if ($sublimits = $DB->get_records('reservation_limit', array('reservationid' => $reservationid), 'id')) {
            $i = 1;
            foreach ($sublimits as $sublimit) {
                $reservation->sublimits[$i] = new stdClass();
                $reservation->sublimits[$i]->field = $sublimit->field;
                $reservation->sublimits[$i]->operator = $sublimit->operator;
                $reservation->sublimits[$i]->matchvalue = $sublimit->matchvalue;
                $reservation->sublimits[$i]->limit = $sublimit->requestlimit;
            }
            return $reservation;
        }
    }
    return false;
}

/**
 *  Return list of reservations in specified course or with specified location
 *
 * @uses $DB
 * @param courseid integer course id
 * @param location string  location name
 * @return array|bool the reservations list or false
 */
function reservation_get_reservations_by_course($courseid, $location='') {
    global  $DB;

    $searchfields = array('courseid' => $courseid);

    $locationquery = ')';
    if (!empty($location)) {
        $locationquery = ' OR res.location = :location)';
        $searchfields['location'] = $location;
    }

    return $DB->get_records_sql('SELECT res.*, c.fullname as coursename
                                   FROM {reservation} res,
                                        {course} c
                                  WHERE res.course = c.id
                                        AND (res.course = :courseid'.$locationquery,
                                $searchfields);
}

/**
 * Return a menu list of parentable reservation
 *
 * @uses $DB, $CFG, $COURSE
 * @return array the reservations menu list or false
 */
function reservation_get_parentable($reservationid) {
    global $DB, $CFG, $COURSE;


    $searchfields = array();
    $additionalquery = '';

    if (!isset($CFG->reservation_connect_to) || ($CFG->reservation_connect_to == 'course')) {
        $searchfields['courseid'] = $COURSE->id;
        $additionalquery .= ' AND (res.course = :courseid)';
    }

    if (!empty($reservationid)) {
        $searchfields['reservationid'] = $reservationid;
        $additionalquery .= ' AND (res.id <> :reservationid)';
        if ($DB->get_records('reservation', array('parent' => $reservationid))) {
            return array();
        }
    }

    return $DB->get_records_sql('SELECT res.id, c.fullname as coursename, c.category as category, res.name as name
                FROM {reservation} res,
                     {course} c
               WHERE res.course = c.id
                     AND (res.parent = 0)' . $additionalquery . ' ORDER BY category, coursename, name', $searchfields);
} 

/**
 * Return a list of connected reservations
 *
 * @uses $DB
 * @param $reservation reservation object
 * @return array the reservations list
 */
function reservation_get_connected($reservation) {
    global $DB;

    $searchfields = array('reservationid' => $reservation->id);
    $additionalquery = '';

    if (!empty($reservation->parent)) {
        $additionalquery .= ' AND ((res.parent = :parent) OR (res.id = :parent2))';
        $searchfields['parent'] = $reservation->parent;
        $searchfields['parent2'] = $reservation->parent;
    } else {
        $additionalquery .= 'AND (res.parent = :reservationid2)';
        $searchfields['reservationid2'] = $reservation->id;
    }

    return $DB->get_records_sql('SELECT res.id, c.fullname as coursename, c.category as category, res.name as name
                FROM {reservation} res,
                     {course} c
               WHERE res.course = c.id
                     AND (res.id <> :reservationid)'.$additionalquery.
          ' ORDER BY category, coursename, name', $searchfields);
}

/**
 * Return a connected reservation where the user is already reserved, if exists
 *
 * @uses $DB
 * @param $reservation reservation object
 * @return object with course name, reservation name, reservation id
 */
function reservation_reserved_on_connected($reservation, $userid) {
    global $DB;

    $searchfields = array('reservationid' => $reservation->id, 'userid' => $userid);
    $additionalquery = '';

    if (!empty($reservation->parent)) {
        $additionalquery .= ' AND ((res.parent = :parent) OR (res.id = :parent2))';
        $searchfields['parent'] = $reservation->parent;
        $searchfields['parent2'] = $reservation->parent;
    } else {
        $additionalquery .= ' AND (res.parent = :reservationid2)';
        $searchfields['reservationid2'] = $reservation->id;
    }

    $res = $DB->get_record_sql('SELECT res.*
                FROM {reservation} res,
                     {reservation_request} req
               WHERE (res.id = req.reservation)
                     AND (req.userid = :userid)
                     AND (req.timecancelled = 0)
                     AND (res.id <> :reservationid)'.$additionalquery, $searchfields);

    if ($res) {
        if ($course = $DB->get_record('course', array('id' => $res->course))) {
            if ($cm = get_coursemodule_from_instance('reservation', $res->id, $res->course)) {
                $result = new stdClass();
                $result->id = $cm->id;
                $result->name = $res->name;
                $result->coursename = $course->fullname;
                $result->category = $course->category;

                return $result;
            }
        }
    } else {
        return false;
    }
}

/**
 * Return all requests user data
 *
 * @uses $CFG
 * @reservation object   reservation record object
 * @full        boolean  define if return a full list or active only requests
 * @fields      array    which data field but returned
 * @groupid     integer  define if return all users or only members of specified group (NOT USED)
 * @groupmode   integer  how groups are showed
 */
function reservation_get_requests($reservation, $full=false, $fields=null, $groupid=0, $groupmode=NOGROUPS) {

    global $CFG, $DB, $USER;

    $clear = '';
    if (!$full) {
        $clear = ' AND r.timecancelled=0';
    }
/*
    $extraselect = '';
    if (!empty($fields)) {
        foreach ($fields as $fieldid => $field) {
            if (($field->custom === false) && ($fieldid != 'email')) {
                $extraselect .= ', u.'.$fieldid;
            }
        }
    }
*/

    //$requests = $DB->get_records_sql('SELECT r.*, u.firstname, u.lastname, u.picture, u.email, u.imagealt'.$extraselect.
    $requests = $DB->get_records_sql('SELECT u.*, r.*'.
                                     ' FROM {reservation_request} r, {user} u'.
                                     ' WHERE u.deleted = 0 AND r.reservation = :reservationid'.$clear.
                                     ' AND r.userid = u.id ORDER BY r.id', array('reservationid' => $reservation->id));

    if (!empty($requests)) {
        if (!empty($fields)) {
            require_once($CFG->dirroot.'/user/profile/lib.php');
        }

        $number = 1;
        foreach ($requests as $requestid => $request) {
            // Add request order numbers
            $requests[$requestid]->number = $number;
            if ($request->timecancelled == 0) {
                $number++;
            }

            // Set current user information
            if (($request->userid == $USER->id) && ($request->timecancelled == '0')) {
                $requests[0] = $request;
            }

            // Fill extra fields
            if (!empty($fields)) {
                $userdata = new stdClass();
                $userdata->id = $request->userid;
                $userdata = profile_user_record($request->userid);
                foreach ($fields as $fieldid => $field) { 
                    if (($field->custom !== false) && ($field->custom !== 'groups')) {
                        $requests[$requestid]->$fieldid = format_string($userdata->$fieldid);
                    }  
                }
            }

            // Add user note
            if ($reservation->note == 1) {
                $requests[$requestid]->note = $DB->get_field('reservation_note', 'note', array('request' => $requestid));
            }

            // Set user groups
            if ($groupmode != NOGROUPS) {
                if (($groupid == 0) || groups_is_member($groupid, $request->userid)) {
                    $groups = groups_get_user_groups($reservation->course, $request->userid);
                    if (!empty($groups['0'])) {
                        $usergroups = array();
                        foreach($groups['0'] as $group) {
                            $usergroups[] = format_string(groups_get_group_name($group));
                        }
                        $requests[$requestid]->groups = implode(', ',$usergroups);
                    } else {
                        $requests[$requestid]->groups = '';
                    }
                } else {
                    unset($requests[$requestid]);
                    continue;
                }
            }
        }
    }
    return $requests;
}

// Sorts an array (you know the kind) by key
// and by the comparison operator you prefer.
// Note that instead of most important criteron first, it's
// least important criterion first.
// The default sort order is ascending, and the default sort
// type is strnatcmp.
function reservation_multisort($array, $sortorders)
{
    if (!empty($sortorders)) {
        $orders = array_reverse($sortorders,true);
        foreach ($orders as $key => $order) {
            $t = 'strnatcasecmp($a->'.$key.', $b->'.$key.')';
            if (!empty($array)) {
                $element = current($array);
                if (is_numeric($element->$key)) {
                    $t = '$a->'.$key.' - $b->'.$key;
                }
            }
            uasort($array, create_function('$a, $b', 'return ' . ($order==SORT_ASC ? '' : '-') . '(' . $t . ');'));
        }
    }

    return $array;
}

function reservation_set_grades($reservation,$teacherid,$grades) {
    global $DB;

    if (!empty($grades)) {
        $now = time();
        $requests = $DB->get_records('reservation_request', array('reservation' => $reservation->id));
        foreach ($grades as $requestid => $grade) {
            $request = $requests[$requestid];
            if ($grade != $request->grade) {
                $request->teacher = $teacherid;
                $request->grade = $grade;
                $request->mailed = 0;
                $request->timegraded = $now;
                $DB->update_record('reservation_request', $request);
                require_once('lib.php');
                reservation_update_grades($reservation, $request->userid);
            }
        }
    }
}


function reservation_get_teacher_names($reservation, $cmid=null) {
    global $DB;

    $teachername = '';
    if (strlen($reservation->teachers) > 0) {
        $context = context_course::instance($reservation->course);
        $capability = 'moodle/course:viewhiddenactivities';
        if (isset($reservation->coursemodule) && !empty($reservation->coursemodule)) {
            $cmid = $reservation->coursemodule;
        }
        if ($cmid != null) {
            $context = context_module::instance($cmid);
            $capability = 'mod/reservation:addinstance';
        }
        $teachers = explode(',', $reservation->teachers);
        foreach ($teachers as $teacherid) {
            if (!empty($teacherid)) {
                if ($teacher = $DB->get_record('user', array('id' => $teacherid))) {
                    if (has_capability($capability, $context, $teacherid)) {
                        if (strlen($teachername) > 0) {
                            $teachername .= ', ';
                        }
                        $teachername .= fullname($teacher);
                    }
                }
            }
        }
    }
    return $teachername;
}

function reservation_get_profilefields() {
    global $DB;

    $infofields = $DB->get_records('user_info_field');
    $customfields = array();
    foreach($infofields as $infofield) {
        $customfields[$infofield->shortname] = new stdClass();
        $customfields[$infofield->shortname]->name = $infofield->name;
        $customfields[$infofield->shortname]->id = $infofield->id;
    }
    return $customfields;
}

function reservation_get_userdata($userid) {
    global $DB, $CFG;

    if ($userdata = $DB->get_record('user', array('id' => $userid))) {
        require_once($CFG->dirroot.'/user/profile/lib.php');
        $profiledata = profile_user_record($userid);
        if (!empty($profiledata)) {
            foreach($profiledata as $fieldname => $value) {
               $userdata->{$fieldname} = $value;
            }
        }
    }
    return $userdata;
}

function reservation_setup_counters($reservation, $customfields) {
    global $DB;

    $counters = array();
    $counters[0] = new stdClass();
    $counters[0]->count = 0;
    $counters[0]->deletedrequests = 0;
    if ($reservation_limits = $DB->get_records('reservation_limit', array('reservationid' => $reservation->id))) {
        $i = 1;
        foreach($reservation_limits as $reservation_limit) {
            $counters[$i] = $reservation_limit;
            $counters[$i]->count = 0;
            if (isset($customfields[$reservation_limit->field])) {
                $counters[$i]->field = $reservation_limit->field;
                $counters[$i]->fieldname = format_string($customfields[$reservation_limit->field]->name);
            } else {
                $counters[$i]->field = $reservation_limit->field;
                $counters[$i]->fieldname = get_string($reservation_limit->field);
            }
            $i++;
        }
    }
    return $counters;
}

function reservation_update_counters($counters, $request) {

    $counters[0]->overbooked = 0;
    $counters[0]->matchlimit = 0;
    if ($request->timecancelled != '0') {
        $counters[0]->deletedrequests++;
    } else {
        $counters[0]->count++;
        for ($i=1;$i<count($counters);$i++) {
            $fieldname = $counters[$i]->field;
            if (($request->$fieldname == $counters[$i]->matchvalue) && !$counters[$i]->operator) {
                $counters[$i]->count++;
                $counters[0]->matchlimit++;
                if ($counters[$i]->count > $counters[$i]->requestlimit) {
                   $counters[0]->overbooked++;
                }
            } elseif (($request->$fieldname != $counters[$i]->matchvalue) && $counters[$i]->operator) {
                $counters[$i]->count++;
                $counters[0]->matchlimit++;
                if ($counters[$i]->count > $counters[$i]->requestlimit) {
                   $counters[0]->overbooked++;
                }
            }
        }
    }
    return $counters;
}

function reservation_setup_sublimit_fields($counters,$customfields,$fields=array()) {
    foreach ($counters as $counter) {
        if (isset($counter->field)) {
            if (isset($customfields[$counter->field])) {
                if (!isset($fields[$counter->field])) {
                    $fields[$counter->field] = new stdClass();
                    $fields[$counter->field]->custom = $customfields[$counter->field]->id;
                }
            } else if ($counter->field != 'group') {
                if (!isset($fields[$counter->field])) {
                    $fields[$counter->field] = new stdClass();
                    $fields[$counter->field]->custom = false;
                }
            }
        }
    }
    return $fields;
}

// calculate available seat for USER
function reservation_get_availability($reservation,$counters,$available) {
    global $USER;

    $availablesublimit = 0;
    $limitoverbook = 0;

    $userdata = reservation_get_userdata($USER->id);

    if (count($counters) - 1 > 0) {
        for ($i=1;$i<count($counters);$i++) {
            $fieldname = $counters[$i]->field;
            if ((($userdata->{$counters[$i]->field} == $counters[$i]->matchvalue) && !$counters[$i]->operator) ||
               (($userdata->{$counters[$i]->field} != $counters[$i]->matchvalue) && $counters[$i]->operator)) {
                if ($availablesublimit <= ($counters[$i]->requestlimit-$counters[$i]->count)) {
                   $availablesublimit = $counters[$i]->requestlimit-$counters[$i]->count;
                   $limitoverbook = round($counters[$i]->requestlimit * $reservation->overbook / 100);
                }
                $nolimit = false;
            }
        }
        if ($available > $availablesublimit) {
            $result = new stdClass();
            $result->available = $availablesublimit;
            $result->overbook = $limitoverbook;
            return $result;
        }
    }
    return false;
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $fields standard user fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function reservation_validate_upload_columns(csv_import_reader $cir, $fields, $required_fields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < count($required_fields)) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // test columns
    $processed = array();
    $required = 0;
    foreach ($columns as $key=>$unused) {
        $field = $columns[$key];
        $lcfield = core_text::strtolower($field);
        if (in_array($field, $fields) or in_array($lcfield, $fields)) {
            // standard fields are only lowercase
            $newfield = $lcfield;
        } else if (preg_match('/^(field|operator|matchvalue|sublimit)\d+$/', $lcfield)) {
            // sublimit fields - not used
            $newfield = $lcfield;

        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        if (in_array($newfield, $required_fields)) {
            $required++;
        }
        $processed[$key] = $newfield;
    }
    if ($required < count($required_fields)) {
        $cir->close();
        $cir->cleanup();
        print_error('missingrequiredfield', 'error', $returnurl);
    }

    return $processed;
}

/**
 * Tracking of processed users.
 *
 * This class prints user information into a html table.
 *
 * @package    core
 * @subpackage admin
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @copyright  2012 Roberto Pinna  {@mail roberto.pinna@unipmn.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ur_progress_tracker {
    private $_row;
    public $columns = array('status', 'line', 'course', 'section', 'name', 'timestart', 'timeclose');

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="urresults" class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('uploadreservationsresult', 'reservation').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('linenumber', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('course').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('section').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('name').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timestart', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timeclose', 'reservation').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) {
            foreach ($field as $type=>$content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="ur'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

    function reservation_print_tabs($reservation, $mode) {
        $tabs = array();
        $row = array();
   
        $baseurl = 'view.php';
        $queries = array('r' => $reservation->id);
   
        $queries['mode'] = 'overview';
        $url = new moodle_url($baseurl, $queries);
        $row[] = new tabobject('overview', $url, get_string('overview','reservation'));
   
        $queries['mode'] = 'manage';
        $url = new moodle_url($baseurl, $queries);
        $row[] = new tabobject('manage', $url, get_string('manage', 'reservation'));
   
        $tabs[] = $row;
   
        // Print out the tabs and continue!
        print_tabs($tabs, $mode, NULL, NULL);
    }
?>

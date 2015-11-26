<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function reservation_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

function reservation_add_instance($reservation) {
    global $DB;

/// Given an object containing all the necessary data, 
/// (defined by the form in mod.html) this function 
/// will create a new instance and return the id number 
/// of the new instance.                       

    $reservation = reservation_postprocess($reservation);

    if ($returnid = $DB->insert_record('reservation', $reservation)) {
        $reservation->id = $returnid;

        reservation_set_sublimits($reservation);

        reservation_set_events($reservation);

        //$reservation = stripslashes_recursive($reservation);
        reservation_grade_item_update($reservation);
    } else {
        error('Could not insert record');
    }
    
    return $returnid;
}


function reservation_update_instance($reservation) {
    global $DB;
/// Given an object containing all the necessary data, 
/// (defined by the form in mod.html) this function 
/// will update an existing instance with new data.

    $reservation->id = $reservation->instance;

    $reservation = reservation_postprocess($reservation);

    if ($returnid = $DB->update_record('reservation', $reservation)) {
    
        $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id));

        reservation_set_sublimits($reservation);

        reservation_set_events($reservation);
      
        //$reservation = stripslashes_recursive($reservation);
        if (!empty($reservation->maxgrade)) {
            reservation_grade_item_update($reservation);
        } else {
            reservation_grade_item_delete($reservation);
        }
    } else {
        error('Could not update record');
    }

    return $returnid;
}

function reservation_delete_instance($id) {
/// Given an ID of an instance of this module, 
/// this function will permanently delete the instance 
/// and any data that depends on it.  
    global $CFG, $DB;

    if (! $reservation = $DB->get_record('reservation', array('id' => $id))) {
        return false;
    }

    $result = true;

    # Delete any dependent records here #

    if (! $DB->delete_records('reservation', array('id' => $reservation->id))) {
        $result = false;
    }
    
    $allrequestsql = 'SELECT rq.id 
                      FROM {reservation_request} rq
                      WHERE reservation = ?';
    if (! $DB->delete_records_select('reservation_note', 'request IN ('.$allrequestsql.')', array($reservation->id))) {
        $result = false;
    }
    if (! $DB->delete_records('reservation_request', array('reservation' => $reservation->id))) {
        $result = false;
    }
    if (! $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id))) {
        $result = false;
    }

    reservation_grade_item_delete($reservation);

    return $result;
}

/**
 * Obtains the automatic completion state for this reservation based on the condition
 * in reservation settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function reservation_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get reservation details
    $reservation = $DB->get_record('reservation', array('id'=>$cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if ($reservation->completionreserved) {
        $params = array('userid'=>$userid, 'reservation'=>$reservation->id, 'timecancelled'=>0);
        return $DB->record_exists('reservation_request', $params);
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

function reservation_user_outline($course, $user, $mod, $reservation) {
/// Return a small object with summary information about what a 
/// user has done with a given particular instance of this module
/// Used for user activity reports.
/// $return->time = the time they did it
/// $return->info = a short text description
    global $DB;

    $return = NULL;
    if ($userrequest = $DB->get_record('reservation_request', array('reservation' => $reservation->id, 'userid' => $user->id, 'timecancelled' =>'0'))) {
        if ($userrequest->timegraded != 0) {
            $return->info = get_string('grade').': '.$userrequest->grade;
            $return->time = $userrequest->timegraded;
        } else {
            $return->info = get_string('reserved', 'reservation');
            $return->time = $userrequest->timecreated;
        }
    } else {
        $return->info = get_string('noreservations', 'reservation');
    }
    return $return;
}

function reservation_user_complete($course, $user, $mod, $reservation) {
/// Print a detailed representation of what a  user has done with 
/// a given particular instance of this module, for user activity reports.
    global $DB;

    if ($userrequest = $DB->get_record('reservation_request', array('reservation' => $reservation->id, 'userid' => $user->id, 'timecancelled' =>'0'))) {
        echo get_string('reservedon', 'reservation').' '.userdate($userrequest->timecreated, get_string('strftimedatetime')).'<br />';
        if ($userrequest->timegraded != 0) {
            if (! $teacher = $DB->get_record('user', array('id' => $userrequest->teacher))) {
                echo 'Could not find teacher '.$request->teacher."\n";
            } else {
                echo get_string('grade').': '.$userrequest->grade.' ('
                     .userdate($userrequest->timegraded, get_string('strftimedatetime')).' '.get_string('by', 'reservation').' '.fullname($teacher).')<br />';
            }
        }
    } else {
        print_string('noreservations', 'reservation');
    }
    
    return true;
}

function reservation_print_recent_activity($course, $isteacher, $timestart) {
/// Given a course and a time, this module should find recent activity 
/// that has occurred in reservation activities and print it out. 
/// Return true if there was output, or false is there was none.

    global $CFG;

    return false;  //  True if anything was printed, otherwise false 
}

function reservation_cron () {
/// Function to be run periodically according to the moodle cron
/// This function searches for things that need to be done, such 
/// as sending out mail, toggling flags etc ... 

    global $CFG, $USER, $DB;

    /// Notices older than 1 day will not be mailed.  This is to avoid the problem where
    /// cron has not been running for a long time, and then suddenly people are flooded
    /// with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow;
    $starttime = $endtime - 24 * 3600;   /// One day earlier

    if (!isset($CFG->reservation_notifies)) {
        $CFG->reservation_notifies = 'teachers,students,grades';
    }
    $notifies = explode(',', $CFG->reservation_notifies);

    /**
     * Notify request grading to students
     */
    if ($requests = reservation_get_unmailed_requests(0, $starttime)) {
        foreach ($requests as $key => $request) {
            if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
            }
        }
    }
    
    if ($requests = reservation_get_unmailed_requests($starttime, $endtime)) {
        foreach ($requests as $request) {

            mtrace('Processing reservation request '.$request->id);

            if (in_array('grades', $notifies)) {

                if (! $user = $DB->get_record('user', array('id' => $request->userid))) {
                    mtrace('Could not find user '.$request->userid);
                    continue;
                }
    
                $USER->lang = $user->lang;
    
                if (! $course = $DB->get_record('course', array('id' => $request->course))) {
                    mtrace('Could not find course '.$request->course);
                    continue;
                }
    
                if (! $teacher = $DB->get_record('user', array('id' => $request->teacher))) {
                    mtrace('Could not find teacher '.$request->teacher);
                    continue;
                }
    
                if (! $mod = get_coursemodule_from_instance('reservation', $request->reservation, $course->id)) {
                    mtrace('Could not find course module for reservation id '.$request->reservation);
                    continue;
                }
    
                if (! $mod->visible) {    /// Hold mail notification for hidden reservations until later
                    continue;
                }
    
                $strreservations = get_string('modulenameplural', 'reservation');
                $strreservation  = get_string('modulename', 'reservation');
    
                $reservationinfo = new stdClass();
                $reservationinfo->teacher = fullname($teacher);
                $reservationinfo->reservation = format_string($request->name,true);
                $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;
    
                $postsubject = $course->shortname.': '.$strreservations.': '.format_string($request->name,true);
                $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($request->name,true)."\n";
                $posttext .= '---------------------------------------------------------------------'."\n";
                $posttext .= get_string('gradedmail', 'reservation', $reservationinfo);
                $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                $posthtml = '';
    
                if ($user->mailformat == 1) {  // HTML
                    $posthtml = '<p>';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> ->';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.$strreservations.'</a> ->';
                    $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.format_string($request->name,true).'</a>';
                    $posthtml .= '</p>';
                    $posthtml .= '<hr />';
                    $posthtml .= '<p>'.get_string('gradedmailhtml', 'reservation', $reservationinfo).'</p>';
                    $posthtml .= '<hr />';
                }
    
                if (! email_to_user($user, $teacher, $postsubject, $posttext, $posthtml)) {
                    mtrace('Error: reservation cron: Could not send out mail for request id '.$request->id.' to user '.$user->id.' ('.$user->email.')');
                } else {
                    if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                        mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
                    }
                }
            } else {
                // Grades notify disabled. Mark as mailed.
                if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                    mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
                }
            }
        }
    }

    /***
     * Notify the end of reservation time with link for info to teachers and students
     */
    if ($reservations = reservation_get_unmailed_reservations(0, $starttime)) {
        foreach ($reservations as $key => $reservation) {
            mtrace('Set unmailed reservation id '.$reservation->id.' as mailed.');
            if (! $DB->set_field('reservation', 'mailed', '1', array('id' => $reservation->id))) {
                mtrace('Could not update the mailed field for reservation id '.$reservation->id.'.  Not mailed.');
            }
        }
    }

    if ($reservations = reservation_get_unmailed_reservations($starttime, $endtime)) {
        foreach ($reservations as $reservation) {
            mtrace('Process reservation id '.$reservation->id.'.');
            // Mark as mailed just to prevent double mail sending
            if (! $DB->set_field('reservation', 'mailed', '1', array('id' => $reservation->id))) {
                mtrace('Could not update the mailed field for reservation id '.$reservation->id.'.');
            }


            if (! $course = $DB->get_record('course', array('id' => $reservation->course))) {
                mtrace('Could not find course '.$reservation->course);
                continue;
            }

            if (! $mod = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
                mtrace('Could not find course module for reservation id '.$reservation->id);
                continue;
            }

            if (! $mod->visible) {    /// Hold mail notification for hidden reservations until later
                continue;
            }

            $reservationinfo = new stdClass();
            $reservationinfo->reservation = format_string($reservation->name,true);
            if (!isset($CFG->reservation_download) || empty($CFG->reservation_download)) {
                $CFG->reservation_download = 'csv';
            }
            $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'&amp;download='.$CFG->reservation_download;

            if (in_array('teachers', $notifies)) {
                // Notify to teachers
                if (!empty($reservation->teachers)) {
                    $teachers = explode(',',$reservation->teachers);
                } else {
                    // If no teachers are defined in reservation notify to all editing teachers and managers
                    $context = context_module::instance($mod->id);

                    $teachers = array_keys(get_users_by_capability($context, 'mod/reservation:addinstance', 'u.id'));
                }
                if (!empty($teachers)) {
                    foreach($teachers as $teacherid) {
                        mtrace('Processing reservation teacher '.$teacherid);
                        if (! $teacher = $DB->get_record('user', array('id' => $teacherid))) {
                            mtrace('Could not find user '.$teacherid);
                            continue;
                        }
        
                        $USER->lang = $teacher->lang;
        
                        $strreservations = get_string('modulenameplural', 'reservation');
                        $strreservation  = get_string('modulename', 'reservation');
        
                        $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name,true);
                        $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name,true)."\n";
                        $posttext .= '---------------------------------------------------------------------'."\n";
                        $posttext .= get_string('mail', 'reservation', $reservationinfo);
                        $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                        $posthtml = '';
            
                        if ($teacher->mailformat == 1) {  // HTML
                            $posthtml = '<p>';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.$strreservations.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.format_string($reservation->name,true).'</a>';
                            $posthtml .= '</p>';
                            $posthtml .= '<hr />';
                            $posthtml .= '<p>'.get_string('mailhtml', 'reservation', $reservationinfo).'</p>';
                            $posthtml .= '<hr />';
                        }
            
                        if (! email_to_user($teacher, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                            mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.' to user '.$teacher->id.' ('.$teacher->email.')');
                        }
                    }
                }
            }
          
            if (in_array('students', $notifies)) {
                // Notify to students 
                require_once($CFG->dirroot.'/mod/reservation/locallib.php'); 
    
                $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;
                if ($requests = reservation_get_requests($reservation)) {
                    foreach($requests as $request) {
                        mtrace('Processing reservation user '.$request->userid);
                        if (! $user = $DB->get_record('user', array('id' => $request->userid))) {
                            mtrace('Could not find user '.$userid);
                            continue;
                        }
        
                        $USER->lang = $user->lang;
            
                        $strreservations = get_string('modulenameplural', 'reservation');
                        $strreservation  = get_string('modulename', 'reservation');
            
                        $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name,true);
                        $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name,true)."\n";
                        $posttext .= '---------------------------------------------------------------------'."\n";
                        $posttext .= get_string('mailrequest', 'reservation', $reservationinfo);
                        $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                        $posthtml = '';
            
                        if ($user->mailformat == 1) {  // HTML
                            $posthtml = '<p>';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.$strreservations.'</a> ->';
                            $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.format_string($reservation->name,true).'</a>';
                            $posthtml .= '</p>';
                            $posthtml .= '<hr />';
                            $posthtml .= '<p>'.get_string('mailrequesthtml', 'reservation', $reservationinfo).'</p>';
                            $posthtml .= '<hr />';
                        }
            
                        if (! email_to_user($user, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                            mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.' to user '.$user->id.' ('.$user->email.')');
                        }
                    }
                }
            }
        }
    }

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param object $reservation
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function reservation_get_user_grades($reservation, $userid=0) {
    global $CFG, $DB;

    $param = array();
    $user = '';
    if ($userid) {
        $user = ' AND u.id = :userid';
        $param['userid'] = $userid;
    }
    
    $sql = 'SELECT u.id as userid, r.grade AS rawgrade, r.teacher AS usermodified, r.timegraded AS dategraded, r.timecreated AS datesubmitted
            FROM {user} u, {reservation_request} r 
            WHERE u.id = r.userid AND r.timegraded >0 AND r.reservation = :reservation'.$user;
    $param['reservation'] = $reservation->id;

    return $DB->get_records_sql($sql, $param);
    
}  

/**
 * Update grades by firing grade_updated event
 *
 * @param object $reservation null means all reservations
 * @param int $userid specific user only, 0 mean all
 */
function reservation_update_grades($reservation=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($reservation != null) {
        if ($grades = reservation_get_user_grades($reservation, $userid)) {

            foreach($grades as $k=>$v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
            reservation_grade_item_update($reservation, $grades);
        } else {
            reservation_grade_item_update($reservation);
        }

    } else {
        $sql = "SELECT r.*, cm.idnumber as cmidnumber, r.course as courseid
                  FROM {reservation} r, {course_modules} cm, {modules} m
                 WHERE m.name='reservation' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $reservation) {
                if ($reservation->maxgrade != 0) {
                    reservation_update_grades($reservation);
                } else {
                    reservation_grade_item_update($reservation);
                }
            }
            $rs->close();
        }
    }
}

/**
 * Create grade item for given reservation
 *
 * @param object $reservation object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function reservation_grade_item_update($reservation, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($reservation->courseid)) {
        $reservation->courseid = $reservation->course;
    }

    $params = array('itemname'=>$reservation->name, 'idnumber'=>$reservation->id);

    if ($reservation->maxgrade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $reservation->maxgrade;
        $params['grademin']  = 0;

    } else if ($reservation->maxgrade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$reservation->maxgrade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/reservation', $reservation->courseid, 'mod', 'reservation', $reservation->id, 0, $grades, $params);
}

/**
 * Delete grade item for given reservation
 *
 * @param object $reservation object
 * @return object reservation
 */
function reservation_grade_item_delete($reservation) {
    global $CFG;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($reservation->courseid)) {
        $reservation->courseid = $reservation->course;
    }

    return grade_update('mod/reservation', $reservation->courseid, 'mod', 'reservation', $reservation->id, 0, NULL, array('deleted'=>1));
}

function reservation_refresh_events($courseid = 0) {
// This standard function will check all instances of this module
// and make sure there are up-to-date events created for each of them.
// If courseid = 0, then every reservation event in the site is checked, else
// only reservation events belonging to the course specified are checked.
// This function is used, in its new format, by restore_refresh_events()
    global $DB;

    if ($courseid) {
        if (! $reservations = $DB->get_records('reservation', array('course' => $courseid))) {
            return true;
        }
    } else {
        if (! $reservations = $DB->get_records('reservation')) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'reservation'));

    foreach ($reservations as $reservation) {
        $DB->delete_records('event', array('modulename' => 'reservation', 'instance' => $reservation->id));
        reservation_set_events($reservation);
   }
    return true;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all requests from the specified reservation
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function reservation_reset_userdata($data) {
    global $CFG, $DB;

    $status = array();    

    $allreservationsql = 'SELECT r.id
                         FROM {reservation} r
                         WHERE r.course = :courseid';
    $allrequestsql = 'SELECT rq.id 
                      FROM {reservation_request} rq
                      WHERE reservation IN ('.$allreservationsql.')';

    if (!empty($data->reset_reservation_request)) {
        $DB->delete_records_select('reservation_request', 'reservation IN ('.$allreservationsql.')', array('courseid' => $data->courseid));
        $DB->delete_records_select('reservation_note', 'request IN ('.$allrequestsql.')', array('courseid' => $data->courseid));

        $status[] = array('component'=>get_string('modulenameplural', 'reservation'), 'item'=>get_string('requests', 'reservation'), 'error'=>false);
    }

    return $status;
}

/* Called by course/reset.php
 * @param $mform form passed by reference
 */
function reservation_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'reservationheader', get_string('modulenameplural', 'reservation'));

    $mform->addElement('checkbox', 'reset_reservation_request', get_string('requests', 'reservation'));
}

/**
 * Course reset form defaults.
 */
function reservation_reset_course_form_defaults($course) {
    return array('reset_reservation_request'=>1);
}

function reservation_get_unmailed_reservations($starttime, $endtime) {
    /// Return list of closed reservation that have not been mailed out to assigned teachers
    global $CFG, $DB;
    return $DB->get_records_sql('SELECT res.*
                                   FROM {reservation} res
                                  WHERE res.mailed = 0
                                    AND res.timeclose <= :endtime
                                    AND res.timeclose >= :starttime',
                                array('endtime' => $endtime, 'starttime' => $starttime));
}

function reservation_get_unmailed_requests($starttime, $endtime) {
    /// Return list of graded requests that have not been mailed out
    global $CFG, $DB;
    return $DB->get_records_sql('SELECT req.*, res.course, res.name
                                   FROM {reservation_request} req,
                                        {reservation} res,
                                        {user} u
                                  WHERE req.mailed = 0
                                    AND req.timecancelled = 0
                                    AND req.timegraded <= :endtime
                                    AND req.timegraded >= :starttime
                                    AND req.reservation = res.id
                                    AND req.userid = u.id',
                                array('endtime' => $endtime, 'starttime' => $starttime));
}

function reservation_postprocess($reservation) {
    global $CFG;

    $reservation->timemodified = time();

    if (!isset($reservation->location)) {
        $reservation->location = '';
    }

    if ((trim($reservation->locationtext) != '') && empty($reservation->location)) {
        $reservation->location = trim($reservation->locationtext);
    }

    if (!empty($reservation->teachers)) {
        $reservation->teachers = implode(',', $reservation->teachers);
    } else {
        $reservation->teachers = '';
    }

    $reservation->mailed = 0;
    if ($reservation->timeclose < $reservation->timemodified) {
        $reservation->mailed = 1;
    }

    return $reservation;
}

function reservation_set_sublimits($reservation) {
    global $CFG, $DB;

    if (isset($CFG->reservation_sublimits) && !empty($CFG->reservation_sublimits)) {

        $DB->delete_records('reservation_limit', array('reservationid' => $reservation->id));

        $last = false;
        for ($i=1;!$last && ($i<=$CFG->reservation_sublimits);$i++) {
            $field = 'field_'.$i;
            $operator = 'operator_'.$i;
            $matchvalue = 'matchvalue_'.$i;
            $requestlimit = 'requestlimit_'.$i;
            if ($reservation->$field != '-') {
                $reservationlimit = new stdClass();
                $reservationlimit->reservationid = $reservation->id;
                $reservationlimit->field = $reservation->$field;
                $reservationlimit->operator = $reservation->$operator;
                $reservationlimit->matchvalue = $reservation->$matchvalue;
                $reservationlimit->requestlimit = $reservation->$requestlimit;
    
                if (!$limitid = $DB->insert_record('reservation_limit', $reservationlimit)) {
                    error('Could not insert sublimit rule '.$i);
                }
            } else {
                $last = true;
            }
        }
    }

    return $reservation;
}

function reservation_set_events($reservation) {
        global $CFG;
        if (!isset($CFG->reservation_events)) {
            $CFG->reservation_events = 'reservation,event';
        }
        
        if (isset($CFG->reservation_events)  && !empty($CFG->reservation_events)) {
            require_once($CFG->dirroot.'/calendar/lib.php');
            
            $events = explode(',', $CFG->reservation_events);
            $event = new stdClass();
            $event->name        = $reservation->name;
            $event->description = format_module_intro('reservation', $reservation, $reservation->coursemodule);
            $event->courseid    = $reservation->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'reservation';
            $event->instance    = $reservation->id;
            $event->eventtype   = 'start';
            $event->timestart   = $reservation->timestart;
            $event->visible     = instance_is_visible('reservation', $reservation);
            $event->timeduration = max($reservation->timeend-$reservation->timestart,0);
    
            $event2 = clone($event);
    
            if (in_array('event', $events)) {
                calendar_event::create($event);
            }
    
            $event2->name        .= ' ('.get_string('reservations', 'reservation').')';
            $event2->description  = array();
            $event2->description['format']= FORMAT_HTML;
            $event2->description['text']  = userdate($reservation->timestart, get_string('strftimedaydatetime')).'<br />'.$reservation->location.'<br />'.format_module_intro('reservation', $reservation, $reservation->coursemodule);
            $event2->eventtype    = 'reservation';
            $event2->timestart    = ($reservation->timeopen) != 0?$reservation->timeopen:$reservation->timemodified;
            $event2->timeduration = ($reservation->timeclose-$event2->timestart)>0?($reservation->timeclose-$event2->timestart):0;
    
            if (in_array('reservation', $events)) {
                calendar_event::create($event2);
            }
    
        }
}

function reservation_get_view_actions() {
    return array('view', 'view all');
}

function reservation_get_port_actions() {
    return array('reserve', 'cancel'. 'grade');
}

?>

<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('locallib.php');
    require_once($CFG->libdir.'/tablelib.php');

    $id = optional_param('id', NULL, PARAM_INT);                    // Course Module ID, or
    $r = optional_param('r', NULL, PARAM_INT);                      // reservation ID
    $view = optional_param('view',NULL, PARAM_ALPHA);               // 'full' or 'clean' view for teacher
    $savegrades = optional_param('savegrades', NULL, PARAM_ALPHA);  // save all modified grades
    $action = optional_param('action', NULL, PARAM_ALPHA);          // delete o message selected requests
    $mode = optional_param('mode', 'overview', PARAM_ALPHA);        // define the viewed tab
    $download = optional_param('download', null, PARAM_ALPHA);      // null or one of available download formats
    $currenttab = $mode;

    if (isset($id)) {
        if (! $cm = get_coursemodule_from_id('reservation', $id)) {
            error('Course Module ID was incorrect');
        }
        if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
            error('Course is misconfigured');
        }
        if (! $reservation = $DB->get_record('reservation', array('id' => $cm->instance))) {
            error('Course module is incorrect');
        }
    } else {
        if (! $reservation = $DB->get_record('reservation', array('id' => $r))) {
            error('Course module is incorrect');
        }
        if (! $course = $DB->get_record('course', array('id' => $reservation->course))) {
            error('Course is misconfigured');
        }
        if (! $cm = get_coursemodule_from_instance('reservation', $reservation->id, $course->id)) {
            error('Course Module ID was incorrect');
        }
    }

    $group = groups_get_activity_group($cm, true);              // group ID

    $queries = array('id'=>$cm->id);
    if ($group !== false) {
        $queries['group'] = $group;
    }
    if (!empty($mode)) {
        $queries['mode'] = $mode;
    }
    $url = new moodle_url('/mod/reservation/view.php', $queries);
    $PAGE->set_url($url);

    require_login($course->id,false,$cm);

    $now = time();

    $context = context_module::instance($cm->id);

    $coursecontext = context_course::instance($reservation->course);

    $params = array(
        'context' => $context,
        'objectid' => $reservation->id
    );
    $event = \mod_reservation\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('reservation', $reservation);
    $event->trigger();

    // Store grades if set
    if (has_capability('mod/reservation:grade',$context)) {
        if (isset($savegrades) && confirm_sesskey()) {
            if (isset($savegrades)) {
                $grades = optional_param_array('grades', array(), PARAM_INT);
                reservation_set_grades($reservation, $USER->id, $grades);
            } 
        }
    } else {
       unset($savegrades); 
    }

    // Do an action 
    if (isset($action) && confirm_sesskey()) {
        switch($action) {
            case 'delete':  /// Some requests need to be deleted
                if (has_capability('mod/reservation:manualdelete',$context)) {
                    $requestids = optional_param_array('requestid', array(), PARAM_INT);
                    if(is_array($requestids) && !empty($requestids)) {
                        require_once('lib.php');
                        foreach($requestids as $num => $requestid) {
                            if(!empty($requestid)) {
                                unset($requestids[$num]);
                                $request = $DB->get_record('reservation_request', array('id' => $requestid));
                                $requestnote = $DB->get_record('reservation_note', array('request' => $requestid));
                                $requestnote = $requestnote?$requestnode:new stdClass();

                                $DB->set_field('reservation_request', 'grade', -1, array('id' => $requestid));
                                $userid = $DB->get_field('reservation_request', 'userid', array('id' => $requestid));
                                reservation_update_grades($reservation, $userid);

                                $DB->delete_records('reservation_request', array('id' => $requestid));
                                $DB->delete_records('reservation_note', array('request' => $requestid));

                                // Update completion state
                                $completion=new completion_info($course);
                                if ($completion->is_enabled($cm)) {
                                    $completion->update_state($cm,COMPLETION_INCOMPLETE,$userid);
                                }

                                \mod_reservation\event\request_deleted::create_from_request($reservation, 
                                                                                            $context, 
                                                                                            $request,
                                                                                            $requestnote)->trigger();
                            }
                        }
                    }
                }
            break;
            case 'mail':  /// Send mail to selected users
                if (has_capability('mod/reservation:viewrequest',$context)) {
                    $requestids = optional_param_array('requestid', array(), PARAM_INT);
                    if(!is_array($requestids) || empty($requestids)) {
                        break;
                    }

                    foreach($requestids as $num => $requestid) {
                        if(empty($requestid)) {
                            unset($requestids[$num]);
                        }
                    }

                    if (empty($SESSION->reservation_messageto)) {
                        $SESSION->reservation_messageto = array();
                    }
                    if (!array_key_exists($cm->id,$SESSION->reservation_messageto)) {
                        $SESSION->reservation_messageto[$cm->id] = array();
                    }

                    foreach($requestids as $requestid) {
                        $request = $DB->get_record('reservation_request', array('id' => $requestid));
                        $user = $DB->get_record('user', array('id' => $request->userid));
                        $user->teacher = $USER->id;
                        $SESSION->reservation_messageto[$cm->id][$request->userid] = $user;
                    }

                    require_once('messageselect.php');
                    exit();
                }
            break; 
        }
    }

    $strreservations = get_string('modulenameplural', 'reservation');
    $strreservation  = get_string('modulename', 'reservation');
    
    $strfields = array();
    $strfields['email'] = get_string('email');
    $strfields['city'] = get_string('city');
    $strfields['country'] = get_string('country');
    $strfields['idnumber'] = get_string('idnumber');
    $strfields['institution'] = get_string('institution');
    $strfields['department'] = get_string('department');
    $strfields['phone1'] = get_string('phone');
    $strfields['phone2'] = get_string('phone2');
    $strfields['address'] = get_string('address');

    $countrynames = get_string_manager()->get_list_of_countries();

    $pagetitle = strip_tags($course->shortname.': '.format_string($reservation->name));

    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);


    if (empty($download)) {
        //
        /// Print the page header
        //
        echo $OUTPUT->header();

        /// Display Intro
        echo $OUTPUT->heading(format_string($reservation->name));

        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

        echo html_writer::tag('div', format_module_intro('reservation', $reservation, $cm->id), array('class' => 'intro'));
        // Retrive teachers list
        $teachername = reservation_get_teacher_names($reservation, $cm->id);
        if (!empty($teachername)) {
            $teacherroles = get_archetype_roles('editingteacher');
            $teacherrole = array_shift($teacherroles);
            $teacherstr = role_get_name($teacherrole, $coursecontext);
            echo html_writer::start_tag('div');
            echo html_writer::tag('label',$teacherstr.': ', array('class' => 'bold'));
            echo html_writer::tag('span', $teachername);
            echo html_writer::end_tag('div');
        }
        if (!empty($reservation->location)) {
            echo html_writer::start_tag('div');
            echo html_writer::tag('label', get_string('location','reservation').': ', array('class' => 'bold'));
            echo html_writer::tag('span', $reservation->location);
            echo html_writer::end_tag('div');
        }
        $strftimedaydatetime = get_string('strftimedaydatetime');
            echo html_writer::start_tag('div');
            if (!empty($reservation->timeend)) { 
                echo html_writer::tag('label', get_string('timestart','reservation').': ', array('class' => 'bold'));
            } else {
                echo html_writer::tag('label', get_string('date').': ', array('class' => 'bold'));
            }
            echo html_writer::tag('span', userdate($reservation->timestart, $strftimedaydatetime));
            echo html_writer::end_tag('div');
        if (!empty($reservation->timeend)) { 
            echo html_writer::start_tag('div');
            echo html_writer::tag('label', get_string('timeend','reservation').': ', array('class' => 'bold'));
            echo html_writer::tag('span', userdate($reservation->timeend, $strftimedaydatetime));
            echo html_writer::end_tag('div');
        }

        echo html_writer::empty_tag('hr', array('class' => 'clearfloat'));
   
        if (!empty($reservation->timeopen)) {
            echo html_writer::start_tag('div');
            echo html_writer::tag('label', get_string('timeopen','reservation').': ', array('class' => 'bold'));
            if ($now < $reservation->timeopen) {
                echo html_writer::tag('span', 
                                      userdate($reservation->timeopen, $strftimedaydatetime), 
                                      array('class' => 'notopened'));
                echo html_writer::tag('span', ' '.get_string('reservationnotopened', 'reservation'), array('class' => 'bold'));
            } else {
                echo html_writer::tag('span', userdate($reservation->timeopen, $strftimedaydatetime));
            }
            echo html_writer::end_tag('div');
        }
        echo html_writer::start_tag('div');
        echo html_writer::tag('label', get_string('timeclose','reservation').': ', array('class' => 'bold'));
        if ($now > $reservation->timeclose) {
            echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime), array('class' => 'notopened'));
            echo html_writer::tag('span', ' '.get_string('reservationclosed', 'reservation'), array('class' => 'bold'));
        } else {
            echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime));
        }
        echo html_writer::end_tag('div');

        if (has_capability('mod/reservation:viewrequest',$context)) { 
            if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                require_once($CFG->libdir.'/coursecatlib.php');  
                $displaylist = coursecat::make_categories_list();
            }
            // Show connected reservations
            if ($connectedreservations = reservation_get_connected($reservation)) {
                $connectedlist = html_writer::tag('label', 
                                                   get_string('connectedto','reservation').': ', 
                                                   array('class' => 'bold'));
                $connectedlist .= html_writer::start_tag('ul', array('class' => 'connectedreservations'));
                foreach ($connectedreservations as $cr) {
                    $linktext = $cr->coursename . ': ' . $cr->name;
                    if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                        $linktext = $displaylist[$cr->category] .'/'. $linktext;
                    }
                    $linkurl = new moodle_url('/mod/reservation/view.php',array('r' => $cr->id));
                    $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));
                    $connectedlist .= html_writer::tag('li', $link);
                }
                $connectedlist .= html_writer::end_tag('ul');

                echo html_writer::tag('div', $connectedlist, array('class' => 'connected'));
            }
        }

        echo $OUTPUT->box_end();
    }

    // Check to see if groups are being used in this reservation
    $groupmode = groups_get_activity_groupmode($cm);

    if (($mode == 'manage') && ($now < $reservation->timestart)) {
        // get list of users available for manual reserve
        $addableusers = array();

        if (has_capability('mod/reservation:manualreserve',$context)) {
            $participants = array();
            if (!isset($CFG->reservation_manual_users)) {
                $CFG->reservation_manual_users = 'course';
            }

            if ($CFG->reservation_manual_users == 'site') {
                $participants = $DB->get_records('user', array('deleted' => 0, 'suspended' => 0), 'lastname ASC', '*');
            } else { 
                $participants = get_enrolled_users($coursecontext, null, 0, 'u.*', 'u.lastname ASC');
            }
            if (!empty($participants)) {
                foreach ($participants as $participant) {
                    if (!in_array($participant->username, array('guest', 'admin'))) {
                        if ($groupmode == SEPARATEGROUPS) {
                            if (($group != 0) && (has_capability('mod/reservation:viewrequest',$context))) {
                                $groups = groups_get_user_groups($reservation->course, $participant->id);
                                if (!empty($groups) && (array_search($group, $groups['0']) === false)) {
                                    continue;
                                }
                            } else if (!has_capability('mod/reservation:viewrequest',$context)) {
                                $mygroups = groups_get_user_groups($reservation->course, $USER->id); 
                                if (!empty($mygroups['0'])) {
                                    $notmember = true;
                                    $i = 0;
                                    while (($i < count($mygroups['0'])) && (!groups_is_member($mygroups['0'][$i], $participant->id))) {
                                        $i++;
                                    } 
                                    if ($i == count($mygroups['0'])) {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            }
                        }
                        $addableusers[$participant->id] = fullname($participant);
                    }
                }
            }
        }
    }

    // Get profile custom fields array
    $customfields = reservation_get_profilefields();

    // Get request table display fields
    if (!isset($CFG->reservation_fields)) {
        $CFG->reservation_fields = '';
    }
  
    $fields = array(); 
    if (has_capability('mod/reservation:viewrequest',$context)) {
        // Add fields to requests table
        $field = strtok($CFG->reservation_fields,',');
        while ($field !== false) {
            if (isset($customfields[$field])) {
                if (!isset($fields[$field])) {
                    $fields[$field] = new stdClass();
                    $fields[$field]->custom = $customfields[$field]->id;
                }
                $fields[$field]->name = format_string($customfields[$field]->name);
            } else {
                if (!isset($fields[$field])) {
                    $fields[$field] = new stdClass();
                    $fields[$field]->custom = false;
                }
                $fields[$field]->name = $strfields[$field];
            }
            $field = strtok(',');
        }

        if (($groupmode == VISIBLEGROUPS) || (($groupmode == SEPARATEGROUPS) && ($group == 0))) {
            $fields['groups'] = new stdClass();
            $fields['groups']->name = get_string('group');
            $fields['groups']->custom = 'groups';
        }
    }

    // Set global and sublimit counters
    $counters = reservation_setup_counters($reservation,$customfields);

    // Add sublimits fields to used fields
    $fields = reservation_setup_sublimit_fields($counters,$customfields,$fields);

    $norequest = true;

    $rows = array();


    // Get all reservation requests
    $requests = reservation_get_requests($reservation, true, $fields, $group, $groupmode);
    if (!empty($requests)) {
        // Check for requests full view
        if (has_capability('mod/reservation:viewrequest',$context)) {
            if ($view == 'clean') {
                unset($_SESSION['mod_reservation'][$reservation->id]['view']);
            } else if($view == 'full') {
                $_SESSION['mod_reservation'][$reservation->id]['view'] = 'full';
            } else if (isset($_SESSION['mod_reservation'][$reservation->id]['view'])) {
                $view = $_SESSION['mod_reservation'][$reservation->id]['view'];
            } 
        } else if (has_capability('mod/reservation:reserve',$context)) {
            $view = 'clean';
        }

        if ($mode == 'manage') {
            $actions = array();
            if (has_capability('mod/reservation:viewrequest',$context)) { 
                $actions['mail'] = get_string('sendmessage', 'message');
            }
            if (has_capability('mod/reservation:manualdelete',$context)) { 
                $actions['delete'] = get_string('deleteselected');
            }
        }

        // Get user request information (if already reserved)
        if (isset($requests[0])) {
            $currentuser = new stdClass();
            $currentuser->number = $requests[0]->number;
            if (($reservation->maxgrade != 0 ) && ($now > $reservation->timestart) && ($requests[0]->grade >= 0)) {
                if ($reservation->maxgrade < 0) {
                    if ($scale = $DB->get_record('scale', array('id' => -$reservation->maxgrade))) {
                        $values = explode(',',$scale->scale);
                        $currentuser->grade = get_string('yourscale', 'reservation',$values[$requests[0]->grade-1]);
                    }
                } else {
                    $grade = new stdClass();
                    $grade->grade = $requests[0]->grade;
                    $grade->maxgrade = $reservation->maxgrade;
                    $currentuser->grade = get_string('yourgrade', 'reservation',$grade);
                }
            }
            if (($reservation->note) && !empty($requests[0]->note)) {
                $currentuser->note = get_string('note', 'reservation').': '.format_string($requests[0]->note);
            } else {
                $currentuser->note = '';
            }
            unset($requests[0]);
        }

        // Create requests table
        $table = new flexible_table('mod-reservation-requests');

        if ($mode == 'overview') {
            $table->is_downloadable(true);
            $table->show_download_buttons_at(array(TABLE_P_TOP, TABLE_P_BOTTOM));

            $table->is_downloading($download,
                                   clean_filename("$course->shortname ".format_string($reservation->name,true)),
                                   format_string($reservation->name,true));
        }

        /// Define Table headers
        if (empty($download)) {
            $tableheaders = array('#','', get_string('fullname'));
            $tablecolumns = array('number', 'picture', 'fullname');
        } else {
            $tableheaders = array('#', get_string('firstname'), get_string('lastname'));
            $tablecolumns = array('number', 'firstname', 'lastname');
        }
        if (has_capability('mod/reservation:viewrequest',$context)) {
            if (!empty($fields)) {
                foreach($fields as $fieldid => $field) {
                    if (isset($field->name)) {
                          $tableheaders[] = $field->name;
                          $tablecolumns[] = $fieldid;
                    }
                }
            }
            $tableheaders[] = get_string('reservedon', 'reservation');
            $tablecolumns[] = 'timecreated';
            if ($view == 'full') {
                $tableheaders[] = get_string('cancelledon', 'reservation');
                $tablecolumns[] = 'timecancelled';
            }

            if (has_capability('mod/reservation:viewnote',$context) && ($reservation->note == 1)) {
                $tableheaders[] = get_string('note', 'reservation');
                $tablecolumns[] = 'note';
            }
            if (($reservation->maxgrade != 0) && ($now > $reservation->timestart)) {
                $tableheaders[] = get_string('grade');
                $tablecolumns[] = 'grade';
            }
            if (empty($download) && !empty($actions)) {
                $tableheaders[] = get_string('select');
                $tablecolumns[] = 'select';
            }
        }
        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($url);

        if (has_capability('mod/reservation:viewrequest',$context)) { 
            $table->sortable(true);
            $table->collapsible(true);
            if (($mode == 'manage') && !empty($actions)) {
                $table->no_sorting('select');
            }
        } else {
            $table->sortable(false);
            $table->collapsible(false);
        }

        foreach ($tablecolumns as $column) {
            $table->column_class($column, $column);
        }

        $table->set_attribute('id', 'requests');
        $table->set_attribute('class', 'requests');
   
        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        //Sort data as requested
        if (has_capability('mod/reservation:viewrequest',$context)) {
            $sortby = $table->get_sort_columns();
            if (!empty($sortby)) {
                $requests = reservation_multisort($requests, $sortby);
            }
        }
   
        foreach ($requests as $request) {
            $row = array();
            $rowclasses = array();

            // Remove already reserved users from manual reservation list of users
            if (($mode == 'manage') && isset($addableusers) && 
                                       isset($addableusers[$request->userid]) && ($request->timecancelled == 0)) {
                unset($addableusers[$request->userid]);
            }

            // Highlight current user request
            if (($USER->id == $request->userid) && ($request->timecancelled == '0')) {
                $rowclasses[] = 'yourreservation';
            }

            $counters = reservation_update_counters($counters, $request);
            // set row data
            if (($counters[0]->matchlimit>0) && ($counters[0]->matchlimit==$counters[0]->overbooked) &&
                                                ($request->timecancelled == 0)) {
                $rowclasses[] = 'overbooked';
            }
            if (($reservation->maxrequest < $request->number) && ($reservation->maxrequest > 0) && ($request->timecancelled == 0)) {
                $rowclasses[] = 'overbooked';
            }
            if (has_capability('mod/reservation:viewrequest',$context)) {
                if ($request->timecancelled != '0') {
                    $rowclasses[] = 'cancelled';
                }
            }

            // check for group (TODO: check if it can be moved in get_all_requests)
            if ($groupmode == SEPARATEGROUPS) {
                if (($group != 0) && (has_capability('mod/reservation:viewrequest',$context))) {
                    $groups = groups_get_user_groups($reservation->course, $request->userid);
                    if (!empty($groups) && (array_search($group, $groups['0']) === false)) {
                        continue;
                    }
                } else if (!has_capability('mod/reservation:viewrequest',$context)) {
                    $mygroups = groups_get_user_groups($reservation->course, $USER->id); 
                    if (!empty($mygroups['0'])) {
                        $notmember = true;
                        $i = 0;
                        while (($i < count($mygroups['0'])) && (!groups_is_member($mygroups['0'][$i], $request->userid))) {
                            $i++;
                        } 
                        if ($i == count($mygroups['0'])) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
            }

            $rowclass = implode(' ', $rowclasses);

            if (($request->timecancelled == '0') || (has_capability('mod/reservation:viewrequest',$context) && ($view == 'full'))) {
                $norequest = false;
                if ($request->timecancelled == '0') {
                    $row[] = $request->number;
                } else {
                    $row[] = '';
                }

                if (empty($download)) {
                    $userlink = new moodle_url('/user/view.php', array('id'=>$request->userid,'course'=>$course->id));
                    $user = $DB->get_record('user', array('id' => $request->userid));
                    $row[] = $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                    $row[] = html_writer::tag('a', fullname($request), array('href'=>$userlink, 'class'=>'fullname '.$rowclass));
                } else {
                    $row[] = $request->firstname;
                    $row[] = $request->lastname;
                }

                if (has_capability('mod/reservation:viewrequest',$context)) {
                    if (!empty($fields)) {
                        foreach($fields as $fieldid => $field) {
                            if (isset($field->name)) {
                                switch ($fieldid) {
                                    case 'email':
                                        if (empty($download)) {
                                            $fieldvalue = obfuscate_mailto($request->$fieldid);
                                        } else {
                                            $fieldvalue = $request->$fieldid;
                                        }
                                    break;
                                    case 'country':
                                        $fieldvalue = $countrynames[$request->$fieldid];
                                    break;
                                    default:
                                        $fieldvalue = format_string($request->$fieldid);
                                    break;
                                }
                                    
                                if (empty($download)) {
                                    $row[] = html_writer::tag('div', $fieldvalue, array('class'=>$fieldid.' '.$rowclass));
                                } else {
                                    $row[] = $fieldvalue;
                                }
                            }
                        }
                    }
                    // add reservation request time
                    if (empty($download)) {
                        $row[] = html_writer::tag('div', 
                                                  trim(userdate($request->timecreated, get_string('strftimedatetime'))), 
                                                  array('class'=>'timecreated '.$rowclass));
                    } else {
                        $row[] = trim(userdate($request->timecreated, get_string('strftimedatetime')));
                    }

                    // if full view display also request revocation time
                    if ($view == 'full') {
                        if ($request->timecancelled != '0') {
                            if (empty($download)) {
                                $row[] = html_writer::tag('div', 
                                                          trim(userdate($request->timecancelled, get_string('strftimedatetime'))),
                                                          array('class'=>'timecancelled '.$rowclass));
                            } else {
                                $row[] = trim(userdate($request->timecancelled, get_string('strftimedatetime')));
                            }
                        } else {
                            $row[] = '';
                        }
                    }
                    // add reservation request note
                    if ($reservation->note) {
                        if (($view == 'full') || ($request->timecancelled == 0)) {
                            if (isset($request->note) && !empty($request->note)) {
                                if (empty($download)) {
                                    $row[] = html_writer::tag('div', $request->note, array('class'=>'note '.$rowclass));
                                } else {
                                    $row[] = $request->note;
                                }
                            } else {
                                if (empty($download)) {
                                    $row[] = html_writer::tag('div', '', array('class'=>'note'));
                                } else {
                                    $row[] = '';
                                }
                            }
                        }
                    }
                    // display grade or grading dropdown menu
                    if (($reservation->maxgrade != 0) && ($now > $reservation->timestart)) {
                        if (($mode == 'manage') && ($request->timecancelled == 0)) {
                            $row[] = html_writer::select(make_grades_menu($reservation->maxgrade), 
                                                         'grades['.$request->id.']', 
                                                         $request->grade, 
                                                         array(-1=>get_string('nograde')));
                        } else {
                            if ($request->timegraded != 0) {
                                $usergrade = $request->grade;
                                if ($reservation->maxgrade < 0) {
                                    if ($scale = $DB->get_record('scale', array('id' => -$reservation->maxgrade))) {
                                        $values = explode(',',$scale->scale);
                                        $usergrade = $values[$request->grade-1];
                                    }
                                }
                                if (empty($download)) {
                                    $row[] = html_writer::tag('div', $usergrade, array('class'=>'grade '.$rowclass));
                                } else {
                                    $row[] = $usergrade;
                                }
                            } else {  
                                if (empty($download)) {
                                    $row[] = html_writer::tag('div', '', array('class'=>'grade '.$rowclass));
                                } else {
                                    $row[] = '';
                                }
                            }
                        }
                    }
                    // if some actions are available, display the selection checkbox
                    if (($mode == 'manage') && !empty($actions) && !empty($row)) {
                        $row[] = html_writer::empty_tag('input', array('type' => 'checkbox',
                                                                       'name' => 'requestid[]',
                                                                       'value' => $request->id));
                    }
                }
            }
            // add row to the table
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
    }

    // set available seats in global count
    $available = max($CFG->reservation_max_requests,($counters[0]->count + 1));
    $overbook = 0;
    if ($reservation->maxrequest > 0) {
        $available = $reservation->maxrequest;
        $overbook = round($reservation->maxrequest * $reservation->overbook / 100);
    }
    $available = min($available,($available-$counters[0]->count));

    if (empty($download) && has_capability('mod/reservation:viewrequest',$context)) {
        /// Show seats availability
        $overview = new html_table();
        $overview->tablealign = 'center';
        $overview->attributes['class'] = 'overview ';
        $overview->summary = get_string('requestoverview', 'reservation');
        $overview->data = array();

        $overview->head = array();
        $overview->head[] = get_string('requests', 'reservation');
        for ($i=1;$i<count($counters);$i++) {
            $operatorstr = (!$counters[$i]->operator)?get_string('equal', 'reservation'):get_string('notequal', 'reservation');
            $overview->head[] = $counters[$i]->fieldname.' '.$operatorstr.' '.$counters[$i]->matchvalue;
        }

        $columns = array();
        $limitdetailstr = '';
        $total = $reservation->maxrequest;
        if (!empty($reservation->overbook) && ($reservation->maxrequest>0)) {
            $overbookseats = round($reservation->maxrequest * $reservation->overbook / 100);
            $limitdetailstr = ' ('.$reservation->maxrequest.'+'.html_writer::tag('span', 
                                                                                 $overbookseats,
                                                                                 array('class' => 'overbooked')).')';
            $total += $overbookseats;
        }
        $columns[] = $counters[0]->count.'/'.($reservation->maxrequest>0?$total:'&infin;').$limitdetailstr;
        for ($i=1;$i<count($counters);$i++) {
            $limitdetailstr= '';
            $total = $counters[$i]->requestlimit;
            if (!empty($reservation->overbook)) {
                $overbookseats = round($counters[$i]->requestlimit * $reservation->overbook / 100);
                $limitdetailstr = ' ('.$counters[$i]->requestlimit.'+'.html_writer::tag('span', 
                                                                                        $overbookseats,
                                                                                        array('class' => 'overbooked')).')';
                $total += $overbookseats;
            }
            $columns[] = $counters[$i]->count.'/'.$total.$limitdetailstr;
        }
        $overview->data[] = $columns;
        echo html_writer::tag('div', html_writer::table($overview));
        reservation_print_tabs($reservation, $mode);
    }

    if (empty($download)) {
        /// Show reservation form
        if (($mode == 'manage') && has_capability('mod/reservation:manualreserve',$context)) {
            if ((($reservation->maxrequest == 0) || ($available > 0) || ($available+$overbook > 0)) &&
                ($now < $reservation->timestart)) {
                if (isset($addableusers) && !empty($addableusers)) {
                    $html = '';
                    $reserveurl = new moodle_url('/mod/reservation/reserve.php', array('id'=>$cm->id));
                    $html .= html_writer::start_tag('form', array('id' => 'manualreserve',
                                                                  'enctype' =>'multipart/form-data',
                                                                  'method' => 'post',
                                                                  'action' => $reserveurl));
                    $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                                   'name' => 'reservation',
                                                                   'value' => $reservation->id));
                    $html .= html_writer::start_tag('div');
                    $html .= html_writer::tag('label',
                                              get_string('addparticipant', 'reservation').'&nbsp;',
                                              array('for' => 'newparticipant', 'class' => 'addparticipant'));
                    $html .= html_writer::select($addableusers,'newparticipant');
                    $html .= html_writer::end_tag('div');
                    if ($reservation->note == 1) {
                        $html .= html_writer::start_tag('div');
                        $html .= html_writer::tag('label', 
                                                  get_string('note', 'reservation'),
                                                  array('for' => 'note', 'class' => 'note'));
                        $html .= html_writer::tag('textarea','', array('name' => 'note', 'rows' => '5', 'cols' => '30'));
                        $html .= html_writer::end_tag('div');
                    }
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'reserve',
                                                                   'value' => get_string('reserve', 'reservation')));
                    $html .= html_writer::end_tag('form');

                    echo html_writer::tag('div', $html, array('class' => 'manualreserve'));
                }
            }
        } else if (has_capability('mod/reservation:reserve',$context)) {
            /// Display general infos messages and student submission button
            if ($result = reservation_get_availability($reservation,$counters,$available)) {
                $available = $result->available;
                $overbook = $result->overbook;
            }
            //display reservation availability
            echo html_writer::start_tag('div', array('class' => 'availability')) ;

            $cr = reservation_reserved_on_connected($reservation, $USER->id);

            if (($now >= $reservation->timeopen) && ($now <= $reservation->timeclose) && ($cr === false)) {
                if (!isset($currentuser)) {
                    $html = '';
                    if (($reservation->maxrequest == 0) && ($available > 0)) {
                        $html = get_string('availablerequests', 'reservation');
                    } else if (($available > 0) || ($overbook+$available > 0)) {
                        $overbookedstring = '';
                        if ($available > 0) {
                            $html = get_string('availablerequests', 'reservation').': '.$available;
                        } else {
                            $html =  html_writer::tag('span',
                                                      get_string('overbookonly', 'reservation'),
                                                      array('class' => 'overbooked'));
                        }
                    } else {
                        $html = get_string('nomorerequest', 'reservation');
                    }
                    echo html_writer::tag('div', $html);
                }

                $html = '';
                $reserveurl = new moodle_url('/mod/reservation/reserve.php', array('id'=>$cm->id));
                $html .= html_writer::start_tag('form', array('id' => 'reserve',
                                                              'enctype' =>'multipart/form-data',
                                                              'method' => 'post',
                                                              'action' => $reserveurl,
                                                              'class' => 'reserve'));
                $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                               'name' => 'reservation',
                                                               'value' => $reservation->id));
                if (isset($currentuser) && ($currentuser->number > 0)) {
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'cancel',
                                                                   'value' => get_string('reservecancel', 'reservation')));
                } else if ((($reservation->maxrequest == 0) && ($available > 0)) || ($available > 0) || ($available+$overbook > 0)) {
//                    if ($available+$overbook > 0) {
//                        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'overbook', 'value' => 'true'));
//                    }
                    if ($reservation->note == 1) {
                        $html .= html_writer::start_tag('div');
                        $html .= html_writer::tag('label',
                                                  get_string('note', 'reservation'),
                                                  array('for' => 'note', 'class' => 'note'));
                        $html .= html_writer::tag('textarea','', array('name' => 'note', 'rows' => '5', 'cols' => '30'));
                        $html .= html_writer::end_tag('div');
                    }
                    $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'name' => 'reserve',
                                                                   'value' => get_string('reserve', 'reservation')));
                }
                $html .= html_writer::end_tag('form');
                echo html_writer::tag('div', $html, array('class' => 'reserve'));
            } else {
                if ($cr !== false) {
                    $linktext = $cr->coursename . ': ' . $cr->name;
                    if (isset($CFG->reservation_connect_to) && ($CFG->reservation_connect_to == 'site')) {
                        $linktext = $displaylist[$cr->category] .'/'. $linktext;
                    }

                    $linkurl = new moodle_url('/mod/reservation/view.php',array('id' => $cr->id));
                    $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));

                    $html = get_string('reservedonconnected', 'reservation', $link);
                    echo html_writer::tag('p', $html);
                }
            }

            if (isset($currentuser) && ($currentuser->number > 0)) {
                if ($now > $reservation->timeclose) {
                    echo $OUTPUT->box(get_string('justbooked', 'reservation',$currentuser->number).$currentuser->note,'center');
                    if (!empty($currentuser->grade)) {
                        echo $OUTPUT->box($currentuser->grade);
                    }
                } else { 
                    echo $OUTPUT->box(get_string('alreadybooked', 'reservation').$currentuser->note,'center');
                }
            }
            echo html_writer::end_tag('div');
        } 

        /// Display requests table
        if (has_capability('mod/reservation:viewrequest',$context) || (($reservation->showrequest == 1) &&
           ($now > $reservation->timeclose) && (is_enrolled($coursecontext)))) {
            echo $OUTPUT->box_start('center');

            if (has_capability('mod/reservation:viewrequest',$context)) {
                if (groups_get_all_groups($course->id) && ($groupmode == SEPARATEGROUPS)) { 
                    groups_print_activity_menu($cm, $url);
                }

                /// Display teacher view mode button
                if (!empty($requests) && ($counters[0]->deletedrequests > 0)) {
                    $html = html_writer::start_tag('form', array('enctype' =>'multipart/form-data',
                                                                 'method' => 'post',
                                                                 'action' => $url,
                                                                 'id' => 'viewtype'));
                    $html .= html_writer::start_tag('fieldset');
                    if ($view == 'full') {
                        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'clean'));
                        $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                       'name' => 'save',
                                                                       'value' => get_string('cleanview', 'reservation')));
                    } else if ($counters[0]->deletedrequests > 0) {
                        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'full'));
                        $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                       'name' => 'save',
                                                                       'value' => get_string('fullview', 'reservation')));
                    }
                    $html .= html_writer::end_tag('fieldset');
                    $html .= html_writer::end_tag('form');
                    echo html_writer::tag('div', $html, array('class' => 'viewtype'));
                }
                echo $OUTPUT->heading(get_string('reservations', 'reservation'));
            }

            if ($norequest) { 
                if (has_capability('mod/reservation:viewrequest',$context)) {
                    echo $OUTPUT->heading(get_string('noreservations', 'reservation'));
                }
            } else {
                echo html_writer::start_tag('div', array('id' => 'tablecontainer'));
                if (($mode == 'manage') && has_capability('mod/reservation:viewrequest',$context)) { 
                    echo html_writer::start_tag('form', array('id' => 'requestactions',
                                                              'enctype' =>'multipart/form-data',
                                                              'method' => 'post',
                                                              'action' => $url,
                                                              'onsubmit' => 'return ((this.action[this.action.selectedIndex].value != "delete") || confirm("'.format_string(get_string('confirmdelete','reservation')).'"));'));
                    echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                               'name' => 'sesskey',
                                                               'value' => $USER->sesskey));
                    if (isset($view) && !empty($view)) {
                        echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                                   'name' => 'view',
                                                                   'value' => $view));
                    }
                }

                $table->start_output();

                foreach ($rows as $row) {
                    $table->add_data($row);
                }

                $table->finish_output();

                if (($mode == 'manage') && has_capability('mod/reservation:viewrequest',$context) && 
                   ((($reservation->maxgrade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0)) 
                    || ($counters[0]->count > 0) || ($counters[0]->deletedrequests > 0))) {
                    if  (($reservation->maxgrade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0)) {
                        $html = html_writer::empty_tag('input', array('type' => 'submit',
                                                                      'name' => 'savegrades',
                                                                      'value' => get_string('save', 'reservation')));
                        echo html_writer::tag('div', $html, array('class' => 'savegrades'));
                    }
                    /// Print "Select all" etc.
                    if (!empty($actions) && (($counters[0]->count > 0) ||
                                             (($view== 'full') && ($counters[0]->deletedrequests > 0)))) {
                        $html = '';
                        $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                        'onclick' => 'checkall()',
                                                        'value' => get_string('selectall')));
                        $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                                       'onclick' => 'checknone()',
                                                                       'value' => get_string('deselectall')));
                        $html .= html_writer::select($actions, 'action', '0', 
                                                     array('0' => get_string('withselected', 'reservation')));
                        $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                                       'name' => 'selectedaction',
                                                                       'value' => get_string('ok')));
                        echo html_writer::tag('div', $html, array('class' => 'form-buttons'));
                    }

                    echo html_writer::end_tag('form');
                }
                echo html_writer::end_tag('div');
            }
            echo $OUTPUT->box_end();
         } 

        /// Finish the page
        echo $OUTPUT->footer($course);
    } else if (!$norequest) {
        $table->start_output();
   
        foreach ($rows as $row) {
            $table->add_data($row);
        }
  
        $table->finish_output();
    }
?>

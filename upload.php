<?php
    require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/csvlib.class.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/course/modlib.php');
    require_once('lib.php');
    require_once('locallib.php');
    require_once('uploadform.php');

    $iid         = optional_param('iid', '', PARAM_INT);
    $previewrows = optional_param('previewrows', 10, PARAM_INT);

    @set_time_limit(60*60); // 1 hour should be enough
    raise_memory_limit(MEMORY_HUGE);

    require_login();
    admin_externalpage_setup('managemodules');

    $systemcontext = context_system::instance();

    require_capability('mod/reservation:uploadreservations', $systemcontext);

    $returnurl = new moodle_url('/mod/reservation/upload.php');

    $FIELDS = array('course','section','name','intro','location','teachers','timestart','timeend','maxgrade','timeopen','timeclose','maxrequest','dummy');
    $REQUIRED_FIELDS = array('section','name','timestart');

    $errorstr = get_string('error');

    if (empty($iid)) {
        $mform = new reservation_upload_form();
    
        if ($formdata = $mform->get_data()) {
            $iid = csv_import_reader::get_new_iid('uploadreservation');
            $cir = new csv_import_reader($iid, 'uploadreservation');
            $content = $mform->get_file_content('reservationsfile');
    
            $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
            unset($content);
    
            if ($readcount === false) {
                print_error('csvloaderror', 'error', $returnurl);
            } else if ($readcount == 0) {
                print_error('csvemptyfile', 'error', $returnurl);
            }
            // test if columns ok
            $filecolumns = reservation_validate_upload_columns($cir, $FIELDS, $REQUIRED_FIELDS, $returnurl);
        } else {
            echo $OUTPUT->header();
    
            echo $OUTPUT->heading_with_help(get_string('upload', 'reservation'), 'upload', 'reservation');
    
            $mform->display();
            echo $OUTPUT->footer();
            die;
        }
    }
    
    if (!empty($iid)) {
        $cir = new csv_import_reader($iid, 'uploadreservation');
        $filecolumns = reservation_validate_upload_columns($cir, $FIELDS, $REQUIRED_FIELDS, $returnurl);

        $mform = new reservation_upload_confirm_form(null, array('columns'=>$filecolumns, 'data'=>array('iid'=>$iid, 'previewrows'=>$previewrows)));
        // If a file has been uploaded, then process it
        if ($formdata = $mform->is_cancelled()) {
            $cir->cleanup(true);
            redirect($returnurl);

        } else if ($formdata = $mform->get_data()) {
            // Print the header
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('uploadreservationsresult', 'reservation'));

            $courses = array();

            $cir->init();
            $linenum = 1; //column header is first line
        
            // init upload progress tracker
            $upt = new ur_progress_tracker();
            $upt->start(); // start table
        
            while ($line = $cir->next()) {
                $upt->flush();
                $linenum++;
        
                $upt->track('line', $linenum);
        
                $data = new stdClass();
                // add fields to user object
                foreach ($line as $keynum => $value) {
                    if (!isset($filecolumns[$keynum])) {
                        // this should not happen
                        continue;
                    }
                    $key = $filecolumns[$keynum];
                    $data->$key = trim($value);

                    if (in_array($key, $upt->columns)) {
                        // default value in progress tracking table, can be changed later
                        $upt->track($key, s($value), 'normal');
                    }
                }
                $noerror = true;
                foreach($REQUIRED_FIELDS as $requiredfield) {
                    if (!isset($data->$requiredfield) || empty($data->$requiredfield)) {
                        $upt->track('status', get_string('missingfield', 'error', $requiredfield), 'error');
                        $upt->track($requiredfield, $errorstr, 'error');
                        $noerror = false;
                    }
                }
                if (!isset($data->course)) {
                    $courseshortname = optional_param('course', '', PARAM_RAW);
                    if (!empty($courseshortname)) {
                        $data->course = $courseshortname;
                        $upt->track('course', s($courseshortname), 'normal');
                    } else {
                        $noerror = false;
                    }
                }
                        
                if ($noerror) {
                    $cm = new stdClass();

                    if (isset($formdata->note)) {
                        $data->note = $formdata->note;
                    }
                    if (!$course = $DB->get_record('course', array('shortname' => $data->course))) {
                        $upt->track('course', $errorstr, 'error');
                    } else {
                        // compartibility with course formats using field 'numsections'
                        $courseformatoptions = course_get_format($course)->get_format_options();
                        if (array_key_exists('numsections', $courseformatoptions) && 
                            $data->section > $courseformatoptions['numsections'] || $data->section < 0) {
                            $upt->track('section', $errorstr, 'error');
                        } else {
                            $cw = get_fast_modinfo($course->id)->get_section_info($data->section);
                            
                            $reservation = new stdClass();
                            // Create the course module
                            $reservation->cmidnumber = null;
                            $reservation->section = $data->section;
                            
                            // Create the reservation database entry
                            $reservation->course = $course->id;
                            $reservation->name = $data->name;
                            if (isset($data->intro) && !empty($data->intro)) {
                                $reservation->intro = $data->intro;
                                $reservation->introformat = FORMAT_MOODLE;
                            } else {
                                $reservation->intro = '';
                                $reservation->introformat = FORMAT_MOODLE;
                            }
          
                            if (isset($data->teachers) && !empty($data->teachers)) {
                                $teachersmail = explode(':',$data->teachers);
                                $teachers = array();
                                foreach ($teachersmail as $teachermail) {
                                    if ($teachermail == clean_param($teachermail, PARAM_EMAIL)) {
                                        if ($teacher = $DB->get_record('user', array('email' => $teachermail))) {
                                            $teachers[] = $teacher->id;
                                        } else {
                                            $upt->track('teachers', $errorstr, 'error');
                                        }
                                    } else {
                                        $upt->track('teachers', $errorstr, 'error');
                                    }
                                }
                                $reservation->teachers = $teachers;
                            }
         
                            $timedate = strtotime($data->timestart);
                            if ($timedate === false) {
                                $upt->track('timestart', $errorstr, 'error');
                            } else {
                                $reservation->timestart = $timedate;
                            }
            
                            $reservation->timeend = 0;
                            if (isset($data->timeend) && !empty($data->timeend)) {
                                $timedate = strtotime($data->timeend);
                                if ($timedate === false) {
                                    $upt->track('timeend', $errorstr, 'error');
                                } else {
                                    $reservation->timeend = $timedate;
                                }
                            }
            
                            $reservation->maxgrade = 0;
                            if (isset($data->maxgrade) && !empty($data->maxgrade)) {
                                $reservation->maxgrade = $data->maxgrade;
                            }
            
                            $reservation->timeopen = 0;
                            if (isset($data->timeopen) && !empty($data->timeopen)) {
                                $timedate = strtotime($data->timeopen);
                                if ($timedate === false) {
                                    $upt->track('timeopen', $errorstr, 'error');
                                } else {
                                    $reservation->timeopen = $timedate;
                                }
                            }
            
                            $date = getdate($reservation->timestart);
                            $reservation->timeclose = mktime(00, 00, 00, $date['mon'], $date['mday'], $date['year']);
                            if (isset($data->timeclose) && !empty($data->timeclose)) {
                                $timedate = strtotime($data->timeclose);
                                if ($timedate === false) {
                                    $upt->track('timeclose', $errorstr, 'error');
                                } else {
                                    $reservation->timeclose = $timedate;
                                }
                            }
            
                            $reservation->locationtext = '';
                            if (isset($data->location) && !empty($data->location)) {
                                $reservation->locationtext = $data->location;
                            }
            
                            $reservation->note = 0;
                            if (isset($data->note) && !empty($data->note)) {
                                $reservation->note = $data->note;
                            }
            
                            $reservation->maxrequest = 0;
                            if (isset($data->maxrequest) && !empty($data->maxrequest)) {
                                $reservation->maxrequest = $data->maxrequest;
                            }
            
                            $reservation->visible = $cw->visible;
                            $reservation->instance = 0;
                            $reservation->field_1 = '-';

                            $reservation->module = $DB->get_field('modules', 'id', array('name' => 'reservation', 'visible' => 1));
                            $reservation->modulename = 'reservation'; 

                            add_moduleinfo($reservation, $course);
/*                            
                            $reservation->instance = reservation_add_instance($reservation);
                            
                            // Update the 'instance' field for the course module
                            $DB->set_field('course_modules', 'instance', $reservation->instance, array('id'=>$reservation->coursemodule));
                            
                            // Add the reservation to the correct section
                            $sectionid = course_add_cm_to_section($reservation->course, $reservation->coursemodule, $reservation->section);
                            $DB->set_field('course_modules', 'section', $sectionid, array('id'=>$reservation->coursemodule));
                            
                            set_coursemodule_visible($reservation->coursemodule, $reservation->visible);
                            
                            // Trigger mod_created event with information about this module.
                            $eventdata = new stdClass();
                            $eventdata->modulename = 'reservation';
                            $eventdata->name       = $reservation->name;
                            $eventdata->cmid       = $reservation->coursemodule;
                            $eventdata->courseid   = $course->id;
                            $eventdata->userid     = $USER->id;
                            events_trigger('mod_created', $eventdata);
                            
                            add_to_log($course->id, "course", "add mod",
                                       "../mod/reservation/view.php?id=$reservation->coursemodule",
                                       "Reservation $reservation->instance");
                            add_to_log($course->id, 'reservation', "add",
                                       "view.php?id=$reservation->coursemodule",
                                       "$reservation->instance", $reservation->coursemodule);
*/
                            $courses[$course->id] = $course->id;
                        }
                    }
                }
            }

            /* Rebuild updated courses cache */
            if (count($courses) > 0) {
                foreach ($courses as $courseid) {
                    rebuild_course_cache($courseid);
                }
            }

            $upt->close(); 

            echo $OUTPUT->continue_button($returnurl);
            echo $OUTPUT->footer();
            die;
        } else {
            echo $OUTPUT->header();
    
            echo $OUTPUT->heading_with_help(get_string('upload', 'reservation'), 'upload', 'reservation');

            // NOTE: this is JUST csv processing preview, 
            //       we must not prevent import from here if there is something in the file!!
            //       this was intended for validation of csv formatting and encoding, not filtering the data!!!!
            //       we definitely must not process the whole file!
            
            // preview table data
            $data = array();
            $cir->init();
            $linenum = 1; //column header is first line
            $noerror = true; // Keep status of any error.
            $maxsection = 0;
            while ($linenum <= $previewrows and $fields = $cir->next()) {
                $linenum++;
                $rowcols = array();
                $rowcols['line'] = $linenum;
                foreach($fields as $key => $field) {
                    $rowcols[$filecolumns[$key]] = s($field);
                }
                $rowcols['status'] = array();
            
                if (isset($rowcols['course'])) {
                    $rowcols['course'] = trim($rowcols['course']);
                    if (empty($rowcols['course'])) {
                        $rowcols['status'][] = get_string('fieldrequired', 'error', 'course');
                        $noerror = false;
                    } else if ($course = $DB->get_record('course', array('shortname'=>$rowcols['course']))) {
                        $rowcols['course'] = html_writer::link(new moodle_url('/course/view.php', array('id'=>$course->id)), $course->fullname);
                        if (isset($rowcols['section'])) {
                            $rowcols['section'] = trim($rowcols['section']);
                            // compartibility with course formats using field 'numsections'
                            $courseformatoptions = course_get_format($course)->get_format_options();
                            if (empty($rowcols['section'])) {
                                $rowcols['status'][] = get_string('fieldrequired', 'error', 'section');
                                $noerror = false;
                            } else if (array_key_exists('numsections', $courseformatoptions) &&
                                       $rowcols['section'] > $courseformatoptions['numsections']) {
                                $rowcols['status'][] = get_string('badsection', 'reservation',$course->fullname);
                                $noerror = false;
                            }
                        }
                    } else {
                        $rowcols['status'][] = get_string('badcourse', 'reservation');
                        $noerror = false;
                    }
                }
            
                if (isset($rowcols['name'])) {
                    $rowcols['name'] = trim($rowcols['name']);
                    if (empty($rowcols['name'])) {
                        $rowcols['status'][] = get_string('fieldrequired', 'error', 'name');
                        $noerror = false;
                    }
                }
            
                if (isset($rowcols['teachers'])) {
                    $rowcols['teachers'] = trim($rowcols['teachers']);
                    if (!empty($rowcols['teachers'])) {
                        $teachersmail = explode(':',$rowcols['teachers']);
                        $teachers = array();
                        foreach ($teachersmail as $teachermail) {
                            if ($teachermail == clean_param($teachermail, PARAM_EMAIL)) {
                                if ($teacher = $DB->get_record('user', array('email' => $teachermail))) {
                                    $teachers[] = html_writer::link(new moodle_url('/user/view.php', array('id'=>$teacher->id)), fullname($teacher));
                                } else {
                                    $rowcols['status'][] = get_string('badteachers', 'reservation', $teachermail);
                                    $noerror = false;
                                }
                            } else {
                                $rowcols['status'][] = get_string('badteachersmail', 'reservation', $teachermail);
                                $noerror = false;
                            }
                        }
                        $rowcols['teachers'] = implode(', ', $teachers);
                    }
                }

                if (isset($rowcols['timestart'])) {
                    if (empty($rowcols['timestart'])) {
                        $rowcols['status'][] = get_string('fieldrequired', 'error', 'timestart');
                        $noerror = false;
                    } else {
                        $timestart = strtotime(trim($rowcols['timestart']));
                        if ($timestart === false) {
                           $rowcols['status'][] = get_string('badtimestart', 'reservation');
                           $noerror = false;
                        }
                    }
                }
            
                if (isset($rowcols['timeend']) && !empty($rowcols['timeend'])) {
                    $timeend = strtotime(trim($rowcols['timeend']));
                    if ($timeend === false) {
                       $rowcols['status'][] = get_string('badtimeend', 'reservation');
                       $noerror = false;
                    }
                }

                if (isset($rowcols['timeopen']) && !empty($rowcols['timeopen'])) {
                    $timeopen = strtotime(trim($rowcols['timeopen']));
                    if ($timeopen === false) {
                       $rowcols['status'][] = get_string('badtimeopen', 'reservation');
                       $noerror = false;
                    }
                }

                if (isset($rowcols['timeclose']) && !empty($rowcols['timeclose'])) {
                    $timeclose = strtotime(trim($rowcols['timeclose']));
                    if ($timeclose === false) {
                       $rowcols['status'][] = get_string('badtimeclose', 'reservation');
                       $noerror = false;
                    }
                }

                if (isset($rowcols['section'])) {
                    $rowcols['section'] = trim($rowcols['section']);
                    if (empty($rowcols['section']) && (!isset($rowcols['course']) || empty($rowcols['course']))) {
                        $rowcols['status'][] = get_string('fieldrequired', 'error', 'section');
                        $noerror = false;
                    } else {
                        $maxsection = max($maxsection, $rowcols['section']);
                    }
                }
            
                $rowcols['status'] = implode('<br />', $rowcols['status']);
                $data[] = $rowcols;
            }
            if ($fields = $cir->next()) {
                $data[] = array_fill(0, count($fields) + 2, '...');
            }
            $cir->close();

            $table = new html_table();
            $table->id = "urpreview";
            $table->attributes['class'] = 'generaltable';
            $table->tablealign = 'center';
            $table->summary = get_string('uploadreservationspreview', 'reservation');
            $table->head = array();
            $table->data = $data;
            
            $table->head[] = get_string('linenumber', 'reservation');
            foreach ($filecolumns as $column) {
                $table->head[] = $column;
            }
            $table->head[] = get_string('status');
            
            echo html_writer::tag('div', html_writer::table($table), array('class'=>'flexible-wrap'));
    
            // Print the form if valid values are available
            if ($noerror) {
                $mform = new reservation_upload_confirm_form(null, array('columns'=>$filecolumns, 'data'=>array('iid'=>$iid, 'previewrows'=>$previewrows, 'maxsection'=>$maxsection)));
                $mform->display();
            } else {
                echo $OUTPUT->continue_button($returnurl);
            }

            echo $OUTPUT->footer();
            die;
        }
    }
?>

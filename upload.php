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

// An hour should be enough.
@set_time_limit(3600);
raise_memory_limit(MEMORY_HUGE);

require_login();
admin_externalpage_setup('managemodules');

$systemcontext = context_system::instance();

require_capability('mod/reservation:uploadreservations', $systemcontext);

$returnurl = new moodle_url('/mod/reservation/upload.php');

$fields = array(
        'course',
        'section',
        'name',
        'intro',
        'location',
        'teachers',
        'timestart',
        'timeend',
        'maxgrade',
        'timeopen',
        'timeclose',
        'maxrequest',
        'dummy'
);

$requiredfields = array('section', 'name', 'timestart');

$errorstr = get_string('error');

if (empty($iid)) {
    $mformupload = new reservation_upload_form();

    if ($formdata = $mformupload->get_data()) {
        $iid = csv_import_reader::get_new_iid('uploadreservation');
        $cir = new csv_import_reader($iid, 'uploadreservation');
        $content = $mformupload->get_file_content('reservationsfile');

        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        unset($content);

        if ($readcount === false) {
            print_error('csvloaderror', 'error', $returnurl);
        } else if ($readcount == 0) {
            print_error('csvemptyfile', 'error', $returnurl);
        }
        // Test if columns are ok.
        $filecolumns = reservation_validate_upload_columns($cir, $fields, $requiredfields, $returnurl);
    } else {
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string('upload', 'reservation'), 'upload', 'reservation');

        $mformupload->display();
        echo $OUTPUT->footer();
        die;
    }
}

if (!empty($iid)) {
    $cir = new csv_import_reader($iid, 'uploadreservation');
    $filecolumns = reservation_validate_upload_columns($cir, $fields, $requiredfields, $returnurl);

    $formdata = array('iid' => $iid, 'previewrows' => $previewrows);
    $mformconfirm = new reservation_upload_confirm_form(null, array('columns' => $filecolumns, 'data' => $formdata));
    // If a file has been uploaded, then process it.
    if ($formdata = $mformconfirm->is_cancelled()) {
        $cir->cleanup(true);
        redirect($returnurl);

    } else if ($formdata = $mformconfirm->get_submitted_data()) {
        // Print the header.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('uploadreservationsresult', 'reservation'));

        $courses = array();

        $cir->init();
        $linenum = 1; // Column header is first line.

        // Init upload progress tracker.
        $upt = new ur_progress_tracker();
        $upt->start(); // Start table.

        while ($line = $cir->next()) {
            $upt->flush();
            $linenum++;

            $upt->track('line', $linenum);

            $data = new stdClass();
            // Add fields to user object.
            foreach ($line as $keynum => $value) {
                if (!isset($filecolumns[$keynum])) {
                    // This should not happen.
                    continue;
                }
                $key = $filecolumns[$keynum];
                $data->$key = trim($value);

                if (in_array($key, $upt->columns)) {
                    // Default value in progress tracking table, can be changed later.
                    $upt->track($key, s($value), 'normal');
                }
            }
            $noerror = true;
            foreach ($requiredfields as $requiredfield) {
                if (!isset($data->$requiredfield) || empty($data->$requiredfield)) {
                    $upt->track('status', get_string('missingfield', 'error', $requiredfield), 'error');
                    $upt->track($requiredfield, $errorstr, 'error');
                    $noerror = false;
                }
            }
            if (!isset($data->course)) {
                $courseshortname = optional_param('course', '', PARAM_RAW);
                if (!empty($courseshortname) && ($DB->get_record('course', array('shortname' => $courseshortname)))) {
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
                    // Compartibility with course formats using field 'numsections'.
                    $coursenumsections = course_get_format($course)->get_last_section_number();
                    if ($data->section > $coursenumsections || $data->section < 0) {
                        $upt->track('section', $errorstr, 'error');
                    } else {
                        $cw = get_fast_modinfo($course->id)->get_section_info($data->section);

                        $reservation = new stdClass();
                        // Create the course module.
                        $reservation->cmidnumber = null;
                        $reservation->section = clean_param($data->section, PARAM_INT);

                        // Create the reservation database entry.
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
                            $teachersmail = explode(':', $data->teachers);
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
                        if ($CFG->branch >= 32) {
                            $reservation->visibleoncoursepage = $cw->visible;
                        }
                        $reservation->instance = 0;
                        $reservation->field_1 = '-';

                        $reservation->module = $DB->get_field('modules', 'id', array('name' => 'reservation', 'visible' => 1));
                        $reservation->modulename = 'reservation';

                        add_moduleinfo($reservation, $course);

                        $courses[$course->id] = $course->id;
                    }
                }
            }
        }

        // Rebuild updated courses cache.
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

        // NOTE:
        // this is JUST csv processing preview,
        // we must not prevent import from here if there is something in the file!!
        // this was intended for validation of csv formatting and encoding, not filtering the data!!!!
        // we definitely must not process the whole file!

        // Preview table data.
        $data = array();
        $cir->init();
        $linenum = 1; // Column header is first line.
        $noerror = true; // Keep status of any error.
        $maxsection = 0;
        while ($linenum <= $previewrows and $fields = $cir->next()) {
            $linenum++;
            $rowcols = array();
            $rowcols['line'] = $linenum;
            foreach ($fields as $key => $field) {
                $rowcols[$filecolumns[$key]] = s($field);
            }
            $rowcols['status'] = array();

            if (isset($rowcols['course'])) {
                $rowcols['course'] = trim($rowcols['course']);
                if (empty($rowcols['course'])) {
                    $rowcols['status'][] = get_string('fieldrequired', 'error', 'course');
                    $noerror = false;
                } else if ($course = $DB->get_record('course', array('shortname' => $rowcols['course']))) {
                    $courseviewurl = new moodle_url('/course/view.php', array('id' => $course->id));
                    $rowcols['course'] = html_writer::link($courseviewurl, $course->fullname);
                    if (isset($rowcols['section'])) {
                        $rowcols['section'] = trim($rowcols['section']);
                        // Compartibility with course formats using field 'numsections'.
                        $courseformatoptions = course_get_format($course)->get_format_options();
                        if (empty($rowcols['section'])) {
                            $rowcols['status'][] = get_string('fieldrequired', 'error', 'section');
                            $noerror = false;
                        } else if (array_key_exists('numsections', $courseformatoptions) &&
                                   $rowcols['section'] > $courseformatoptions['numsections']) {
                            $rowcols['status'][] = get_string('badsection', 'reservation', $course->fullname);
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
                    $teachersmail = explode(':', $rowcols['teachers']);
                    $teachers = array();
                    foreach ($teachersmail as $teachermail) {
                        if ($teachermail == clean_param($teachermail, PARAM_EMAIL)) {
                            if ($teacher = $DB->get_record('user', array('email' => $teachermail))) {
                                $userviewurl = new moodle_url('/user/view.php', array('id' => $teacher->id));
                                $teachers[] = html_writer::link($userviewurl, fullname($teacher));
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
                $rowcols['section'] = clean_param(trim($rowcols['section']), PARAM_INT);
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

        echo html_writer::tag('div', html_writer::table($table), array('class' => 'flexible-wrap'));

        // Print the form if valid values are available.
        if ($noerror) {
            $mformconfirm->display();
        } else {
            echo $OUTPUT->continue_button($returnurl);
        }

        echo $OUTPUT->footer();
        die;
    }
}

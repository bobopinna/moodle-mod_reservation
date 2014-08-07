<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('locallib.php');

    $id = required_param('id', PARAM_INT);    // Course ID
    $timestartstr = required_param('timestart', PARAM_ALPHANUMEXT);    // timestart value
    $timeendstr = optional_param('timeend', '', PARAM_ALPHANUMEXT);    // timeend value 
    $location = optional_param('location', '', PARAM_TEXT);    // event location 
    $reservationid = optional_param('reservation', 0, PARAM_INT);    // reservation id 

    if ($id) {
        if (! $course = $DB->get_record('course', array('id' => $id))) {
            error('Course ID is incorrect');
        }
    }

    require_course_login($course);

    $coursecontext = context_course::instance($course->id);
  
    require_capability('moodle/course:manageactivities', $coursecontext);

    if (!isset($CFG->reservation_check_clashes)) {
        $CFG->reservation_check_clashes = 0;
    }

    if ($CFG->reservation_check_clashes) {
 
        if (!isset($CFG->reservation_min_duration)) {
            $CFG->reservation_min_duration = 3600; // an hour
        }
        if (!empty($timestartstr)) {
            $timearray = explode('-',$timestartstr);
            $timestart = make_timestamp($timearray[0], $timearray[1], $timearray[2], $timearray[3], $timearray[4], 0, 99, true);
        
            $timeend = $timestart + $CFG->reservation_min_duration;
        
            if (!empty($timeendstr)) {
                $timearray = explode('-',$timeendstr);
                $timeend = make_timestamp($timearray[0], $timearray[1], $timearray[2], $timearray[3], $timearray[4], 0, 99, true);
            }

            if ($timestart < $timeend) {
                if ($reservations = reservation_get_reservations_by_course($course->id, $location)) {
                    $strftimedaydatetime = get_string('strftimedatetime');
    
                    $collision_table = new html_table();
                    $collision_table->tablealign = 'center';
                    $collision_table->attributes['class'] = 'collisions ';
                    $collision_table->summary = get_string('clashesreport', 'reservation');
                    $collision_table->data = array();
    
                    $collision_table->head = array();
                    $collision_table->head[] = get_string('course');
                    $collision_table->head[] = get_string('modulename', 'reservation');
                    $collision_table->head[] = get_string('location', 'reservation');
                    $collision_table->head[] = get_string('timestart', 'reservation');
                    $collision_table->head[] = get_string('timeend', 'reservation');
                    foreach ($reservations as $reservation) {
                        $collision = false;
                        if ($reservationid != $reservation->id) {
                            $extimestart = $reservation->timestart;
                            $extimeend = $reservation->timeend;
                            if (empty($reservation->timeend)) {
                                $extimeend = $reservation->timestart + $CFG->reservation_min_duration;
                            }
                            /**
                             * Collision cases
                             *
                             * Existing        EXTS-----------EXTE
                             * Test 1               TS----------TE
                             * Test 2          TS----------TE
                             * Test 3     TS------------------------TE
                             * Test 4               TS----TE
                             * Test 5 (1+2+3+4)  TS-----------TE
                             */
                            if (($timestart >= $extimestart) && ($timestart < $extimeend) && ($timeend >= $extimeend)) {
                                $collision = true;
                            }
                            if (($timestart <= $extimestart) && ($timeend > $extimestart) && ($timeend <= $extimeend)) {
                                $collision = true;
                            }
                            if (($timestart <= $extimestart) && ($timeend >= $extimeend)) {
                                $collision = true;
                            }
                            if (($timestart >= $extimestart) && ($timeend <= $extimeend)) {
                                $collision = true;
                            }
                        }
                        if ($collision) {
                           $columns = array();
                           $columns[] = format_string($reservation->coursename);
                           $columns[] = format_string($reservation->name);
                           $columns[] = format_string($reservation->location);
                           $columns[] = userdate($reservation->timestart, $strftimedaydatetime);
                           if (!empty($reservation->timeend)) {
                               $columns[] = userdate($reservation->timeend, $strftimedaydatetime);
                           } else {
                               $columns[] = html_writer::tag('em', userdate($extimeend, $strftimedaydatetime), array('class' => 'stimed'));
                           }
                           $collision_table->data[] = $columns;
                        }
                    }
                    if (!empty($collision_table->data)) {
                        echo html_writer::table($collision_table);
                    }
                } 
            } else {
                echo html_writer::tag('span', get_string('err_timeendlower', 'reservation'));
            }
        }
    }
?>

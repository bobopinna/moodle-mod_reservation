<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('locallib.php');

    $id = required_param('id', PARAM_INT);    // Course Module ID, or
    $reserve = optional_param('reserve', NULL, PARAM_ALPHA);
    $cancel = optional_param('cancel', NULL, PARAM_ALPHA);
    $note = optional_param('note', NULL, PARAM_TEXT);
    $reservationid = optional_param('reservation', NULL, PARAM_INT);     // reservation ID

    if ($id) {
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
        error('Bad script calling');
        exit;
    }

    $url = new moodle_url('/mod/reservation/reserve.php', array('id'=>$cm->id));
    $PAGE->set_url($url);

    require_login($course->id,false,$cm);  
    
    /// Update reservation
    if ($reservationid == $reservation->id) {
        
        $context = context_module::instance($cm->id);

        if (isset($reserve)) {
            $now = time();

            // Manage manual and students reservation
            $request = new stdClass();
            $request->userid = NULL;
            $newparticipant = optional_param('newparticipant', NULL, PARAM_INT);     // new participant ID
            if (has_capability('mod/reservation:manualreserve',$context) && !empty($newparticipant)) {
                if ($now < $reservation->timestart) {
                    $request->userid = $newparticipant;
                } else {
                    $notice = 'reservationdenied';
                }
            } else if (has_capability('mod/reservation:reserve',$context) && ($now >= $reservation->timeopen) && ($now <= $reservation->timeclose)) {
                $request->userid = $USER->id;
            } else {
                $notice = 'reservationclosed';
            }

            // Count requests and check seats avalability
            if (!empty($request->userid)) {
                if ($DB->get_record('reservation_request', array('userid' => $request->userid, 'reservation' => $reservation->id, 'timecancelled' => '0'))) {
                    $notice = 'alreadybooked';
                } else {
                    $overbook = round($reservation->maxrequest * $reservation->overbook / 100);
                    $available = $CFG->reservation_max_requests;

                    // Get profile custom fields array
                    $customfields = reservation_get_profilefields();

                    // Set counters
                    $counters = reservation_setup_counters($reservation,$customfields);
                    // Set sublimits fields
                    $fields = reservation_setup_sublimit_fields($counters,$customfields);   
 
                    if (!($requests = reservation_get_requests($reservation, false, $fields)) || (count($requests) < ($reservation->maxrequest+$overbook)) || ($reservation->maxrequest == 0)) {
                        if (count($counters) - 1 > 0) {
                            if ($requests) {
                                foreach ($requests as $requestdata) {
                                    $counters[0]->count++;
                                    for ($i=1;$i<count($counters);$i++) {
                                        $fieldname = $counters[$i]->field;
                                            if (($requestdata->$fieldname == $counters[$i]->matchvalue) && !$counters[$i]->operator) {
                                            $counters[$i]->count++;
                                        } elseif (($requestdata->$fieldname != $counters[$i]->matchvalue) && $counters[$i]->operator) {
                                            $counters[$i]->count++;
                                        }
                                    }
                                }
                            }
                          
                            if ($reservation->maxrequest > 0) {
                                $available = min($reservation->maxrequest,($reservation->maxrequest-$counters[0]->count));
                            }
    
                            if ($USER->id == $request->userid) {
                                if ($result = reservation_get_availability($reservation,$counters,$available)) {
                                    $available = $result->available;
                                    $overbook = $result->overbook;
                                }
                            }
                        }
    /*
                        $request->overbooked = 0;
                        if (($available <= 0) && ($available+$overbook > 0)) {
                            $request->overbooked = 1;
                        }
    */
                        if (($available > 0) || ($available+$overbook > 0)) {
                            $request->reservation = $reservation->id;
                            $request->timecreated = time();
                            if (isset($request->userid) && !empty($request->userid)) {
                                if ($requestid = $DB->insert_record('reservation_request', $request)) {
                                    $usernote = new stdClass();
                                    if (($reservation->note == 1) && (!empty($note))) {
                                        $usernote->request = $requestid;
                                        $usernote->note = strip_tags($note);
                                        $DB->insert_record('reservation_note', $usernote);
                                    }
                                    $request = $DB->get_record('reservation_request', array('id' => $requestid));
                                    \mod_reservation\event\request_added::create_from_request($reservation, $context, $request, $usernote)->trigger();

                                    // Update completion state
                                    $completion=new completion_info($course);
                                    if ($completion->is_enabled($cm) && $reservation->completionreserved) {
                                        $completion->update_state($cm,COMPLETION_COMPLETE);
                                    }

                                    redirect ('view.php?id='.$cm->id, get_string('reserved', 'reservation'), 2);
                                } else {
                                    error('Database insertion error');
                                }
                            }
                        } else {
                            $notice = 'nomorerequest';
                        }
                    } else {
                        $notice = 'nomorerequest';
                    }
                }
            } else {
                $notice = 'reservationdenied';
            }
        } elseif (isset($cancel)) {
            if ($request = $DB->get_record('reservation_request', array('userid' => $USER->id, 'reservation' => $reservation->id, 'timecancelled' => '0'))) {
                $DB->set_field('reservation_request', 'timecancelled', time(), array('id' => $request->id));

                \mod_reservation\event\request_cancelled::create_from_request($reservation, $context, $request)->trigger();

                // Update completion state
                $completion=new completion_info($course);
                if ($completion->is_enabled($cm) && $reservation->completionreserved) {
                    $completion->update_state($cm,COMPLETION_INCOMPLETE);
                }
                redirect ('view.php?id='.$cm->id, get_string('reservationcancelled', 'reservation'), 2);
            } else {
                error('Bad script calling');
            }
        }
    } else {
        error('Bad script calling');
    }
    
/// Print the page header
    $strreservations = get_string('modulenameplural', 'reservation');
    $strreservation  = get_string('modulename', 'reservation');

    $pagetitle = strip_tags($course->shortname.': '.format_string($reservation->name));

    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    if (!empty($notice)) {
        notice(get_string($notice, 'reservation'),'view.php?id='.$cm->id);
    }

/// Finish the page
    echo $OUTPUT->footer($course);

?>

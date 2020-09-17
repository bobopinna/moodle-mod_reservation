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
 * A schedule task for reservation cron.
 *
 * @package   mod_reservation
 * @copyright 2020 Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_reservation\task;
defined('MOODLE_INTERNAL') || die();

/**
 * A schedule task for reservation cron.
 *
 * @copyright 2020 Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_reservation');
    }

    /**
     * Finds all notifications that have yet to be mailed out, and mails them
     * out to all teachers or students based on requirements.
     */
    public function execute() {
        global $CFG, $USER, $DB;

        // Notices older than 1 day will not be mailed.  This is to avoid the problem where
        // cron has not been running for a long time, and then suddenly people are flooded
        // with mail from the past few weeks or months.
        $timenow   = time();
        $endtime   = $timenow;
        $starttime = $endtime - 24 * 3600;   // One day earlier.

        $notifieslist = get_config('reservation', 'notifies');
        if ($notifieslist === false) {
            $notifieslist = 'teachers,students,grades';
        }
        $notifies = explode(',', $notifieslist);

        // Notify request grading to students.
        if ($requests = $this->get_unmailed_requests(0, $starttime)) {
            foreach ($requests as $key => $request) {
                if (! $DB->set_field('reservation_request', 'mailed', '1', array('id' => $request->id))) {
                    mtrace('Could not update the mailed field for request id '.$request->id.'.  Not mailed.');
                }
            }
        }

        if ($requests = $this->get_unmailed_requests($starttime, $endtime)) {
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

                    if (! $mod->visible) {
                        // Hold mail notification for hidden reservations until later.
                        continue;
                    }

                    $strreservations = get_string('modulenameplural', 'reservation');
                    $strreservation  = get_string('modulename', 'reservation');

                    $reservationinfo = new \stdClass();
                    $reservationinfo->teacher = fullname($teacher);
                    $reservationinfo->reservation = format_string($request->name, true);
                    $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;

                    $postsubject = $course->shortname.': '.$strreservations.': '.format_string($request->name, true);
                    $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($request->name, true)."\n";
                    $posttext .= '---------------------------------------------------------------------'."\n";
                    $posttext .= get_string('gradedmail', 'reservation', $reservationinfo);
                    $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                    $posthtml = '';

                    if ($user->mailformat == 1) {  // HTML.
                        $posthtml = '<p>';
                        $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$course->shortname.'</a> ->';
                        $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                                $strreservations.'</a> ->';
                        $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                                format_string($request->name, true).'</a>';
                        $posthtml .= '</p>';
                        $posthtml .= '<hr />';
                        $posthtml .= '<p>'.get_string('gradedmailhtml', 'reservation', $reservationinfo).'</p>';
                        $posthtml .= '<hr />';
                    }

                    if (! email_to_user($user, $teacher, $postsubject, $posttext, $posthtml)) {
                        mtrace('Error: reservation cron: Could not send out mail for request id '.$request->id.' to user '.
                               $user->id.' ('.$user->email.')');
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

        // Notify the end of reservation time with link for info to teachers and students.
        if ($reservations = $this->get_unmailed_reservations(0, $starttime)) {
            foreach ($reservations as $key => $reservation) {
                mtrace('Set unmailed reservation id '.$reservation->id.' as mailed.');
                if (! $DB->set_field('reservation', 'mailed', '1', array('id' => $reservation->id))) {
                    mtrace('Could not update the mailed field for reservation id '.$reservation->id.'.  Not mailed.');
                }
            }
        }

        if ($reservations = $this->get_unmailed_reservations($starttime, $endtime)) {
            foreach ($reservations as $reservation) {
                mtrace('Process reservation id '.$reservation->id.'.');
                // Mark as mailed just to prevent double mail sending.
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

                if (! $mod->visible) {
                    // Hold mail notification for hidden reservations until later.
                    continue;
                }

                $reservationinfo = new \stdClass();
                $reservationinfo->reservation = format_string($reservation->name, true);

                $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;

                if (in_array('teachers', $notifies)) {
                    $context = \context_module::instance($mod->id);
                    // Notify to teachers.
                    if (!empty($reservation->teachers)) {
                        $teachers = explode(',', $reservation->teachers);
                    } else {
                        // If no teachers are defined in reservation notify to all editing teachers and managers.
                        $teachers = array_keys(get_users_by_capability($context, 'mod/reservation:addinstance', 'u.id'));
                    }
                    if (!empty($teachers)) {
                        foreach ($teachers as $teacherid) {
                            mtrace('Processing reservation teacher '.$teacherid);
                            if (! $teacher = $DB->get_record('user', array('id' => $teacherid))) {
                                mtrace('Could not find user '.$teacherid);
                                continue;
                            } else if (has_capability('mod/reservation:reserve', $context, $teacherid)) {
                                continue;
                            }

                            $USER->lang = $teacher->lang;

                            $strreservations = get_string('modulenameplural', 'reservation');
                            $strreservation  = get_string('modulename', 'reservation');

                            $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name, true);
                            $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name, true)."\n";
                            $posttext .= '---------------------------------------------------------------------'."\n";
                            $posttext .= get_string('mail', 'reservation', $reservationinfo);
                            $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                            $posthtml = '';

                            if ($teacher->mailformat == 1) {  // HTML.
                                $posthtml = '<p>';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.
                                         $course->shortname.'</a> ->';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                                         $strreservations.'</a> ->';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                                         format_string($reservation->name, true).'</a>';
                                $posthtml .= '</p>';
                                $posthtml .= '<hr />';
                                $posthtml .= '<p>'.get_string('mailhtml', 'reservation', $reservationinfo).'</p>';
                                $posthtml .= '<hr />';
                            }

                            if (! email_to_user($teacher, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                                mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.
                                        ' to user '.$teacher->id.' ('.$teacher->email.')');
                            }
                        }
                    }
                }

                if (in_array('students', $notifies)) {
                    // Notify to students.
                    require_once($CFG->dirroot.'/mod/reservation/locallib.php');

                    $reservationinfo->url = $CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id;
                    if ($requests = reservation_get_requests($reservation)) {
                        foreach ($requests as $request) {
                            mtrace('Processing reservation user '.$request->userid);
                            if (! $user = $DB->get_record('user', array('id' => $request->userid))) {
                                mtrace('Could not find user '.$user->id);
                                continue;
                            }

                            $USER->lang = $user->lang;

                            $strreservations = get_string('modulenameplural', 'reservation');
                            $strreservation  = get_string('modulename', 'reservation');

                            $postsubject = $course->shortname.': '.$strreservations.': '.format_string($reservation->name, true);
                            $posttext  = $course->shortname.' -> '.$strreservations.' -> '.format_string($reservation->name, true)."\n";
                            $posttext .= '---------------------------------------------------------------------'."\n";
                            $posttext .= get_string('mailrequest', 'reservation', $reservationinfo);
                            $posttext .= "\n".'---------------------------------------------------------------------'."\n";
                            $posthtml = '';

                            if ($user->mailformat == 1) {  // HTML.
                                $posthtml = '<p>';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.
                                        $course->shortname.'</a> ->';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/index.php?id='.$course->id.'">'.
                                        $strreservations.'</a> ->';
                                $posthtml .= '<a href="'.$CFG->wwwroot.'/mod/reservation/view.php?id='.$mod->id.'">'.
                                        format_string($reservation->name, true).'</a>';
                                $posthtml .= '</p>';
                                $posthtml .= '<hr />';
                                $posthtml .= '<p>'.get_string('mailrequesthtml', 'reservation', $reservationinfo).'</p>';
                                $posthtml .= '<hr />';
                            }

                            if (! email_to_user($user, $CFG->noreplyaddress, $postsubject, $posttext, $posthtml)) {
                                mtrace('Error: reservation cron: Could not send out mail for reservation id '.$reservation->id.
                                        ' to user '.$user->id.' ('.$user->email.')');
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Return list of closed reservation that have not been mailed out to assigned teachers.
     *
     * @param int $starttime The date and time to search from
     * @param int $endtime The date and time to search to
     * @return array list of closed reservation
     */
    private function get_unmailed_reservations($starttime, $endtime) {
        global $DB;

        return $DB->get_records_sql('SELECT res.*
                                       FROM {reservation} res
                                      WHERE res.mailed = 0
                                        AND res.timeclose <= :endtime
                                        AND res.timeclose >= :starttime',
                                    array('endtime' => $endtime, 'starttime' => $starttime));
    }

    /**
     * Return list of graded requests that have not been mailed out.
     *
     * @param int $starttime The date and time to search from
     * @param int $endtime The date and time to search to
     * @return array list of graded request
     */
    private function get_unmailed_requests($starttime, $endtime) {
        global $DB;

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
}

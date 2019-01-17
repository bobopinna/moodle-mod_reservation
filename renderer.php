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
 * This file contains renderers to display contents of this module
 *
 * @package mod_reservation
 * @copyright 2018 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
defined('MOODLE_INTERNAL') || die();

/**
 * Moodle renderer used to display elements of the reservation module
 *
 * @package   mod_reservation
 * @copyright 2018 Roberto Pinna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class mod_reservation_renderer extends plugin_renderer_base {

    /**
     * Print view page tabs
     *
     * @param stdClass $reservation
     * @param string $mode the current selected tab (overview or manage)
     */
    public function display_tabs($reservation, $mode) {
        $tabs = array();
        $row = array();

        $baseurl = 'view.php';
        $queries = array('r' => $reservation->id);

        $queries['mode'] = 'overview';
        $url = new moodle_url($baseurl, $queries);
        $row[] = new tabobject('overview', $url, get_string('overview', 'reservation'));

        $queries['mode'] = 'manage';
        $url = new moodle_url($baseurl, $queries);
        $row[] = new tabobject('manage', $url, get_string('manage', 'reservation'));

        $tabs[] = $row;

        // Print out the tabs and continue!
        print_tabs($tabs, $mode, null, null);
    }

    /**
     * Print html formatted reservation info
     *
     * @param stdClass $reservation
     * @param stdClass $cmid
     */
    public function print_info($reservation, $cmid) {
        $now = time();

        $coursecontext = context_course::instance($reservation->course);

        echo html_writer::tag('div', format_module_intro('reservation', $reservation, $cmid), array('class' => 'intro'));
        // Retrive teachers list.
        $teachername = reservation_get_teacher_names($reservation, $cmid);
        if (!empty($teachername)) {
            $teacherroles = get_archetype_roles('editingteacher');
            $teacherrole = array_shift($teacherroles);
            $teacherstr = role_get_name($teacherrole, $coursecontext);
            echo html_writer::start_tag('div', array('class' => 'teachername'));
            echo html_writer::tag('label', $teacherstr.': ', array('class' => 'bold'));
            echo html_writer::tag('span', $teachername);
            echo html_writer::end_tag('div');
        }
        if (!empty($reservation->location)) {
            echo html_writer::start_tag('div', array('class' => 'location'));
            echo html_writer::tag('label', get_string('location', 'reservation').': ', array('class' => 'bold'));
            echo html_writer::tag('span', $reservation->location);
            echo html_writer::end_tag('div');
        }
        $strftimedaydatetime = get_string('strftimedaydatetime');
        echo html_writer::start_tag('div', array('class' => 'timestart'));
        if (!empty($reservation->timeend)) {
            echo html_writer::tag('label', get_string('timestart', 'reservation').': ', array('class' => 'bold'));
        } else {
            echo html_writer::tag('label', get_string('date').': ', array('class' => 'bold'));
        }
        echo html_writer::tag('span', userdate($reservation->timestart, $strftimedaydatetime));
        echo html_writer::end_tag('div');
        if (!empty($reservation->timeend)) {
            echo html_writer::start_tag('div', array('class' => 'timeend'));
            echo html_writer::tag('label', get_string('timeend', 'reservation').': ', array('class' => 'bold'));
            echo html_writer::tag('span', userdate($reservation->timeend, $strftimedaydatetime));
            echo html_writer::end_tag('div');
        }

        echo html_writer::empty_tag('hr', array('class' => 'clearfloat'));

        if (!empty($reservation->timeopen)) {
            echo html_writer::start_tag('div', array('class' => 'timeopen'));
            echo html_writer::tag('label', get_string('timeopen', 'reservation').': ', array('class' => 'bold'));
            if ($now < $reservation->timeopen) {
                echo html_writer::tag('span',
                                      userdate($reservation->timeopen, $strftimedaydatetime),
                                      array('class' => 'notopened'));
                echo html_writer::tag('span', ' '.get_string('reservationnotopened', 'reservation'),
                                      array('class' => 'alert bg-warning'));
            } else {
                echo html_writer::tag('span', userdate($reservation->timeopen, $strftimedaydatetime));
            }
            echo html_writer::end_tag('div');
        }
        echo html_writer::start_tag('div', array('class' => 'timeclose'));
        echo html_writer::tag('label', get_string('timeclose', 'reservation').': ', array('class' => 'bold'));
        if ($now > $reservation->timeclose) {
            echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime), array('class' => 'notopened'));
            echo html_writer::tag('span', ' '.get_string('reservationclosed', 'reservation'), array('class' => 'alert bg-warning'));
        } else {
            echo html_writer::tag('span', userdate($reservation->timeclose, $strftimedaydatetime));
        }
        echo html_writer::end_tag('div');
    }

    /**
     * Print connected reservations
     *
     * @param stdClass $reservation
     */
    public function print_connected($reservation) {
        global $CFG;

        $connectto = get_config('reservation', 'connect_to');
        if ($connectto == 'site') {
            require_once($CFG->libdir.'/coursecatlib.php');
            $displaylist = coursecat::make_categories_list();
        }
        // Show connected reservations.
        if ($connectedreservs = reservation_get_connected($reservation)) {
            $connectedlist = html_writer::tag('label',
                                               get_string('connectedto', 'reservation').': ',
                                               array('class' => 'bold'));
            $connectedlist .= html_writer::start_tag('ul', array('class' => 'connectedreservations'));
            foreach ($connectedreservs as $cr) {
                $linktext = $cr->coursename . ': ' . $cr->name;
                if ($connectto == 'site') {
                    $linktext = $displaylist[$cr->category] .'/'. $linktext;
                }
                $linkurl = new moodle_url('/mod/reservation/view.php', array('r' => $cr->id));
                $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));
                $connectedlist .= html_writer::tag('li', $link);
            }
            $connectedlist .= html_writer::end_tag('ul');

            echo html_writer::tag('div', $connectedlist, array('class' => 'connected'));
        }
    }

    /**
     * Print reservation availability and counters
     *
     * @param stdClass $reservation
     * @param array    $counters
     */
    public function print_counters($reservation, $counters) {
        // Show seats availability.
        $overview = new html_table();
        $overview->tablealign = 'center';
        $overview->attributes['class'] = 'requestoverview';
        $overview->summary = get_string('requestoverview', 'reservation');
        $overview->data = array();

        $overview->head = array();
        $overview->head[] = get_string('requests', 'reservation');
        for ($i = 1; $i < count($counters); $i++) {
            $operatorstr = (!$counters[$i]->operator) ? get_string('equal', 'reservation') : get_string('notequal', 'reservation');
            $overview->head[] = $counters[$i]->fieldname.' '.$operatorstr.' '.$counters[$i]->matchvalue;
        }

        $columns = array();
        $limitdetailstr = '';
        $total = $reservation->maxrequest;
        if (!empty($reservation->overbook) && ($reservation->maxrequest > 0)) {
            $overbookseats = round($reservation->maxrequest * $reservation->overbook / 100);
            $limitdetailstr = ' ('.$reservation->maxrequest.'+'.html_writer::tag('span',
                                                                                 $overbookseats,
                                                                                 array('class' => 'overbooked')).')';
            $total += $overbookseats;
        }
        $columns[] = $counters[0]->count.'/'.(($reservation->maxrequest > 0) ? $total : '&infin;').$limitdetailstr;
        for ($i = 1; $i < count($counters); $i++) {
            $limitdetailstr = '';
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

        echo html_writer::tag('div', html_writer::table($overview), array('class' => 'counters'));
    }

    /**
     * Gets note field
     *
     * @param object $reservation reservation object
     * @return string The request note form field
     */
    public function display_note_field($reservation) {
        $html = '';
        if ($reservation->note == 1) {
            $html .= html_writer::start_tag('div', array('class' => 'usernote'));
            $html .= html_writer::tag('label',
                                      get_string('note', 'reservation'),
                                      array('for' => 'note', 'class' => 'note'));
            $html .= html_writer::tag('textarea', '', array('name' => 'note', 'rows' => '5', 'cols' => '30'));
            $html .= html_writer::end_tag('div');
        }

        return $html;
    }

}

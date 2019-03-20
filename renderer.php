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
     * Print the link to connected reservation where the user is reserved
     *
     * @param stdClass $cr connected reservation data
     */
    public function print_reserved_on_connected($cr) {
        $linktext = $cr->coursename . ': ' . $cr->name;
        $connectto = get_config('reservation', 'connect_to');
        if ($connectto == 'site') {
            require_once($CFG->libdir.'/coursecatlib.php');
            $displaylist = coursecat::make_categories_list();
            $linktext = $displaylist[$cr->category] .'/'. $linktext;
        }

        $linkurl = new moodle_url('/mod/reservation/view.php', array('id' => $cr->id));
        $link = html_writer::tag('a', $linktext, array('href' => $linkurl, 'class' => 'connectedlink'));

        $html = get_string('reservedonconnected', 'reservation', $link);
        echo html_writer::tag('p', $html);
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
     * Print user request status
     *
     * @param stdClass $reservation
     * @param stdClass $currentuser
     */
    public function print_user_request_status($reservation, $currentuser) {
        global $OUTPUT;

        $now = time();
        if (isset($currentuser->number) && ($currentuser->number > 0)) {
            $note = '';
            if (!empty($currentuser->note)) {
                $notelabel = html_writer::tag('span', get_string('yournote', 'reservation'), array('class' => 'notelabel'));
                $notetext = html_writer::tag('span', format_string($currentuser->note), array('class' => 'notetext'));
                $note = html_writer::tag('div', $notelabel.' '.$notetext, array('class' => 'usernote'));
            }
            $canviewnumbernow = ($reservation->showrequest == 0) && ($now > $reservation->timeclose);
            $canviewnumberalways = ($reservation->showrequest == 3);
            if ($canviewnumbernow || $canviewnumberalways) {
                $numberspan = html_writer::tag('span', $currentuser->number, array('class' => 'justbookednumber'));
                if (($reservation->maxrequest > 0) && ($currentuser->number > $reservation->maxrequest)) {
                    $strjustbooked = get_string('justoverbooked', 'reservation', html_writer::tag('span', $numberspan));
                    echo $OUTPUT->box($strjustbooked.$note, 'justbooked overbooked');
                } else {
                    $strjustbooked = get_string('justbooked', 'reservation', html_writer::tag('span', $numberspan));
                    echo $OUTPUT->box($strjustbooked.$note, 'justbooked');
                }
            } else {
                $classes = 'alreadybooked';
                if (($reservation->maxrequest > 0) && ($currentuser->number > $reservation->maxrequest)) {
                    $classes .= ' overbooked';
                    echo $OUTPUT->box(get_string('alreadyoverbooked', 'reservation').$note, $classes);
                } else {
                    echo $OUTPUT->box(get_string('alreadybooked', 'reservation').$note, $classes);
                }
            }
            if (!empty($currentuser->grade)) {
                echo $OUTPUT->box($currentuser->grade, 'graded');
            }
        }
    }



    /**
     * Gets note field
     *
     * @param object $reservation reservation object
     *
     * @return string The request note form field
     */
    public function display_note_field($reservation) {
        global $OUTPUT;

        $html = '';
        if ($reservation->note >= 1) {
            $html .= html_writer::start_tag('div', array('class' => 'note'));
            $required = '';
            if ($reservation->note == 2) {
                $required = '<span class="req">' . $OUTPUT->pix_icon('req', get_string('requiredelement', 'form')) . '</span>';
            }
            $html .= html_writer::tag('label',
                                      get_string('note', 'reservation').$required,
                                      array('for' => 'note', 'class' => 'notelabel'));
            $html .= html_writer::tag('textarea', '', array('id' => 'note', 'name' => 'note', 'rows' => '5', 'cols' => '30'));
            $html .= html_writer::end_tag('div');
        }
        return $html;
    }


    /**
     * Get seats availability
     *
     * @param object $reservation reservation object
     * @param object $seats seats availability counters
     *
     * @return string availability html code
     */
    public function display_availability($reservation, $seats) {
        $html = '';
        if (($reservation->maxrequest == 0) && ($seats->available > 0)) {
            $html = html_writer::tag('span', get_string('availablerequests', 'reservation'), array('class' => 'available'));
        } else if (($seats->available > 0) || ($seats->total > 0)) {
            if ($seats->available > 0) {
                $html = html_writer::tag('span',
                                          get_string('availablerequests', 'reservation').': ',
                                          array('class' => 'available'));
                $html .= html_writer::tag('span', $seats->available, array('class' => 'availablenumber'));
            } else {
                $html = html_writer::tag('span',
                                          get_string('overbookonly', 'reservation'),
                                          array('class' => 'overbook'));
            }
        } else {
            $html = html_writer::tag('span',
                                     get_string('nomorerequest', 'reservation'),
                                     array('class' => 'nomoreavailable'));
        }
        return html_writer::tag('div', $html, array('class' => 'availability'));
    }

    /**
     * Print reserve form
     *
     * @param stdClass $reservation
     * @param stdClass $status
     * @param array $addableusers
     */
    public function print_manualreserve_form($reservation, $status, $addableusers) {
        global $USER, $OUTPUT;

        $html = '';
        $formattributes = array();
        $formattributes['id'] = 'manualreserve';
        $formattributes['enctype'] = 'multipart/form-data';
        $formattributes['method'] = 'post';
        $formattributes['action'] = $status->url;
        $formattributes['class'] = 'mform';

        if ($reservation->note == 2) {
            $errormessage = '<span class="error">'.get_string('err_required', 'form').'</span>';
            $formattributes['onsubmit'] = 'if (this.menunewparticipant.selectedIndex == 0) { '.
                   '$(\'#menunewparticipant\').before(\'' . $errormessage . '<br\>\'); '.
                   'return false; '.
                   '} '.
                   'if (this.note.value == \'\') { '.
                   '$(\'#note\').before(\'' . $errormessage . '<br\>\'); '.
                   'return false; '.
                   '}';
        }
        $html .= html_writer::start_tag('form', $formattributes);
        $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'reservation',
                                                       'value' => $reservation->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'sesskey',
                                                       'value' => $USER->sesskey));
        $html .= html_writer::start_tag('div');
        $required = '<span class="req">' . $OUTPUT->pix_icon('req', get_string('requiredelement', 'form')) . '</span>';
        $html .= html_writer::tag('label',
                                  get_string('addparticipant', 'reservation').$required,
                                  array('for' => 'newparticipant', 'class' => 'addparticipant'));
        $html .= html_writer::select($addableusers, 'newparticipant');
        $html .= html_writer::end_tag('div');

        $html .= $this->display_note_field($reservation);

        $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                       'name' => 'reserve',
                                                       'class' => 'btn btn-primary manualreservebtn',
                                                       'value' => get_string('reserve', 'reservation')));
        $html .= html_writer::end_tag('form');

        echo html_writer::tag('div', $html, array('class' => 'manualreserve'));
    }

    /**
     * Print reserve form
     *
     * @param stdClass $reservation
     * @param stdClass $status
     * @param stdClass $currentuser
     * @param stdClass $seats
     */
    public function print_reserve_form($reservation, $status, $currentuser, $seats) {
        global $USER;

        $html = '';

        $formattributes = array();
        $formattributes['id'] = 'reserve';
        $formattributes['enctype'] = 'multipart/form-data';
        $formattributes['method'] = 'post';
        $formattributes['action'] = $status->url;
        $formattributes['class'] = 'mform';

        if ($reservation->note == 2) {
            $errormessage = '<span id="id_error_name" class="error">'.get_string('err_required', 'form').'</span>';
            $formattributes['onsubmit'] = 'if (this.note.value == \'\') { ' .
                   '$(\'#note\').before(\'' . $errormessage . '<br\>\'); '.
                   'return false; '.
                   '}';
        }
        $html .= html_writer::start_tag('form', $formattributes);
        $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'reservation',
                                                       'value' => $reservation->id));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'sesskey',
                                                       'value' => $USER->sesskey));
        if (isset($currentuser->number) && ($currentuser->number > 0)) {
            $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                           'name' => 'cancel',
                                                           'class' => 'btn btn-primary',
                                                           'value' => get_string('reservecancel', 'reservation')));
        } else if (($reservation->maxrequest == 0) || ($seats->available > 0) || ($seats->total > 0)) {
            $html .= $this->display_note_field($reservation);

            $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                           'name' => 'reserve',
                                                           'class' => 'btn btn-primary reservebtn',
                                                           'value' => get_string('reserve', 'reservation')));
        }
        $html .= html_writer::end_tag('form');
        echo html_writer::tag('div', $html, array('class' => 'reserve'));
    }

    /**
     * Print viewtype form
     *
     * @param stdClass $status
     * @param array $counters
     */
    public function print_viewtype_form($status, $counters) {
        $html = html_writer::start_tag('form', array('enctype' => 'multipart/form-data',
                                                     'method' => 'post',
                                                     'action' => $status->url,
                                                     'id' => 'viewtype'));
        $html .= html_writer::start_tag('fieldset');
        if ($status->view == 'full') {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'clean'));
            $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                           'name' => 'save',
                                                           'class' => 'btn btn-secondary',
                                                           'value' => get_string('cleanview', 'reservation')));
        } else if ($counters[0]->deletedrequests > 0) {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'full'));
            $html .= html_writer::empty_tag('input', array('type' => 'submit',
                                                           'name' => 'save',
                                                           'class' => 'btn btn-secondary',
                                                           'value' => get_string('fullview', 'reservation')));
        }
        $html .= html_writer::end_tag('fieldset');
        $html .= html_writer::end_tag('form');

        echo html_writer::tag('div', $html, array('class' => 'viewtype'));
    }

    /**
     * Print requests table and manage form
     *
     * @param stdClass $reservation
     * @param object $table
     * @param array $rows
     * @param stdClass $status
     * @param array $counters
     * @param object $context
     */
    public function print_requests_table($reservation, $table, $rows, $status, $counters, $context) {
        global $USER, $PAGE;

        $now = time();

        echo html_writer::start_tag('div', array('id' => 'tablecontainer'));
        if (($status->mode == 'manage') && has_capability('mod/reservation:viewrequest', $context)) {
            echo html_writer::start_tag('form', array('id' => 'requestactions',
                                                      'enctype' => 'multipart/form-data',
                                                      'method' => 'post',
                                                      'action' => $status->url));
            echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                       'name' => 'sesskey',
                                                       'value' => $USER->sesskey));
            if (isset($status->view) && !empty($status->view)) {
                echo html_writer::empty_tag('input', array('type' => 'hidden',
                                                           'name' => 'view',
                                                           'value' => $status->view));
            }
        }

        $table->start_output();

        foreach ($rows as $row) {
            $table->add_data($row);
        }

        $table->finish_output();

        if (($status->mode == 'manage') && has_capability('mod/reservation:viewrequest', $context) &&
           ((($reservation->grade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0))
            || ($counters[0]->count > 0) || ($counters[0]->deletedrequests > 0))) {
            if (($reservation->grade != 0) && ($now > $reservation->timestart) && ($counters[0]->count > 0)) {
                $html = html_writer::empty_tag('input', array('type' => 'submit',
                                                              'name' => 'savegrades',
                                                              'class' => 'btn btn-primary',
                                                              'value' => get_string('save', 'reservation')));
                echo html_writer::tag('div', $html, array('class' => 'savegrades'));
            }
            // Print "Select all" etc.
            if (!empty($status->actions) && (($counters[0]->count > 0) ||
               (($status->view == 'full') && ($counters[0]->deletedrequests > 0)))) {
                $html = '';
                $html .= html_writer::start_tag('div', array('class' => 'btn-group'));
                $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                'id' => 'checkall',
                                                'class' => 'btn btn-secondary',
                                                'value' => get_string('selectall')));
                $html .= html_writer::empty_tag('input', array('type' => 'button',
                                                               'id' => 'checknone',
                                                               'class' => 'btn btn-secondary',
                                                               'value' => get_string('deselectall')));
                $html .= html_writer::end_tag('div');
                $html .= html_writer::select($status->actions, 'action', '0',
                                             array('0' => get_string('withselected', 'reservation')));
                $okbutton = html_writer::empty_tag('input', array('type' => 'submit',
                                                               'name' => 'selectedaction',
                                                               'class' => 'btn btn-secondary m-r-1',
                                                               'value' => get_string('ok')));
                $html .= html_writer::tag('noscript', $okbutton);
                echo html_writer::tag('div', $html, array('class' => 'form-buttons'));

                $options = new stdClass();
                $options->reservationid = $reservation->id;
                $PAGE->requires->js_call_amd('mod_reservation/requests', 'init', [$options]);
            }

            echo html_writer::end_tag('form');
        }
        echo html_writer::end_tag('div');

    }

}

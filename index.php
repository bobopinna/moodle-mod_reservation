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
 * This page lists all the instances of reservation in a particular course.
 *
 * @package mod_reservation
 * @copyright 2006 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', null, PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

$coursecontext = context_course::instance($course->id);

$publiclists = get_config('reservation', 'publiclists');
if (($publiclists === false) || isloggedin()) {
    require_course_login($course);
} else {
    $PAGE->set_context($coursecontext);
}

$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$event = \mod_reservation\event\course_module_instance_list_viewed::create(array('context' => $coursecontext));
$event->add_record_snapshot('course', $course);
$event->trigger();

// Get all required strings.
$strreservations = get_string('modulenameplural', 'reservation');
$strreservation  = get_string('modulename', 'reservation');
$strsectionname  = get_string('sectionname', 'format_'.$course->format);
$strname  = get_string('name');
$streventdate  = get_string('date');
$strteachers  = get_string('teachers');
$strlocation  = get_string('location', 'reservation');
$strintro  = get_string('moduleintro');
$strclose  = get_string('timeclose', 'reservation');

// Define the table headers.
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $tableheaders  = array ($strsectionname, $strname, $streventdate, $strteachers, $strlocation, $strintro, $strclose);
    $tablecolumns = array ('section', 'name', 'startdate', 'teachers', 'location', 'intro', 'timeclose');
} else {
    $tableheaders  = array ($strname, $streventdate, $strteachers, $strlocation, $strintro, $strclose);
    $tablecolumns = array ('name', 'startdate', 'teachers', 'location', 'intro', 'timeclose');
}

if (isloggedin() && !isguestuser()) {
    $tableheaders[] = get_string('reserved', 'reservation');
    $tablecolumns[] = 'reserved';
}

// Set up the table.
$table = new flexible_table('mod-reservation');

$table->is_downloadable(true);
$table->show_download_buttons_at(array(TABLE_P_TOP, TABLE_P_BOTTOM));

$table->is_downloading($download, clean_filename("$course->shortname $strreservations"), "$course->shortname $strreservations");

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($CFG->wwwroot.'/mod/reservation/index.php?id='.$id);

$table->sortable(false);
$table->collapsible(true);

foreach ($tablecolumns as $column) {
    $table->column_class($column, $column);
}

$table->set_attribute('id', 'reservations');
$table->set_attribute('class', 'generaltable generalbox');

$table->setup();

// Get all the appropriate data.
if ($reservations = get_all_instances_in_course('reservation', $course)) {
    $resnames = array();
    $restimestarts = array();
    $ressections = array();
    $listsort = get_config('reservation', 'list_sort');
    foreach ($reservations as $key => $row) {
        $resnames[$key]  = $row->name;
        if ($listsort == 'date') {
            $restimestarts[$key] = $row->timestart;
        } else {
            $ressections[$key] = $row->section;
        }
    }

    if ($listsort === false) {
         $listsort = 'section';
    }

    if (($listsort == 'date') || (!$usesections)) {
        array_multisort($restimestarts, SORT_NUMERIC, $resnames, SORT_ASC, $reservations);
    } else if ($listsort == 'name') {
        array_multisort($resnames, SORT_ASC, $ressections, SORT_NUMERIC, $reservations);
    } else {
        array_multisort($ressections, SORT_NUMERIC, $resnames, SORT_ASC, $reservations);
    }
}

// Print the header.
if (!$table->is_downloading()) {
    $PAGE->set_url('/mod/reservation/index.php', array('id' => $id));
    $PAGE->set_title($course->shortname.': '.$strreservations);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strreservations);

    echo $OUTPUT->header();

    echo $OUTPUT->box_start('center');
}

$table->start_output();

$modinfo = get_fast_modinfo($course);
$printsection = '';
$currentsection = null;
foreach ($reservations as $reservation) {
    $cm = $modinfo->cms[$reservation->coursemodule];
    if ($usesections) {
        if ($reservation->section !== $currentsection) {
            $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $reservation->section));
            $printsection = get_section_name($course, $section);
            $currentsection = $reservation->section;
        }
    }

    $dimmed = '';
    if (!$reservation->visible) {
        $dimmed = 'class="dimmed"';
    }

    $place = $reservation->location != '0' ? format_string(trim($reservation->location)) : '';
    if (!$table->is_downloading()) {
        $description = format_string(preg_replace('/\n|\r|\r\n/', ' ', strip_tags(trim($reservation->intro))));
    } else {
        $description = format_string(preg_replace('/\n|\r|\r\n|, /', ' ', strip_tags(trim($reservation->intro))));
    }

    $now = time();
    $deltatime = get_config('reservation', 'deltatime');
    if ($deltatime === false) {
        $deltatime = -1;
    }
    $eventdate = userdate($reservation->timestart, get_string('strftimedate')) .' '.
              userdate($reservation->timestart, get_string('strftimetime'));
    if (($reservation->timestart + $deltatime < $now) && ($deltatime > 0)) {
        $dimmed = 'class="dimmed"';
    }

    if ($reservation->timeclose > $now) {
        $reservation->timeopen = !empty($reservation->timeopen) ? $reservation->timeopen : $reservation->timemodified;
        if ($reservation->timeopen < $now) {
            $timeclose = userdate($reservation->timeclose, get_string('strftimedate'));
        } else if ($table->is_downloading()) {
            $timeclose = userdate($reservation->timeclose, get_string('strftimedate')) .' '.
                     userdate($reservation->timeclose, get_string('strftimetime'));
        } else {
            $timeclose = get_string('notopened', 'reservation');
        }
    } else {
        if ($reservation->timestart > $now) {
            $timeclose = get_string('closed', 'reservation');
        } else {
            $timeclose = '';
        }
    }
    $teachername = reservation_get_teacher_names($reservation);

    if (!$table->is_downloading()) {
        $link = "<a $dimmed href=\"view.php?id=$reservation->coursemodule\">$reservation->name</a>";
    } else {
        $link = trim($reservation->name);
    }

    $row = array();

    $context = context_module::instance($reservation->coursemodule);
    if ((has_capability('mod/reservation:viewrequest', $context)) || (empty($dimmed))) {
        if ($usesections) {
            $row = array ($printsection, $link, $eventdate, $teachername, $place, $description, $timeclose);
        } else {
            $row = array ($link, $eventdate, $teachername, $place, $description, $timeclose);
        }
        if (has_capability('mod/reservation:viewrequest', $context)) {
            $row[] = $DB->count_records('reservation_request', array('reservation' => $reservation->id, 'timecancelled' => 0))
                     .' '. get_string('students');
        } else if (has_capability('mod/reservation:reserve', $context)) {
            $queryparameters = array('reservation' => $reservation->id, 'userid' => $USER->id, 'timecancelled' => 0);
            if ($DB->get_record('reservation_request', $queryparameters)) {
                $row[] = get_string('yes');
            } else {
                $row[] = get_string('no');
            }
        }
    }
    if (!empty($row)) {
            $table->add_data($row);
    }
}

$table->finish_output();

if (!$table->is_downloading()) {
    echo $OUTPUT->box_end();

    // Finish the page.
    echo $OUTPUT->footer($course);
}

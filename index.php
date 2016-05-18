<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// This page lists all the instances of reservation in a particular course

    require_once('../../config.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->libdir.'/tablelib.php');
    require_once('locallib.php');

    $id = required_param('id', PARAM_INT);   // course
    $download = optional_param('download', null, PARAM_ALPHA); // none, excel or text

    $course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

    $coursecontext = context_course::instance($course->id);   // COURSE context

    if (!isset($CFG->reservation_publiclists) || empty($CFG->reservation_publiclists) || isloggedin()) {
        require_course_login($course);
    } else {
        $PAGE->set_context($coursecontext);
    }

    $PAGE->set_pagelayout('incourse');

    // Trigger instances list viewed event.
    $event = \mod_reservation\event\course_module_instance_list_viewed::create(array('context' => $coursecontext));
    $event->add_record_snapshot('course', $course);
    $event->trigger();

/// Get all required strings
    $strreservations = get_string('modulenameplural', 'reservation');
    $strreservation  = get_string('modulename', 'reservation');
    $strsectionname  = get_string('sectionname','format_'.$course->format);
    $strname  = get_string('name');
    $streventdate  = get_string('date');
    $strteachers  = get_string('teachers');
    $strlocation  = get_string('location', 'reservation');
    $strintro  = get_string('moduleintro');
    $strclose  = get_string('timeclose', 'reservation');

/// Define the table headers    
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

/// Set up the table
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

/// Get all the appropriate data
    if ($reservations = get_all_instances_in_course('reservation', $course)) {
        $resnames = array();
        $restimestarts = array();
        $ressections = array();
        foreach ($reservations as $key => $row) {
            $resnames[$key]  = $row->name;
            if ($CFG->reservation_list_sort == 'date') {
                $restimestarts[$key] = $row->timestart;
            } else {
                $ressections[$key] = $row->section;
            }
        }
    
        if (!isset($CFG->reservation_list_sort)) {
             $CFG->reservation_list_sort = 'section';
        }

        if (($CFG->reservation_list_sort == 'date') || (!$usesections)) {
            array_multisort($restimestarts, SORT_NUMERIC, $resnames, SORT_ASC, $reservations);
        } else if ($CFG->reservation_list_sort == 'name') {
            array_multisort($resnames, SORT_ASC, $ressections, SORT_NUMERIC, $reservations);
        } else {
            array_multisort($ressections, SORT_NUMERIC, $resnames, SORT_ASC, $reservations);
        }
    }

/// Print the header
    if (!$table->is_downloading()) {
        $PAGE->set_url('/mod/reservation/index.php', array('id'=>$id));
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

        $place = $reservation->location!='0'?format_string(trim($reservation->location)):'';
        if (!$table->is_downloading()) {    
            $description = format_string(preg_replace('/\n|\r|\r\n/',' ',strip_tags(trim($reservation->intro))));
        } else {
            $description = format_string(preg_replace('/\n|\r|\r\n|, /',' ',strip_tags(trim($reservation->intro))));
        }

        $now = time();
        if (!isset($CFG->reservation_deltatime)) {
            $CFG->reservation_deltatime = -1;
        }
        $eventdate = userdate($reservation->timestart, get_string('strftimedate')) .' '. userdate($reservation->timestart, get_string('strftimetime'));
        if (($reservation->timestart+$CFG->reservation_deltatime < $now) && ($CFG->reservation_deltatime > 0)) {
            $dimmed = 'class="dimmed"';
        }

        if ($reservation->timeclose > $now) {
            $reservation->timeopen = !empty($reservation->timeopen)?$reservation->timeopen:$reservation->timemodified;
            if ($reservation->timeopen < $now) {
                $timeclose = userdate($reservation->timeclose, get_string('strftimedate'));
            } else if ($table->is_downloading()) {
$timeclose = userdate($reservation->timeclose, get_string('strftimedate')) .' '. userdate($reservation->timeclose, get_string('strftimetime'));
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
        if ((has_capability('mod/reservation:viewrequest',$context)) || (empty($dimmed))) {
            if ($usesections) {
                $row = array ($printsection, $link, $eventdate, $teachername, $place, $description, $timeclose);
            } else {
                $row = array ($link, $eventdate, $teachername, $place, $description, $timeclose);
            }
            if (has_capability('mod/reservation:viewrequest',$context)) {
                $row[] = $DB->count_records('reservation_request', array('reservation' => $reservation->id,'timecancelled' => 0)) .' '. get_string('students');
            } else if (has_capability('mod/reservation:reserve',$context)) {
                if ($DB->get_record('reservation_request', array('reservation' => $reservation->id,'userid' => $USER->id,'timecancelled' => 0))) {
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

        /// Finish the page
        echo $OUTPUT->footer($course);
    }

?>

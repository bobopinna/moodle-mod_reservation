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
 * Bulk reservation upload forms
 *
 * @package    mod_reservation
 * @copyright  2012 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');


/**
 * Upload a file CVS file with reservation list.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @copyright  2012 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reservation_upload_form extends moodleform {
    /**
     * Define the reservation upload form
     */
    public function definition () {
        $mform = $this->_form;

        $mform->addElement('header', 'settingsheader', get_string('upload'));

        $mform->addElement('filepicker', 'reservationsfile', get_string('file'));
        $mform->addRule('reservationsfile', null, 'required');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $choices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploaduser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(false, get_string('uploadreservations', 'reservation'));
    }
}

/**
 * Confirm CSV file data and add missing values
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @copyright  2012 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reservation_upload_confirm_form extends moodleform {
    /**
     * Define the confirmation form
     */
    public function definition () {
        global $DB;
        $mform = $this->_form;
        $noerror = true;

        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        $mform->addElement('header', 'settingsheader', get_string('general'));
        if (!in_array('course', $columns)) {
            $displaylist = array();
            if (class_exists('core_course_category')) {
                $displaylist = core_course_category::make_categories_list();
            } else {
                require_once($CFG->libdir. '/coursecatlib.php');
                $displaylist = coursecat::make_categories_list();
            }

            $courses = $DB->get_records('course');
            if ($courses) {
                $choices = array();
                foreach ($courses as $course) {
                    $coursenumsections = 0;
                    if (course_get_format($course)->uses_sections()) {
                        $sections = get_fast_modinfo($course->id)->get_section_info_all();
                        if (!empty($sections)) {
                            $coursenumsections = (int)max(array_keys($sections));
                        }
                    }

                    if ($coursenumsections > 0 ) {
                        if ($course->category != 0) {
                            $choices[$course->shortname] = $displaylist[$course->category].' / '.
                                    $course->fullname.' ('.$course->shortname.')';
                        } else {
                            $choices[$course->shortname] = $course->fullname.' ('.$course->shortname.')';
                        }
                    }
                }
                if (!empty($choices)) {
                    asort($choices, SORT_NATURAL);
                    $mform->addElement('select', 'course', get_string('course'), $choices);
                } else {
                    $noerror = false;
                }

            } else {
                $noerror = false;
            }
        } else {
            $mform->addElement('hidden', 'course');
            $mform->setType('course', PARAM_INT);
            $mform->setdefault('course', '');

        }
        if ($noerror) {
            $choices = array();
            $choices[0] = get_string('no');
            $choices[1] = get_string('optional', 'reservation');
            $choices[2] = get_string('required', 'reservation');
            $mform->addElement('select', 'note', get_string('enablenote', 'reservation'), $choices);
            // Hidden fields.
            $mform->addElement('hidden', 'iid');
            $mform->setType('iid', PARAM_INT);
            $mform->setdefault('iid', $data['iid']);

            $this->add_action_buttons(true, get_string('importreservations', 'reservation'));

            $this->set_data($data);
        } else {
            $mform->addElement('static', 'alert', '', get_string('nocourseswithnsections', 'reservation', $data['maxsection']));
            $mform->addElement('cancel', 'cancel');
        }
    }
}

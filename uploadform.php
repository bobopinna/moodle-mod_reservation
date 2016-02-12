<?php
/**
 * Bulk reservation upload forms
 *
 * @package    reservation
 * @subpackage upload
 * @copyright  2012 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';


/**
 * Upload a file CVS file with user information.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @copyright  2012 Roberto Pinna {@mail roberto.pinna@unipmn.it}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reservation_upload_form extends moodleform {
    function definition () {
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

        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploaduser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(false, get_string('uploadreservations', 'reservation'));
    }
}

class reservation_upload_confirm_form extends moodleform {
    function definition () {
        global $DB;
        $mform = $this->_form;
        $noerror = true;

        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];
        if (!isset($data['maxsection'])) {
            $data['maxsection'] = 0;
        }
         
        $mform->addElement('header', 'settingsheader', get_string('general'));
        if (!in_array('course',$columns)) {
            $displaylist = coursecat::make_categories_list();

            $courses = $DB->get_records('course');
            if ($courses) {
                $choices = array();
                foreach($courses as $course) {
                    // compartibility with course formats using field 'numsections'
                    $courseformatoptions = course_get_format($course)->get_format_options();

                    if (array_key_exists('numsections', $courseformatoptions) && ($data['maxsection'] <= $courseformatoptions['numsections']) && ($data['maxsection'] > 0)) {
                        if ($course->category != 0) {
                            $choices[$course->shortname] = $displaylist[$course->category].' / '.$course->fullname.' ('.$course->shortname.')';
                        } else {
                            $choices[$course->shortname] = $course->fullname.' ('.$course->shortname.')';
                        }
                    }
                }
                $mform->addElement('select', 'course', get_string('course'), $choices);
            } else {
                $noerror = false;
            }
        } else {
            $mform->addElement('hidden', 'course');
            $mform->setType('course', PARAM_INT);
            $mform->setdefault('course', '');

        }
        if ($noerror) {
            $mform->addElement('selectyesno', 'note', get_string('note', 'reservation'));
            // hidden fields
            $mform->addElement('hidden', 'iid');
            $mform->setType('iid', PARAM_INT);
            $mform->setdefault('iid', $data['iid']);

            $this->add_action_buttons(true, get_string('importreservations', 'reservation'));
        } else {
            $mform->addElement('static', 'alert', '', get_string('nocourseswithnsections', 'reservation', $data['maxsection']));
            $mform->addElement('cancel','cancel');
        }
    }
}

?>

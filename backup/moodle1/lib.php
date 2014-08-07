<?php

/**
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * @package    mod
 * @subpackage reservation
 * @author     Roberto Pinna (bobo@di.unipmn.it)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Choice conversion handler
 */
class moodle1_mod_reservation_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the path /MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION does not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path(
                'reservation', '/MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION',
                array(
                    'renamefields' => array(
 //                       'description' => 'intro',
                    ),
                    'newfields' => array(
                        'introformat' => 0,
                        'overbook' => 0,
                        'mailed' => 0,
                        'parent' => 0,
                    ),
                    'dropfields' => array(
                        'modtype'
                    ),
                )
            ),
            new convert_path('reservation_limits', '/MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION/LIMITS'),
            new convert_path('reservation_limit', '/MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION/LIMITS/LIMIT'),
        );
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION
     * data available
     */
    public function process_reservation($data) {

        // get the course module id and context id
        $instanceid     = $data['id'];
        $cminfo         = $this->get_cminfo($instanceid);
        $this->moduleid = $cminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_reservation');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $data['intro'] = moodle1_converter::migrate_referenced_files($data['intro'], $this->fileman);

        // start writing reservation.xml
        $this->open_xml_writer("activities/reservation_{$this->moduleid}/reservation.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
            'modulename' => 'reservation', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('reservation', array('id' => $instanceid));

        foreach ($data as $field => $value) {
            if ($field <> 'id') {
                $this->xmlwriter->full_tag($field, $value);
            }
        }

        return $data;
    }

    /**
     * This is executed when the parser reaches the <LIMITS> opening element
     */
    public function on_reservation_limits_start() {
        $this->xmlwriter->begin_tag('limits');
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/RESERVATION/LIMITS/LIMIT
     * data available
     */
    public function process_reservation_limit($data) {
        $this->write_xml('limit', $data, array('/limit/id'));
    }

    /**
     * This is executed when the parser reaches the closing </LIMITS> element
     */
    public function on_reservation_limits_end() {
        $this->xmlwriter->end_tag('limits');
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'reservation' path
     */
    public function on_reservation_end() {
        // finalize reservation.xml
        $this->xmlwriter->end_tag('reservation');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();


        // write inforef.xml
        $this->open_xml_writer("activities/reservation_{$this->moduleid}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }
}

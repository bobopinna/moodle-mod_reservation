<?php
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/reservation/backup/moodle2/backup_reservation_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/reservation/backup/moodle2/backup_reservation_settingslib.php'); // Because it exists (optional)

/**
 * reservation backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_reservation_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new backup_reservation_activity_structure_step('reservation_structure', 'reservation.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of reservations
        $search="/(".$base."\/mod\/reservation\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHOICEINDEX*$2@$', $content);

        // Link to reservation view by moduleid
        $search="/(".$base."\/mod\/reservation\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHOICEVIEWBYID*$2@$', $content);

        return $content;
    }
}

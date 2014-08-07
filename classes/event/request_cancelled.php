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
 * The mod_reservation request cancelled event.
 *
 * @package    mod_reservation
 * @copyright  2014 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reservation\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_reservation request cancelled event class.
 *
 * @package    mod_reservation
 * @since      Moodle 2.7
 * @copyright  2014 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_cancelled extends \core\event\base {
    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \stdClass $reservation
     * @param \context_module $context
     * @param \stdClass $request
     * @param \stdClass $requestnote
     * @return request_cancelled
     */
    public static function create_from_request(\stdClass $reservation, \context_module $context, \stdClass $request) {
        $data = array(
            'context' => $context,
            'objectid' => $request->id,
        );
        /** @var request_cancelled $event */
        $event = self::create($data);
        $event->add_record_snapshot('reservation', $reservation);
        $event->add_record_snapshot('reservation_request', $request);

        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' cancelled the request with id '$this->objectid' for the reservation with the " .
            "course module id '$this->contextinstanceid'.";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        $request = $this->get_record_snapshot('reservation_request', $this->objectid);
        return array($this->courseid, 'reservation', 'cancel', 'view.php?id='.$this->contextinstanceid, $request->userid, $this->contextinstanceid);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventrequestcancelled', 'mod_reservation');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/reservation/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'reservation_request';
    }
}

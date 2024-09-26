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
 * CLASS MADE BY ARIADNE
 */
declare(strict_types=1);

namespace mod_reservation\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the assign activity.
 *
 * Class for defining mod_assign's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given assign instance and a user.
 *
 * @package mod_reservation
 * @copyright 2024 Ariadne
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;
        // Get reservation details.
        $reservation = $DB->get_record('reservation', array('id' => $this->cm->instance), '*', MUST_EXIST);
        // If completion option is enabled, evaluate it and return true/false.
        if ($reservation->completionreserved) {
            $params = array('userid' => $this->userid, 'reservation' => $reservation->id, 'timecancelled' => 0);
            if ($DB->record_exists('reservation_request', $params)) {
                return 1;
            }
            return 0;
        } else {
            // Completion option is not enabled so just return 0.
            return 0;
        }
    }

    /**
     * Fetches the overall completion status of this activity instance for a user based on its available custom completion rules.
     *
     * @return int The completion state (e.g. COMPLETION_COMPLETE, COMPLETION_INCOMPLETE).
     */
    public function get_overall_completion_state(): int {
        foreach ($this->get_available_custom_rules() as $rule) {
            $state = $this->get_state($rule);
            // Return early if one of the custom completion rules is not yet complete.
            if ($state == COMPLETION_INCOMPLETE) {
                return $state;
            }
        }
        // If this was reached, then all custom rules have been marked complete.
        return COMPLETION_COMPLETE;
    }

    /**
     * Fetches the list of custom completion rules that are being used by this activity module instance.
     *
     * @return array
     */
    public function get_available_custom_rules(): array {
        $rules = static::get_defined_custom_rules();
        $availablerules = [];
        $customdata = (array)$this->cm->customdata;
        foreach ($rules as $rule) {
            $customrule = $customdata['customcompletionrules'][$rule] ?? false;
            if (!empty($customrule)) {
                $availablerules[] = $rule;
            }
        }

        return $availablerules;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionreserved'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionreserved' => get_string('completionreserved:submit', 'reservation')
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionreserved',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}

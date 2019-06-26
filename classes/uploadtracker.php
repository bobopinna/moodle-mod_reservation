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
 * Tracking of processed reservation.
 *
 * This class prints reservation information into a html table.
 *
 * @package    mod_reservation
 * @copyright  2007 Petr Skoda {@link http://skodak.org}
 * @copyright  2012 onwards Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class to track upload reservations progress 
 *
 * @package    mod_reservation
 * @copyright  2007 Petr Skoda {@link http://skodak.org}
 * @copyright  2012 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ur_progress_tracker {
    /** @var array The row */
    private $_row;

    /** @var array $columns List of columns */
    public $columns = array('status', 'line', 'course', 'section', 'name', 'timestart', 'timeclose');

    /**
     * Print table header.
     *
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="urresults" class="generaltable boxaligncenter flexible-wrap" summary="'.
                get_string('uploadreservationsresult', 'reservation').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('linenumber', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('course').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('section').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('name').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timestart', 'reservation').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('timeclose', 'reservation').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     *
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number.
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal' => '', 'info' => '', 'warning' => '', 'error' => '');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $field) {
            $types = array_keys((array) $field);
            foreach ($types as $type) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="ur'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal' => '', 'info' => '', 'warning' => '', 'error' => '');
        }
    }

    /**
     * Add tracking info
     *
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); // Init arrays.
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .= '<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     *
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

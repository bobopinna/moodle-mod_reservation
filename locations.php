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
 * Editing interface to edit reservation location.
 *
 * @package mod_reservation
 * @copyright 2006 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('locallib.php');

$url = new moodle_url('/mod/reservation/locations.php');

$PAGE->set_url($url);

// This is hacky, tehre should be a special hidden page for it.
admin_externalpage_setup('managemodules');

// Get the current list of locations.
if (!$locations = $DB->get_records_menu('reservation_location')) {
    $locations = array();
}

// Print the header of the page.
$strmodulename = get_string('modulename', 'reservation');
$strlocations = get_string('locations', 'reservation');

echo $OUTPUT->header();

echo $OUTPUT->heading($strmodulename . ': ' . $strlocations);

echo $OUTPUT->box(get_string('configlocations', 'reservation'), 'generalbox boxaligncenter boxwidthnormal');

// First, process any inputs there may be.
if (confirm_sesskey()) {
    $add = optional_param('add', null, PARAM_ALPHA);
    if (isset($add)) {
        $location = optional_param('name', null, PARAM_TEXT);  // Location Name.
        if (isset($location) && !empty($location) && !in_array($location, $locations)) {
            $loc = new stdClass();
            $loc->name = $location;
            $id = $DB->insert_record('reservation_location', $loc);
            $locations[$id] = $location;
        }
    }
    $delete = optional_param('delete', null, PARAM_ALPHA);
    if (isset($delete)) {
        $selectedlocations = optional_param_array('locations', array(), PARAM_INT);  // Location id.
        foreach ($selectedlocations as $selectedlocation) {
            if (isset($selectedlocation) && !isset($locations[$selectedlocation])) {
                $selectedlocation = null;
            }
            if (isset($selectedlocation)) {
                $DB->delete_records('reservation_location', array('id' => $selectedlocation));
                unset($locations[$selectedlocation]);
            }
        }
    }
}

$sesskey = !empty($USER->id) ? $USER->sesskey : '';

natsort($locations);

// Print out the complete form.
echo $OUTPUT->box_start('locationform');
?>
<form id="locationform" method="post" action="locations.php">
     <fieldset class="locationedit">
         <label for='locations'><?php print_string('locationslist', 'reservation'); ?></label><br />
         <select id="locations" name="locations[]" size="15" multiple="multiple">
            <?php
            if (!empty($locations)) {
                foreach ($locations as $id => $location) {
                    echo '<option value="'.$id.'">'.$location."</option>\n";
                }
            }
            ?>
         </select>
     </fieldset>
     <fieldset class="locationedit">
         <input type="hidden" name="sesskey" value="<?php p($sesskey) ?>" />
         <input type="submit" name="add" value="&lt;-- <?php print_string('add') ?>" /><br />
         <input type="submit" name="delete" value="<?php print_string('deleteselected') ?> --X" />
     </fieldset>
     <fieldset class="locationedit">
         <label for='newlocation'><?php print_string('newlocation', 'reservation'); ?></label><br />
         <input id="newlocation" type="text" name="name" size="15" value="" />
     </fieldset>
</form>

<?php

echo $OUTPUT->box_end();

echo $OUTPUT->footer();

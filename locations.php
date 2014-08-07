<?php 
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// Editing interface to edit reservation location

    require_once('../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once('locallib.php');

    $url = new moodle_url('/mod/reservation/locations.php');

    $PAGE->set_url($url);

    admin_externalpage_setup('managemodules'); // this is hacky, tehre should be a special hidden page for it

/// Get the current list of locations

    if (!$locations = $DB->get_records_menu('reservation_location')) {
        $locations = array();
    }

/// Print the header of the page
    $strmodulename = get_string('modulename', 'reservation');
    $strlocations = get_string('locations', 'reservation');

    echo $OUTPUT->header();

    echo $OUTPUT->heading($strmodulename . ': ' . $strlocations);

    echo $OUTPUT->box(get_string('configlocations', 'reservation'), 'generalbox boxaligncenter boxwidthnormal');

/// First, process any inputs there may be.
    if (confirm_sesskey()) {
        $add = optional_param('add', NULL, PARAM_ALPHA); 
        if (isset($add)) {
            $location = optional_param('name', NULL, PARAM_TEXT);  // Location Name
            if (isset($location) && !empty($location) && !in_array($location,$locations)) {
                $loc = new stdClass();
                $loc->name = $location;
                $id = $DB->insert_record('reservation_location',$loc); 
                $locations[$id] = $location;
            }   
        }
        $delete = optional_param('delete', NULL, PARAM_ALPHA); 
        if (isset($delete)) {
            $selectedlocations = optional_param_array('locations', array(), PARAM_INT);  // Location id
            foreach ($selectedlocations as $selectedlocation) {
                if (isset($selectedlocation) && !isset($locations[$selectedlocation])) {
                    $selectedlocation = NULL;
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

/// Print out the complete form
    echo $OUTPUT->box_start('locationform');
?>
<form id="locationform" method="post" action="locations.php">
     <fieldset class="locationedit">
         <label for='locations'><?php print_string('locationslist','reservation'); ?></label><br />
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
         <label for='newlocation'><?php print_string('newlocation','reservation'); ?></label><br />
         <input id="newlocation" type="text" name="name" size="15" value="" />
     </fieldset>
</form>

<?php

    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();

?>

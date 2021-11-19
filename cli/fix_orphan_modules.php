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
 * CLI script to fix orphan course modules.
 *
 * @package    mod_reservation
 * @copyright  2021 Roberto Pinna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
        array(
                'help' => false,
                'check' => false
        ),
        array(
                'h' => 'help',
                'c' => 'check'
        )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "CLI script to fix orphan course modules.

Options:
-h, --help          Print out this help
-c, --check         Check and list orphans course modules

Example:
\$sudo -u www-data /usr/bin/php mod/reservation/cli/fix_orphans_modules.php [-h] [-c]
";
    cli_error($help);
}

// Turn on debugging so we can see the detailed progress.
set_debugging(DEBUG_DEVELOPER, true);

$module = $DB->get_record('modules', array('name' => 'reservation'));
$coursemodules = $DB->get_records('course_modules', array('module' => $module->id));
if (!empty($coursemodules)) {
    foreach ($coursemodules as $coursemodule) {
        // Cleanup orphans records.
        if (!$DB->record_exists('reservation', array('id' => $coursemodule->instance))) {
            cli_writeln('Missing reservation: '. $coursemodule->instance);
            if (! $options['check']) {
                $reservation = new stdClass();
                $reservation->id = $coursemodule->instance;
                $reservation->course = $coursemodule->course;
                $reservation->name = 'Foo Reservation';
                $reservation->teachers = '';
                $reservation->location = '';
                $reservation->timemodified = $coursemodule->added;
                $newid = $DB->insert_record('reservation', $reservation);
                $DB->set_field('course_modules', 'instance', $newid, array('id' => $coursemodule->id));

                require_once($CFG->dirroot . '/course/lib.php');
                course_delete_module($coursemodule->id);

                cli_writeln('Reservation ' . $coursemodule->instance . ' fixed');
            }
        }
    }
}
cli_writeln('DONE!');

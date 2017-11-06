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
 * Send a private message to several selected users
 *
 * @package mod_reservation
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @copyright 2013 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/message/lib.php');

$id = required_param('id', PARAM_INT);
$messagebody = optional_param('messagebody', '', PARAM_CLEANHTML);
$send = optional_param('send', '', PARAM_BOOL);
$preview = optional_param('preview', '', PARAM_BOOL);
$edit = optional_param('edit', '', PARAM_BOOL);
$returnto = optional_param('returnto', new moodle_url('/mod/reservation/view.php', array('id' => $id)), PARAM_LOCALURL);
$format = optional_param('format', FORMAT_MOODLE, PARAM_INT);
$deluser = optional_param('deluser', 0, PARAM_INT);

if (isset($id)) {
    if (! $cm = get_coursemodule_from_id('reservation', $id)) {
        error('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }
}

$url = new moodle_url('/mod/reservation/messageselect.php', array('id' => $id));
if ($messagebody !== '') {
    $url->param('messagebody', $messagebody);
}
if ($send !== '') {
    $url->param('send', $send);
}
if ($preview !== '') {
    $url->param('preview', $preview);
}
if ($edit !== '') {
    $url->param('edit', $edit);
}
if ($returnto !== '') {
    $url->param('returnto', $returnto);
}
if ($format !== FORMAT_MOODLE) {
    $url->param('format', $format);
}
if ($deluser !== 0) {
    $url->param('deluser', $deluser);
}

$modulecontext = context_module::instance($cm->id);

$PAGE->set_url($url);
$PAGE->set_context($modulecontext);

require_login($course->id, false, $cm);

$coursecontext = context_course::instance($course->id);

$systemcontext = context_system::instance();

require_capability('moodle/course:bulkmessaging', $coursecontext);

if (empty($SESSION->reservation_messageto)) {
    $SESSION->reservation_messageto = array();
}
if (!array_key_exists($id, $SESSION->reservation_messageto)) {
    $SESSION->reservation_messageto[$id] = array();
}

if ($deluser) {
    $idinmessageto = array_key_exists($id, $SESSION->reservation_messageto);
    if ($idinmessageto && array_key_exists($deluser, $SESSION->reservation_messageto[$id])) {
        unset($SESSION->reservation_messageto[$id][$deluser]);
    }
}

if (empty($SESSION->reservation_messageselect[$id]) || $messagebody) {
    $SESSION->reservation_messageselect[$id] = array('messagebody' => $messagebody);
}

$messagebody = $SESSION->reservation_messageselect[$id]['messagebody'];

$count = 0;

if ($data = data_submitted()) {
    require_sesskey();
    foreach ($data as $k => $v) {
        if (preg_match('/^(user|teacher)(\d+)$/', $k, $m)) {
            if (!array_key_exists($m[2], $SESSION->reservation_messageto[$id])) {
                $returnfields = 'id,firstname,lastname,idnumber,email,mailformat,lastaccess, lang, maildisplay';
                if ($user = $DB->get_record_select('user', "id = ?", array($m[2]), $returnfields)) {
                    $SESSION->reservation_messageto[$id][$m[2]] = $user;
                    $count++;
                }
            }
        }
    }
}

$strtitle = get_string('message', 'reservation');

$PAGE->navbar->add($strtitle);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
echo $OUTPUT->header();
// If messaging is disabled on site, we can still allow users with capabilities to send emails instead.
if (empty($CFG->messaging)) {
    echo $OUTPUT->notification(get_string('messagingdisabled', 'message'));
}

if ($count) {
    if ($count == 1) {
        $heading = get_string('addedrecip', 'moodle', $count);
    } else {
        $heading = get_string('addedrecips', 'moodle', $count);
    }
    echo $OUTPUT->heading($heading);
}

if (!empty($messagebody) && !$edit && !$deluser && ($preview || $send)) {
    require_sesskey();
    if (count($SESSION->reservation_messageto[$id])) {
        if (!empty($preview)) {
            echo '<form method="post" action="messageselect.php" style="margin: 0 20px;">
<input type="hidden" name="returnto" value="'.s($returnto).'" />
<input type="hidden" name="id" value="'.$id.'" />
<input type="hidden" name="format" value="'.$format.'" />
<input type="hidden" name="sesskey" value="' . sesskey() . '" />
';
            echo "<h3>".get_string('previewhtml')."</h3>";
            echo "<div class=\"messagepreview\">\n".format_text($messagebody, $format)."\n</div>\n";
            echo '<p align="center"><input type="submit" name="send" value="'.get_string('sendmessage', 'message').'" />'."\n";
            echo '<input type="submit" name="edit" value="'.get_string('update').'" /></p>';
            echo "\n</form>";
        } else if (!empty($send)) {
            $fails = array();
            foreach ($SESSION->reservation_messageto[$id] as $user) {
                if (!message_post_message($USER, $user, $messagebody, $format)) {
                    $user->fullname = fullname($user);
                    $fails[] = get_string('messagedselecteduserfailed', 'moodle', $user);
                };
            }
            if (empty($fails)) {
                echo $OUTPUT->heading(get_string('messagedselectedusers'));
                unset($SESSION->reservation_messageto[$id]);
                unset($SESSION->reservation_messageselect[$id]);
            } else {
                echo $OUTPUT->heading(get_string('messagedselectedcountusersfailed', 'moodle', count($fails)));
                echo '<ul>';
                foreach ($fails as $f) {
                        echo '<li>', $f, '</li>';
                }
                echo '</ul>';
            }
            echo '<p align="center"><a href="view.php?id='.$id.'">'.get_string('backtoparticipants').'</a></p>';
        }
        echo $OUTPUT->footer();
        exit;
    } else {
        echo $OUTPUT->notification(get_string('nousersyet'));
    }
}

echo '<p align="center"><a href="'.$returnto.'">'.get_string("keepsearching").'</a>'.
        ((count($SESSION->reservation_messageto[$id])) ? ', '.get_string('usemessageform') : '').'</p>';

if ((!empty($send) || !empty($preview) || !empty($edit)) && (empty($messagebody))) {
    echo $OUTPUT->notification(get_string('allfieldsrequired'));
}

if (count($SESSION->reservation_messageto[$id])) {
    require_sesskey();
    $usehtmleditor = true;
    require("message.html");
}

$PAGE->requires->yui_module('moodle-core-formchangechecker',
        'M.core_formchangechecker.init',
        array(array(
            'formid' => 'theform'
        ))
);
$PAGE->requires->string_for_js('changesmadereallygoaway', 'moodle');

echo $OUTPUT->footer();

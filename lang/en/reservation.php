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
 * Reservation module language strings
 *
 * @package mod_reservation
 * @copyright 2006 onwards Roberto Pinna
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addparticipant'] = 'Add request';
$string['after10min'] = 'After 10 minutes from event start';
$string['after12h'] = 'After 12 hours from event start';
$string['after1d'] = 'After 1 day from event start';
$string['after1h'] = 'After 1 hour from event start';
$string['after1w'] = 'After 1 week from event start';
$string['after2d'] = 'After 2 days from event start';
$string['after2h'] = 'After 2 hours from event start';
$string['after2w'] = 'After 2 weeks from event start';
$string['after30min'] = 'After 30 minutes from event start';
$string['after3w'] = 'After 3 weeks from event start';
$string['after4h'] = 'After 4 hours from event start';
$string['after4w'] = 'After 4 weeks from event start';
$string['after5min'] = 'After 5 minutes from event start';
$string['after6h'] = 'After 6 hours from event start';
$string['alreadybooked'] = 'You are already booked';
$string['alreadyoverbooked'] = 'You are already in the waiting list';
$string['atstart'] = 'At event start';
$string['autohide'] = 'Reservation list auto hide';
$string['availablerequests'] = 'Available seats';
$string['badcourse'] = 'Course does not esists';
$string['badcoursesection'] = 'Section does not esists in given course';
$string['badparent'] = 'This reservation was connected to another reservation not restored now. The connection has been removed. If needed reconnect them manually';
$string['badteachers'] = 'Specified teacher email with ({$a}) not found';
$string['badteachersmail'] = 'Specified teacher email ({$a}) is bogus';
$string['badtimeclose'] = 'timeclose is bogus';
$string['badtimeend'] = 'timeend is bogus';
$string['badtimeopen'] = 'timeopen is bogus';
$string['badtimestart'] = 'timestart is bogus';
$string['by'] = 'by';
$string['bydate'] = 'Event date';
$string['byname'] = 'Name';
$string['bysection'] = 'Topic/Week';
$string['cancelledon'] = 'Cancelled on';
$string['cancellersmail'] = 'You are cancelled from \'{$a->reservation}\'. reservation';
$string['cancellersmailhtml'] = 'You are cancelled from <em>{$a->reservation}</em>. reservation';
$string['checkclashes'] = 'Check place and time clashes';
$string['clashesfound'] = 'Some place or time clashes found';
$string['clashesreport'] = 'Clashes report';
$string['cleanview'] = 'View only current reserved';
$string['close'] = 'close';
$string['closed'] = 'Closed';
$string['completiondetail:reserved'] = 'Student must reserve to complete this activity';
$string['completionreserved'] = 'Student must reserve to complete this activity';
$string['config'] = 'Reservation settings';
$string['configautohide'] = 'This define when reservations must be hidded from reservation list (mod/reservation/index.php). This could be useful if used with public lists, in order to display a cleaned list.';
$string['configcheckclashes'] = 'Enable "Check place and time clashes" button in reservation editing page';
$string['configconnectto'] = 'Define where the module search reservations to connect with';
$string['configdownload'] = 'This define the default download file format for all request and reservation lists.';
$string['configevents'] = 'This setting define which events will be created for every reservation';
$string['configfields'] = 'This setting define which fields will shown in reservations table';
$string['configlocations'] = 'Manage standard locations for reservations in this Moodle site';
$string['configmanualusers'] = 'This define what list of users is shown in dropdown menu used to manual reserve users.';
$string['configmaxoverbook'] = 'This define the max percentage of overbooking for reservations.';
$string['configmaxrequests'] = 'Define the limit of dropdown menu in reservation edit page';
$string['configminduration'] = 'This indicates reservation event minimal duration. It is used with non-ending events to check time and place availability';
$string['confignotifies'] = 'This setting define which notifies must sent';
$string['configoverbookstep'] = 'This define the percentage granularity of overbooking. Smaller step, greater granularity';
$string['configpubliclists'] = 'This define if reservation lists are public (viewed without login) or not.';
$string['configsortby'] = 'This define how reservation list are sorted.';
$string['configsublimits'] = 'Define the number of sublimits rules row in reservation edit page';
$string['configupload'] = 'Create several reservations uploading them via text file';
$string['confirmdelete'] = 'Are you sure that you want to delete the selected reservation requests?';
$string['connectedto'] = 'Reservation connected to';
$string['connectto'] = 'Connectable reservation from';
$string['crontask'] = 'Send reservation and request grading mails';
$string['description'] = 'Description';
$string['downloadas'] = 'Default download format';
$string['duration10h'] = '10 hours';
$string['duration10min'] = '10 minutes';
$string['duration11h'] = '11 hours';
$string['duration12h'] = '12 hours';
$string['duration15min'] = '15 minutes';
$string['duration20min'] = '20 minutes';
$string['duration2h'] = '2 hours';
$string['duration30min'] = '30 minutes';
$string['duration3h'] = '3 hours';
$string['duration45min'] = '45 minutes';
$string['duration4h'] = '4 hours';
$string['duration5h'] = '5 hours';
$string['duration5min'] = '5 minutes';
$string['duration60min'] = '60 minutes';
$string['duration6h'] = '6 hours';
$string['duration7h'] = '7 hours';
$string['duration8h'] = '8 hours';
$string['duration90min'] = '90 minutes';
$string['duration9h'] = '9 hours';
$string['enablenote'] = 'Enable users note';
$string['equal'] = 'equal to';
$string['err_notimestart'] = 'Event start date is not set';
$string['err_sublimitsgreater'] = 'Sublimits sum is greater than max allowed request';
$string['err_timeendlower'] = 'Event end date is set prior start date';
$string['err_timeopengreater'] = 'Reservation start date is set after end date';
$string['eventevent'] = 'Create an course event from start to end dates (the event)';
$string['eventreminder'] = '{$a} (reserved)';
$string['eventrequestadded'] = 'Reservation request added';
$string['eventrequestcancelled'] = 'Reservation request cancelled';
$string['eventrequestdeleted'] = 'Reservation request deleted';
$string['events'] = 'Calendar events';
$string['eventsettings'] = 'Event Settings';
$string['explainconfig'] = 'Administrators can define here global settings for the Resevation Module';
$string['fields'] = 'Shown fields';
$string['fullview'] = 'View also cancelled reservations';
$string['grade'] = 'Grade';
$string['gradedmail'] = '{$a->teacher} has posted some feedback on your
reservation \'{$a->reservation}\'

You can see it here:

    {$a->url}';
$string['gradedmailhtml'] = '{$a->teacher} has posted some feedback on your
reservation \'<i>{$a->reservation}</i>\'<br /><br />
You can see it <a href=\"{$a->url}\">here</a>.';
$string['importreservations'] = 'Import Reservations';
$string['invalidreservationid'] = 'Invalid reservation ID';
$string['justbooked'] = 'You are booked as: {$a}';
$string['justoverbooked'] = 'You are overbooked as: {$a}';
$string['linenumber'] = '#';
$string['listafterclose'] = 'reservation requests list after reservation was closed';
$string['listalways'] = 'reservation requests list anytime';
$string['location'] = 'Place';
$string['locations'] = 'Manage Locations';
$string['locationslist'] = 'Locations List';
$string['mail'] = 'Reservation \'{$a->reservation}\' has been closed.

You can download reservation list from:

    {$a->url}';
$string['mailhtml'] = 'Reservation <em>{$a->reservation}</em> has been closed.<br /><br />
You can download reservation list from <a href="{$a->url}">here</a>.';
$string['mailrequest'] = 'Reservation \'{$a->reservation}\' has been closed.

You can get your reservation number on:

    {$a->url}';
$string['mailrequesthtml'] = 'Reservation <em>{$a->reservation}</em> has been closed.<br /><br />
You can get your reservation number <a href="{$a->url}">here</a>.';
$string['manage'] = 'Manage';
$string['manualusers'] = 'Manual reserve show users of';
$string['maxoverbook'] = 'Max overbook percentage';
$string['maxrequest'] = 'Max Reservations';
$string['message'] = 'Message to participants';
$string['minduration'] = 'Event minimal duration';
$string['modulename'] = 'Reservation';
$string['modulename_help'] = '<p>The main aim of this activity is schedule laboratory sessions and exams but you can schedule everything you want.</p><p>Teacher can define the number of seats available for the event, event date, reservation opening and closing date.<br />A reservation may have a grade or a scale.<br />Students can book and unbook a seat and add a note about this reservation.</p><p>After the event starts the teacher can grade the event. Students will notified by mail.</p><p>Reservation list may be downloaded in various formats.</p>';
$string['modulenameplural'] = 'Reservations';
$string['newlocation'] = 'New Location';
$string['noclashcheck'] = 'Clash check is not enabled. Please ask to site admin.';
$string['noclashes'] = 'No place and time clashes found';
$string['nocourseswithnsections'] = 'No course found with {$a} sections';
$string['nolimit'] = 'No reservation limit';
$string['nolocations'] = 'No locations defined';
$string['nomorerequest'] = 'No more seats available';
$string['none'] = 'no informations about reservation order';
$string['nooverbook'] = 'No Overbooking';
$string['noparent'] = 'None';
$string['noreservations'] = 'No reservation to show';
$string['note'] = 'Note';
$string['noteachers'] = 'No available teachers';
$string['notequal'] = 'not equal to';
$string['noterequired'] = 'Note required, please enter here before reserve.';
$string['notgraded'] = 'Not graded';
$string['notifies'] = 'Notifies sent';
$string['notifycancellers'] = 'Notify cancellers when they make a successful reservation cancellation';
$string['notifygrades'] = 'Notify reservation graded to students';
$string['notifyoverbookers'] = 'Notify students that have overbooked when they became regular reservers';
$string['notifyreservers'] = 'Notify reservers when they make a successful reservation request';
$string['notifystudents'] = 'Notify reservation time closed to students';
$string['notifyteachers'] = 'Notify reservation time closed to teachers';
$string['notopened'] = 'Not opened';
$string['notreserved'] = 'Not reserved';
$string['novalues'] = 'No available values for this field';
$string['number'] = 'Reservation Number';
$string['numberafterclose'] = 'their reservation request number after reservation was closed';
$string['numberalways'] = 'their reservation request number anytime';
$string['optional'] = 'Optional';
$string['otherlocation'] = 'Other location specified';
$string['overbook'] = 'Overbooking';
$string['overbookonly'] = 'Only overbook seats available';
$string['overbookstep'] = 'Overbook step';
$string['overview'] = 'Overview';
$string['parent'] = 'Connect this reservation with';
$string['pluginadministration'] = 'Reservation administration';
$string['pluginname'] = 'Reservation';
$string['privacy:metadata:reservation_note'] = 'Store user note of a reservation request';
$string['privacy:metadata:reservation_note:note'] = 'User note for the reservation request';
$string['privacy:metadata:reservation_request'] = 'Store user data of a reservation request';
$string['privacy:metadata:reservation_request:grade'] = 'User reservation request grade';
$string['privacy:metadata:reservation_request:grader'] = 'ID of the grader';
$string['privacy:metadata:reservation_request:mailed'] = 'The mailed notification status of the grading';
$string['privacy:metadata:reservation_request:reservationid'] = 'ID of the reservation';
$string['privacy:metadata:reservation_request:timecancelled'] = 'Date and time of request cancel';
$string['privacy:metadata:reservation_request:timecreated'] = 'Date and time of request creation';
$string['privacy:metadata:reservation_request:timegraded'] = 'Date and time of request grade';
$string['privacy:metadata:reservation_request:userid'] = 'ID of the user';
$string['publiclists'] = 'Reservation public list';
$string['requestoverview'] = 'Requests overview';
$string['requests'] = 'Requests';
$string['required'] = 'Required';
$string['reservation:addinstance'] = 'Add a new reservation';
$string['reservation:downloadrequests'] = 'Can download requests list';
$string['reservation:grade'] = 'Can assign grade';
$string['reservation:manage'] = 'Can manage reservation';
$string['reservation:manualdelete'] = 'Can delete other users requests';
$string['reservation:manualreserve'] = 'Can submit request for other users';
$string['reservation:reserve'] = 'Can submit own requests';
$string['reservation:uploadreservations'] = 'Can upload reservations';
$string['reservation:view'] = 'Can view reservation';
$string['reservation:viewnote'] = 'Can view requests note';
$string['reservation:viewrequest'] = 'Can view requests list';
$string['reservation_listing'] = 'Index page settings';
$string['reservation_other'] = 'Other settings';
$string['reservation_settings'] = 'Editing settings';
$string['reservation_view'] = 'View page settings';
$string['reservationcancelled'] = 'Reservation cancelled';
$string['reservationclosed'] = 'Reservations closed';
$string['reservationdenied'] = 'Reservations not allowed';
$string['reservationevent'] = 'Create an course event from open to close dates (reservation time)';
$string['reservationnotopened'] = 'Reservations not yet opened';
$string['reservations'] = 'Reservations';
$string['reservationsettings'] = 'Reservation Settings';
$string['reserve'] = 'Reserve';
$string['reservecancel'] = 'Cancel reservation';
$string['reserved'] = 'Reserved';
$string['reservedon'] = 'Reserved on';
$string['reservedonconnected'] = 'You are already reserved on a connected reservation: {$a}';
$string['reserversmail'] = 'You are reserved to \'{$a->reservation}\' reservation.';
$string['reserversmailhtml'] = 'You are reserved to <em>{$a->reservation}</em> reservation.';
$string['resetreservation'] = 'Remove all reservations';
$string['save'] = 'Save grade';
$string['search:activity'] = 'Reservation - activity information';
$string['selectvalue'] = 'Select one of available values';
$string['showrequest'] = 'Users can view requests list';
$string['showuserrequest'] = 'Users can view';
$string['sortby'] = 'Reservation lists sorted by';
$string['status'] = 'Reservation status';
$string['sublimit'] = 'Sublimit {$a}';
$string['sublimitrules'] = 'Sublimits Rules';
$string['sublimits'] = 'Reservation Sublimits';
$string['timeclose'] = 'Reservation end on';
$string['timeend'] = 'End date';
$string['timeopen'] = 'Reservation start on';
$string['timestart'] = 'Start date';
$string['tools'] = 'Reservation tools';
$string['upload'] = 'Reservations upload';
$string['upload_help'] = '<p>Reservations may be uploaded via text file. The format of the file should be as follows:</p><ul><li>Each line of the file contains one record</li><li>Each record is a series of data separated by commas (or other delimiters)</li><li>The first record contains a list of fieldnames defining the format of the rest of the file</li><li>Required fieldsname are section, name and timestart</li><li>Optional fieldsname are course, intro, teachers, timeend, grade, timeopen, timeclose, maxrequest</li><li>If course is not specified it must be choosen after preview</li></ul>';
$string['uploadreservations'] = 'Upload Reservations';
$string['uploadreservationspreview'] = 'Upload Reservations Preview';
$string['uploadreservationsresult'] = 'Upload Reservations Result';
$string['useralreadybooked'] = 'User already booked';
$string['userevent'] = 'Create an user event from start to end dates when user reserve in a reservation';
$string['with'] = 'with';
$string['withselected'] = 'With selected...';
$string['yourgrade'] = 'Your grade about this reservation is: {$a->grade}/{$a->maxgrade}';
$string['yournote'] = 'Your note:';
$string['yourscale'] = 'Your grade about this reservation is: {$a}';

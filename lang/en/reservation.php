<?php 
/**
 * @package mod
 * @subpackage reservation
 * @author Roberto Pinna (bobo@di.unipmn.it)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Reservation';
$string['pluginadministration'] = 'Reservation administration';

$string['reservation:grade'] = 'Can assign grade';
$string['reservation:reserve'] = 'Can submit own requests';
$string['reservation:viewnote'] = 'Can view requests note';
$string['reservation:viewrequest'] = 'Can view requests list';
$string['reservation:manualreserve'] = 'Can submit request for other users';
$string['reservation:manualdelete'] = 'Can delete other users requests';
$string['reservation:downloadrequests'] = 'Can download requests list';
$string['reservation:addinstance'] = 'Add a new reservation';
$string['reservation:uploadreservations'] = 'Can upload reservations';
$string['search:activity'] = 'Reservation - activity information';

$string['availablerequests'] = 'Available seats';
$string['location'] = 'Place';
$string['otherlocation'] = 'Other location specified';
$string['cancelledon'] = 'Cancelled on';
$string['cleanview'] = 'View only current reserved';
$string['description'] = 'Description';
$string['fullview'] = 'View also deleted reservations';
$string['maxrequest'] = 'Max Reservations';
$string['configmaxrequests'] = 'Define the limit of dropdown menu in reservation edit page';
$string['modulename'] = 'Reservation';
$string['modulenameplural'] = 'Reservations';
$string['nolimit'] = 'No reservation limit';
$string['nomorerequest'] = 'No more seats available';
$string['noreservations'] = 'No reservation to show';
$string['requests'] = 'Requests';
$string['reservationcancelled'] = 'Reservation cancelled';
$string['reservationclosed'] = 'Reservations closed';
$string['reservationdenied'] = 'Reservations not allowed';
$string['reservationnotopened'] = 'Reservations not yet opened';
$string['reservations'] = 'Reservations';
$string['reserve'] = 'Reserve';
$string['reservecancel'] = 'Cancel reservation';
$string['reserved'] = 'Reserved';
$string['reservedon'] = 'Reserved on';
$string['timeclose'] = 'Reservation end on';
$string['timeopen'] = 'Reservation start on';
$string['timestart'] = 'Start date';
$string['timeend'] = 'End date';
$string['save'] = 'Save grade';
$string['justbooked'] = 'You are booked as: {$a}';
$string['alreadybooked'] = 'You are already booked';
$string['yourgrade'] = 'Your grade about this reservation is: {$a->grade}/{$a->maxgrade}';
$string['yourscale'] = 'Your grade about this reservation is: {$a}';
$string['by'] = 'by';
$string['showrequest'] = 'Users can view requests list';
$string['configlocations'] = 'In this page you can manage standard locations for reservations in this Moodle site';
$string['locations'] = 'Manage Locations';
$string['locationslist'] = 'Locations List';
$string['newlocation'] = 'New Locations';
$string['resetreservation'] = 'Remove all reservations';
$string['withselected'] = 'With selected...';
$string['note'] = 'Note';
$string['enablenote'] = 'Enable users note';
$string['notopened'] = 'Not opened';
$string['closed'] = 'Closed';
$string['fields'] = 'Shown fields';
$string['configfields'] = 'This setting define which fields will shown in reservations table';
$string['config'] = 'Reservation module global settings';
$string['explainconfig'] = 'Administrators can define here global settings for the Resevation Module';
$string['addparticipant'] = 'Add request';
$string['noteachers'] = 'No available teachers';
$string['reservationsettings'] = 'Reservation Settings';
$string['eventsettings'] = 'Event Settings';
$string['sublimit'] = 'Sublimit {$a}';
$string['with'] = 'with';
$string['equal'] = 'equal to';
$string['notequal'] = 'not equal to';
$string['sublimits'] = 'Reservation Sublimits';
$string['configsublimits'] = 'Define the number of sublimits rules row in reservation edit page';
$string['err_timeendlower'] = 'Event end date is set prior start date';
$string['err_timeopengreater'] = 'Reservation start date is set after end date';
$string['err_sublimitsgreater'] = 'Sublimits sum is greater than max allowed request';
$string['overbook'] = 'Overbooking';
$string['nooverbook'] = 'No Overbooking';
$string['maxoverbook'] = 'Max overbook percentage';
$string['configmaxoverbook'] = 'This define the max percentage of overbooking for reservations.';
$string['overbookstep'] = 'Overbook step';
$string['configoverbookstep'] = 'This define the percentage granularity of overbooking. Smaller step, greater granularity';
$string['overbookonly'] = 'Only overbooked seats available';
$string['requestoverview'] = 'Requests overview';
$string['sublimitrules'] = 'Sublimits Rules';
$string['selectvalue'] = 'Select one of available values';
$string['novalues'] = 'No available values for this field';
$string['close'] = 'close';
$string['manualusers'] = 'Manual reserve show users of';
$string['configmanualusers'] = 'This define what list of users is shown in dropdown menu used to manual reserve users.';
$string['autohide'] = 'Reservation list auto hide';
$string['configautohide'] = 'This define when reservations must be hidded from reservation list (mod/reservation/index.php). This could be useful if used with public lists, in order to display a cleaned list.';
$string['atstart'] = 'At event start';
$string['after5min'] = 'After 5 minutes from event start';
$string['after10min'] = 'After 10 minutes from event start';
$string['after30min'] = 'After 30 minutes from event start';
$string['after1h'] = 'After 1 hour from event start';
$string['after2h'] = 'After 2 hours from event start';
$string['after4h'] = 'After 4 hours from event start';
$string['after6h'] = 'After 6 hours from event start';
$string['after12h'] = 'After 12 hours from event start';
$string['after1d'] = 'After 1 day from event start';
$string['after2d'] = 'After 2 days from event start';
$string['after1w'] = 'After 1 week from event start';
$string['after2w'] = 'After 2 weeks from event start';
$string['after3w'] = 'After 3 weeks from event start';
$string['after4w'] = 'After 4 weeks from event start';
$string['publiclists'] = 'Reservation public list';
$string['configpubliclists'] = 'This define if reservation lists are public (viewed without login) or not.';
$string['sortby'] = 'Reservation lists sorted by';
$string['configsortby'] = 'This define how reservation list are sorted.';
$string['bysection'] = 'Topic/Week';
$string['bydate'] = 'Event date';
$string['byname'] = 'Name';
$string['number'] = 'Reservation Number';
$string['linenumber'] = '#';
$string['gradedmail'] = '{$a->teacher} has posted some feedback on your
reservation \'{$a->reservation}\'

You can see it here:

    {$a->url}';
$string['gradedmailhtml'] = '{$a->teacher} has posted some feedback on your
reservation \'<i>{$a->reservation}</i>\'<br /><br />
You can see it <a href=\"{$a->url}\">here</a>.';
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
$string['configdownload'] = 'This define the default download file format for all request and reservation lists.';
$string['configcheckclashes'] = 'Enable "Check place and time clashes" button in reservation editing page';
$string['checkclashes'] = 'Check place and time clashes';
$string['clashesreport'] = 'Clashes report';
$string['noclashes'] = 'No place and time clashes found';
$string['clashesfound'] = 'Some place or time clashes found';
$string['minduration'] = 'Event minimal duration';
$string['configminduration'] = 'This indicates reservation event minimal duration. It is used with non-ending events to check time and place availability';
$string['duration5min'] = '5 minutes';
$string['duration10min'] = '10 minutes';
$string['duration15min'] = '15 minutes';
$string['duration20min'] = '20 minutes';
$string['duration30min'] = '30 minutes';
$string['duration45min'] = '45 minutes';
$string['duration60min'] = '60 minutes';
$string['duration90min'] = '90 minutes';
$string['duration2h'] = '2 hours';
$string['duration3h'] = '3 hours';
$string['duration4h'] = '4 hours';
$string['duration5h'] = '5 hours';
$string['duration6h'] = '6 hours';
$string['duration7h'] = '7 hours';
$string['duration8h'] = '8 hours';
$string['duration9h'] = '9 hours';
$string['duration10h'] = '10 hours';
$string['duration11h'] = '11 hours';
$string['duration12h'] = '12 hours';

$string['upload'] = 'Reservations upload';
$string['upload_help'] = '<p>Reservations may be uploaded via text file. The format of the file should be as follows:</p><ul><li>Each line of the file contains one record</li><li>Each record is a series of data separated by commas (or other delimiters)</li><li>The first record contains a list of fieldnames defining the format of the rest of the file</li><li>Required fieldsname are section, name and timestart</li><li>Optional fieldsname are course, intro, teachers, timeend, maxgrade, timeopen, timeclose, maxrequest</li><li>If course is not specified it must be choosen after preview</li></ul>';
$string['uploadreservations'] = 'Upload Reservations';
$string['uploadreservationsresult'] = 'Upload Reservations Result';
$string['importreservations'] = 'Import Reservations';
$string['uploadreservationspreview'] = 'Upload Reservations Preview';
$string['badcourse'] = 'Course does not esists';
$string['badteachers'] = 'Specified teacher email with ({$a}) not found';
$string['badteachersmail'] = 'Specified teacher email ({$a}) is bogus';
$string['badsection'] = 'Section does not esists in course "{$a}"';
$string['badtimestart'] = 'timestart is bogus';
$string['badtimeend'] = 'timeend is bogus';
$string['badtimeopen'] = 'timeopen is bogus';
$string['badtimeclose'] = 'timeclose is bogus';
$string['nocourseswithnsections'] = 'No course found with {$a} sections';
$string['parent'] = 'Connect this reservation with';
$string['noparent'] = 'None';
$string['connectto'] = 'Connectable reservation from';
$string['configconnectto'] = 'Define where the module search reservations to connect with';
$string['connectedto'] = 'Reservation connected to';
$string['reservedonconnected'] = 'You are already reserved on a connected reservation: {$a}';
$string['overview'] = 'Overview';
$string['manage'] = 'Manage';
$string['confirmdelete'] = 'Are you sure that you want to delete the selected reservation requests?';
$string['notifies'] = 'Notifies sent';
$string['confignotifies'] = 'This setting define which notifies must sent';
$string['notifyteachers'] = 'Notify reservation time closed to teachers';
$string['notifystudents'] = 'Notify reservation time closed to students';
$string['notifygrades'] = 'Notify reservation graded to students';
$string['events'] = 'Calendar events';
$string['configevents'] = 'This setting define which events will be created for every reservation';
$string['reservationevent'] = 'Create an event from open to close dates (reservation time)';
$string['eventevent'] = 'Create an event from start to end dates (the event)';
$string['downloadas'] = 'Default download format';
$string['reservation_settings'] = 'Editing settings';
$string['reservation_listing'] = 'Index page settings';
$string['reservation_view'] = 'View page settings';
$string['reservation_other'] = 'Other settings';
$string['message'] = 'Message to participants';
$string['eventrequestadded'] = 'Reservation request added';
$string['eventrequestcancelled'] = 'Reservation request cancelled';
$string['eventrequestdeleted'] = 'Reservation request deleted';
$string['modulename_help'] = '<p>The main aim of this activity is schedule laboratory sessions and exams but you can schedule everything you want.</p><p>Teacher can define the number of seats available for the event, event date, reservation opening and closing date.<br />A reservation may have a grade or a scale.<br />Students can book and unbook a seat and add a note about this reservation.</p><p>After the event starts the teacher can grade the event. Students will notified by mail.</p><p>Reservation list may be downloaded in various formats.</p>';
$string['completionreserved'] = 'Student must reserve to complete this activity';
$string['badparent'] = 'This reservation was connected to another reservation not restored now. The connection has been removed. If needed reconnect them manually';
?>

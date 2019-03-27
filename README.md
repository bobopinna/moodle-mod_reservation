moodle-mod_reservation [![Build Status](https://travis-ci.org/bobopinna/moodle-mod_reservation.svg?branch=master)](https://travis-ci.org/bobopinna/moodle-mod_reservation)
======================
Reservation module

This module permits to schedule an event with a defined reservation time.
The main targets of this module are schedule laboratory sessions and exams but you can schedule everything you want.

Teacher can define the number of seats available for the event, event date, reservation opening and closing date.
A reservation may have a grade or a scale.
Students can book and unbook a seat and add a note about this reservation.

After the event starts the teacher can grade the event. Students and teachers may will notified by mail.

Reservation list may be downloaded in various formats.

- teacher can also define multiple sublimits for available seats basis on user profile fields;
- reservation permits overbooking, also for sublimits;
- teacher can manually reserve seats for other user;
- teacher can send messages to reserved users;
- admin can define which profile fields are shown in reservation list table in reservation module settings;
- reservation can be connected to another reservation so students can reserve to only one of them;
- managers and administrators can upload list of reservation through a CSV file;

## Changelog
* v3.6
  * Full code revision
  * Added option to make Note field required
* v3.5
  * Added Moodle Privacy API support
  * Fixed note display to students
* v3.4
  * Disabled overbooking when no reservation limit is set
  * Added option to do not show reservation number to students
  * No time limit to manual reservation 
* v3.3
  * Update to Moodle 3.3
  * Added options in what student can view in reservation page (number, list and when)
* v3.2
  * Added options to choose which calendar events must be created with reservation
  * Moved italian translation on AMOS
  * Fixed long sheet name in excel downloading error
* v3.1
  * Added Global Search support
* v3.0
  * Fixed compatibility with Moodle 2.7/2.8
  * Changed plugin icon
* v2.9
  * Added support on activity completion with rule reserved

## Install

1. Copy the plugin directory "reservation" into moodle/mod/. 
2. Check admin notifications to install.
3. Done

## Maintainer

The module is being maintained by Roberto Pinna

## Thanks to

With thanks to various friends for contributing (Angelo, Matteo, Wiktor, Cecilia, Francesco).
Thanks also to users who have taken the time to share feedback and improve the module.

## Technical Support

Issue tracker can be found on [GitHub](https://github.com/bobopinna/moodle-mod_reservation/issues).
Please try to give as much detail about your problem as possible and I'll do my best to help.

## License

Released Under the GNU General Public Licence http://www.gnu.org/copyleft/gpl.html


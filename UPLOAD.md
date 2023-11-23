Reservations may be uploaded via text file. 
The format of the file should be as follows:
- Each line of the file contains one record
- Each record is a series of data separated by commas (or other delimiters)
- The first record contains a list of fieldnames defining the format of the rest of the file
- Required fieldnames are section, name and timestart
- Optional fieldnames are course, intro, location, teachers, timeend, grade, timeopen, timeclose, note, maxrequest
- If course is not specified it must be choosen after preview

The fields content needs to be in these formats:
- section: section number (general section is number 0)
- name: reservation name text
- timestart: event start date and time in a format that could be decoded by [strtotime](https://www.php.net/manual/en/datetime.formats.php) PHP function
- course: course shortname
- intro: reservation description text (Moodle format)
- location: text
- teachers: list of email addresses colon (:) separated
- timeend: event end date and time in a format that could be decoded by [strtotime](https://www.php.net/manual/en/datetime.formats.php) PHP function
- grade: maxgrade or scale negative id number
- timeopen: reservation open date and time in a format that could be decoded by [strtotime](https://www.php.net/manual/en/datetime.formats.php) PHP function
- timeclose: reservation close date and time in a format that could be decoded by [strtotime](https://www.php.net/manual/en/datetime.formats.php) PHP function
- note: 0 for no notes, 1 for optional notes, 2 for required notes
- maxrequest: number of seats available for reservation

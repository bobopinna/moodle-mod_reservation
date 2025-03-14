# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]

### [4.2.1] - 2024-02-15
### Fixed
- Completion management for Moodle 4.x - thanks to @attilioariadne1985

### [4.2] - 2024-02-15
### Fixed
- Codings style

## [4.0.1] and [3.9.2] - 2022-11-08
### Fixed
- Teacher notifications

### Changed
- Button tags from input to button - thanks to @Kemmotar83

## [4.0] - 2022-04-27
### Added
- Support for Moodle 4.0
- New icons

### Changed
- deprecated print_error use to exception throw

### Fixed
- Coding styles

## [3.9.1] - 2021-09-16
### Added
- CLI script to fix reservation orphan modules
- Username field choice in reservation reports

### Changed
- Reservation deletion clean up order
- Moved cron utility functions from lib.php to cron_task.php

### Fixed
- Check for user email stop

## [3.9] - 2020-08-19
### Added
- Support for Moodle 3.9
- Notification choice for reservation and cancellation

### Changed
- Cron from lib.php to scheduled task class
- Request limit counters
- Moved reservation upload tracker from locallib.php to dedicated class

### Fixed
- User event creation on manual and self reserve

## [3.6] - 2019-03-20
### Added
- Option to make Note field required

### Changed
- Full code revision

## [3.5] - 2018-10-06
### Added
- Moodle Privacy API support

### Fixed
- Note display to students

## [3.4] - 2017-11-06
### Added
- Option to do not show reservation number to students

### Changed
- Disabled overbooking when no reservation limit is set
- No time limit to manual reservation 

## [3.3.1] - 2017-11-03


## [3.3] - 2017-05-24
### Added
- Support to Moodle 3.3
- Options in what student can view in reservation page (number, list and when)

## [3.2] - 2017-02-21
### Added
- Options to choose which calendar events must be created with reservation

### Changed
- Moved italian translation on AMOS

### Fixed
- Long sheet name in excel downloading error

## [3.1] - 2016-07-11
### Added
- Global Search support

### Changed
- Removed default download format setting

## [3.0] - 2015-11-26
### Changed
- Plugin icon

### Fixed
- Compatibility with Moodle 2.7/2.8

## [2.7.1] - 2015-11-18
### Added
- Support for Moodle 3.0
- Support on activity completion with rule reserved

## [2.7] - 2014-08-07
### Added
- Support for Moodle 2.7
- First release on GitHub

<?php
$addons = array(
    "mod_reservation" => array( // Plugin identifier
    	'handlers' => array( // Different places where the plugin will display content.
            'coursereservation' => array( // Handler unique name (alphanumeric).
            	'displaydata' => array(
                	'icon' => $CFG->wwwroot . '/mod/reservation/pix/icon.gif',
                	'class' => '',
            	),
 
            	'delegate' => 'CoreCourseModuleDelegate', // Delegate (where to display the link to the plugin)
            	'method' => 'mobile_course_view', // Main function in \mod_reservation\output\mobile
            	'offlinefunctions' => array(
                    'mobile_course_view' => array(),
                    'mobile_requests_view' => array()
                 )       // Function that needs to be downloaded for offline.
            )
    	),
	'lang' => array(	// Language strings that are used in all the handlers.
                array('pluginname', 'reservation'),
                array('getreservation', 'reservation'),
                array('requiredtimenotmet', 'reservation'),
		array('viewreservationviews', 'reservation')
        ),
    )
);

<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Updater
Plugin URI: https://www.websmithing.com/gps-tracker
Description: This parses the GET request from the phone and updates the db with locations.
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

if (!class_exists('Gps_Tracker_Updater')) {
    class Gps_Tracker_Updater {
        
        function __construct() {
            register_activation_hook(__FILE__, array(get_class($this), 'activate'));
            register_deactivation_hook(__FILE__, array(get_class($this), 'deactivate'));
            register_uninstall_hook(__FILE__, array(get_class($this), 'uninstall'));
        
            // to test this plugin, add this shortcode to any page or post: [gps_tracker_route] 
            add_shortcode('gps_tracker_route', array($this,'gpstracker_shortcode'));
      
            add_filter('query_vars', array($this, 'gpstracker_query_vars'));
            add_action('parse_request', array($this, 'parse_location_request'));
        }

        static function activate() {      
            global $wpdb;
            global $charset_collate;
            $table_name = $wpdb->prefix . 'gps_locations';
        
            $sql = "DROP TABLE IF EXISTS {$table_name};  
                CREATE TABLE {$table_name} (
                gps_location_id int(10) unsigned NOT NULL AUTO_INCREMENT,
                last_update timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                latitude decimal(10,6) NOT NULL DEFAULT '0.000000',
                longitude decimal(10,6) NOT NULL DEFAULT '0.000000',
                phone_number varchar(50) NOT NULL DEFAULT '',
                session_id varchar(50) NOT NULL DEFAULT '',
                speed int(10) unsigned NOT NULL DEFAULT '0',
                direction int(10) unsigned NOT NULL DEFAULT '0',
                distance decimal(10,1) NOT NULL DEFAULT '0.0',
                gps_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                location_method varchar(50) NOT NULL DEFAULT '',
                accuracy int(10) unsigned NOT NULL DEFAULT '0',
                extra_info varchar(255) NOT NULL DEFAULT '',
                event_type varchar(50) NOT NULL DEFAULT '',
                UNIQUE KEY (gps_location_id),
                KEY session_id_index (session_id),
                KEY phone_number_index (phone_number)
                ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); 
            dbDelta($sql);
        
            $location_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name};" );
        
            if ($location_row_count == 0) {
                $wpdb->insert( 
            	$table_name, 
            	array( 
            		'latitude' => 47.475931, 
            		'longitude' => -122.021119,
                    'phone_number' => 'wordpressUser',
                    'session_id' => '1111-1111-1111-1111-1111-1111',
                    'speed' => 137,
                    'direction' => 237,
                    'distance' => 137.0,
                    'gps_time' => '2014-03-07 01:37:00',
                    'location_method' => 'na',
                    'accuracy' => 37,
                    'extra_info' => 'na',
                    'event_type' => 'wordpress init'   
            	), 
            	array( 
            		'%f', '%f', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s'
            	    ) 
                );              
            }

            $procedure_name =  $wpdb->prefix . "get_routes";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};"); 
           
            $sql = "CREATE PROCEDURE {$procedure_name}()
            BEGIN
            CREATE TEMPORARY TABLE temp_routes (
                session_id VARCHAR(50),
                phone_number VARCHAR(50),
                start_time DATETIME,
                end_time DATETIME)
                ENGINE = MEMORY;

            INSERT INTO temp_routes (session_id, phone_number)
            SELECT DISTINCT session_id, phone_number
            FROM {$table_name};

            UPDATE temp_routes tr
            SET start_time = (SELECT MIN(gps_time) FROM {$table_name} gl
            WHERE gl.session_id = tr.session_id
            AND gl.phone_number = tr.phone_number);

            UPDATE temp_routes tr
            SET end_time = (SELECT MAX(gps_time) FROM {$table_name} gl
            WHERE gl.session_id = tr.session_id
            AND gl.phone_number = tr.phone_number);

            SELECT
            CONCAT('{ \"session_id\": \"', CAST(session_id AS CHAR),  '\", \"phone_number\": \"', phone_number, '\", \"times\": \"(', DATE_FORMAT(start_time, '%b %e %Y %h:%i%p'), ' - ', DATE_FORMAT(end_time, '%b %e %Y %h:%i%p'), ')\" }') json
            FROM temp_routes
            ORDER BY start_time DESC;

            DROP TABLE temp_routes;
            END;";                
            
            $wpdb->query($sql); 
            // $wpdb->print_error();
        
            $procedure_name =  $wpdb->prefix . "get_route_for_map";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};");
        
            $sql = "CREATE PROCEDURE {$procedure_name}(
            _session_id VARCHAR(50),
            _phone_number VARCHAR(50))
            BEGIN
            SELECT
            CONCAT('<locations latitude=\"', CAST(latitude AS CHAR),'\" longitude=\"', CAST(longitude AS CHAR), '\" speed=\"', CAST(speed AS CHAR), '\" direction=\"', CAST(direction AS CHAR), '\" distance=\"', CAST(distance AS CHAR), '\" location_method=\"', location_method, '\" gps_time=\"', DATE_FORMAT(gps_time, '%b %e %Y %h:%i%p'), '\" phone_number=\"', phone_number,'\" session_id=\"', CAST(session_id AS CHAR), '\" accuracy=\"', CAST(accuracy AS CHAR), '\" extraInfo=\"', extraInfo, '\" />') xml
            FROM {$table_name}
            WHERE session_id = _session_id
            AND phoneNumber = _phone_number
            ORDER BY last_update;
            END;";
                    
            $wpdb->query($sql);  
        
            // exit(var_dump($_GET));
        }

        static function deactivate() {

            ////////////////////////////////////////////
            // need to remove this
            ////////////////////////////////////////////
        
            global $wpdb;
            $table_name = $wpdb->prefix . 'gps_locations';
            $sql = "DROP TABLE IF EXISTS {$table_name};";
            $wpdb->query($sql);
        
            $procedure_name =  $wpdb->prefix . "get_routes";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};");
        
            $procedure_name =  $wpdb->prefix . "get_route_for_map";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};");
        }
    
        static function uninstall() {
            if (!current_user_can('activate_plugins')) {
                return;
            }          
            check_admin_referer('bulk-plugins');

            // check if the file is the one that was registered during the uninstall hook.
            if ( __FILE__ != WP_UNINSTALL_PLUGIN) {
                return;
            }
        
            global $wpdb;
            $table_name = $wpdb->prefix . 'gps_locations';
            $sql = "DROP TABLE IF EXISTS {$table_name};";
            $wpdb->query($sql);
        
            $procedure_name =  $wpdb->prefix . "get_routes";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};");
        
            $procedure_name =  $wpdb->prefix . "get_route_for_map";
            $wpdb->query("DROP PROCEDURE IF EXISTS {$procedure_name};");
        }
    
        function gpstracker_shortcode($atts) {
            // default values
            extract(shortcode_atts(array(
                'latitude'         => '47.475931',
                'longitude'        => '-122.021119',
                'phonenumber'      => 'wordpressUser',
                'sessionid'        => '1111-1111-1111-1111-1111-1111',
                'speed'            => '137',
                'direction'        => '237',
                'distance'         => '137.0',
                'gpstime'          => '2014-03-07 11:37:00',
                'locationmethod'   => 'na',
                'accuracy'         => '37',
                'extrainfo'        => 'na',
                'eventtype'        => 'wordpress default'
            ), $atts));
            
            return sprintf('<a href="%s">Gps Tracker Updater</a>',$this->gpstracker_url($latitude, $longitude, $phonenumber, $sessionid, $speed, $direction, $distance, urlencode($gpstime), $locationmethod, $accuracy, $extrainfo, urlencode($eventtype)));
        }
    
        function gpstracker_url($latitude, $longitude, $phonenumber, $sessionid, $speed, $direction,
                $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype) {

                $update_location_query = '%s/index.php?latitude=%s&longitude=%s&phonenumber=%s&sessionid=%s&speed=%s&direction=%s&distance=%s&gpstime=%s&locationmethod=%s&accuracy=%s&extrainfo=%s&eventtype=%s';

                return sprintf($update_location_query, home_url(), $latitude, $longitude, $phonenumber, $sessionid, $speed, $direction, $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype);
        }

        function gpstracker_query_vars($query_vars) {
            $query_vars[] = 'latitude';
            $query_vars[] = 'longitude';
            $query_vars[] = 'phonenumber';
            $query_vars[] = 'sessionid';
            $query_vars[] = 'speed';
            $query_vars[] = 'direction';
            $query_vars[] = 'distance';
            $query_vars[] = 'gpstime';
            $query_vars[] = 'locationmethod';
            $query_vars[] = 'accuracy';
            $query_vars[] = 'extrainfo';
            $query_vars[] = 'eventtype';        
            return $query_vars;
        }
    
        function parse_location_request($wp_query) {
            if (
                isset($wp_query->query_vars['latitude']) && 
                isset($wp_query->query_vars['longitude']) &&
                isset($wp_query->query_vars['phonenumber']) &&
                isset($wp_query->query_vars['sessionid']) &&
                isset($wp_query->query_vars['speed']) &&
                isset($wp_query->query_vars['direction']) &&
                isset($wp_query->query_vars['distance']) &&
                isset($wp_query->query_vars['gpstime']) &&
                isset($wp_query->query_vars['locationmethod']) &&
                isset($wp_query->query_vars['accuracy']) &&
                isset($wp_query->query_vars['extrainfo']) &&
                isset($wp_query->query_vars['eventtype'])    
                ) { 

                global $wpdb;
                $table_name = $wpdb->prefix . 'gps_locations';
            
                $wpdb->insert( 
            	$table_name, 
            	array( 
            		'latitude' => $wp_query->query_vars['latitude'], 
            		'longitude' => $wp_query->query_vars['longitude'],
                    'phone_number' => $wp_query->query_vars['phonenumber'],
                    'session_id' => $wp_query->query_vars['sessionid'],
                    'speed' => $wp_query->query_vars['speed'],
                    'direction' => $wp_query->query_vars['direction'],
                    'distance' => $wp_query->query_vars['distance'],
                    'gps_time' => urldecode($wp_query->query_vars['gpstime']),
                    'location_method' => $wp_query->query_vars['locationmethod'],
                    'accuracy' => $wp_query->query_vars['accuracy'],
                    'extra_info' => $wp_query->query_vars['extrainfo'],
                    'event_type' => urldecode($wp_query->query_vars['eventtype'])
            	), 
            	array( 
            		'%f', '%f', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s'
            	    ) 
                );
            
                // testing
                printf("<pre>%s</pre>", print_r($wp_query->query_vars, true));
                exit(0);
            }
        }
    }

    $Gps_Tracker_Updater = new Gps_Tracker_Updater();
}
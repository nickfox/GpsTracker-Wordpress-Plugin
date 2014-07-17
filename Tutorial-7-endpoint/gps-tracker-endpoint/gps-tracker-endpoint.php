<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Endpoint
Plugin URI: https://www.websmithing.com/gps-tracker
Description: Create an endpoint for Gps Tracker.
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

function gpstracker_add_endpoint() {
    add_rewrite_endpoint('gpstracker', EP_ROOT);
}
add_action('init', 'gpstracker_add_endpoint');
 
function gpstracker_template_redirect() {
    global $wp_query;

    if (!isset($wp_query->query_vars['gpstracker'])) {
        return;
    }
        
    switch ($wp_query->query_vars['gpstracker']) {
        case 'nonce':
            $session_id = isset($wp_query->query_vars['sessionid']) ? $wp_query->query_vars['sessionid'] : '0';
            $session_id_pattern = '/^[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$/';    
          
            if (preg_match($session_id_pattern, $session_id)) {
                echo wp_create_nonce($session_id);
            } else {
                echo '0';
            }

            break;
        case 'location':
            $session_id = isset($wp_query->query_vars['sessionid']) ? $wp_query->query_vars['sessionid'] : '0';
            $wpnonce = isset($wp_query->query_vars['wpnonce']) ? $wp_query->query_vars['wpnonce'] : '1';
            
            if (!wp_verify_nonce($wpnonce, $session_id)) {
                echo '0';
                exit;
            }
        
            $latitude = isset($wp_query->query_vars['latitude']) ? $wp_query->query_vars['latitude'] : '0.0';
            $longitude = isset($wp_query->query_vars['longitude']) ? $wp_query->query_vars['longitude'] : '0.0';
            $user_name = isset($wp_query->query_vars['username']) ? $wp_query->query_vars['username'] : 'wordpressUser';
            $speed = isset($wp_query->query_vars['speed']) ? $wp_query->query_vars['speed'] : '0';
            $direction = isset($wp_query->query_vars['direction']) ? $wp_query->query_vars['direction'] : '0';
            $distance = isset($wp_query->query_vars['distance']) ? $wp_query->query_vars['distance'] : '0';
            $gps_time = isset($wp_query->query_vars['gpstime']) ? urldecode($wp_query->query_vars['gpstime']) : '0000-00-00 00:00:00';
            $location_method = isset($wp_query->query_vars['locationmethod']) ? $wp_query->query_vars['locationmethod'] : '0';
            $accuracy = isset($wp_query->query_vars['accuracy']) ? $wp_query->query_vars['accuracy'] : '0';
            $extra_info = isset($wp_query->query_vars['extrainfo']) ? urldecode($wp_query->query_vars['extrainfo']) : '';
            $event_type = isset($wp_query->query_vars['eventtype']) ? $wp_query->query_vars['eventtype'] : 'wordpress';

            global $wpdb;
            $table_name = $wpdb->prefix . 'gps_locations';

            $wpdb->insert( 
        	$table_name, 
        	array( 
                'latitude' => $latitude, 
                'longitude' => $longitude,
                'user_name' => $user_name,
                'session_id' => $session_id,
                'speed' => $speed,
                'direction' => $direction,
                'distance' => $distance,
                'gps_time' => $gps_time,
                'location_method' => $location_method,
                'accuracy' => $accuracy,
                'extra_info' => $extra_info,
                'event_type' => $event_type
        	), 
        	array( 
        		'%f', '%f', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s'
        	    ) 
            );
            
            break;
    }
        
    exit;
}
add_action('template_redirect', 'gpstracker_template_redirect');

function gpstracker_query_vars($query_vars) {
    $query_vars[] = 'latitude';
    $query_vars[] = 'longitude';
    $query_vars[] = 'username';
    $query_vars[] = 'sessionid';
    $query_vars[] = 'speed';
    $query_vars[] = 'direction';
    $query_vars[] = 'distance';
    $query_vars[] = 'gpstime';
    $query_vars[] = 'locationmethod';
    $query_vars[] = 'accuracy';
    $query_vars[] = 'extrainfo';
    $query_vars[] = 'eventtype';
    $query_vars[] = 'wpnonce';        
    return $query_vars;
}
add_filter('query_vars', 'gpstracker_query_vars');

function activate() {
    gpstracker_add_endpoint();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'activate');
 
function deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'deactivate');
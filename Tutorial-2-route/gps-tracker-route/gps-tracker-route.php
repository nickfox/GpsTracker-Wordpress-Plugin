<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Route
Plugin URI: https://www.websmithing.com/gps-tracker
Description: This plugin creates a route using the rewrite api which allows the phone to send location data to Gps Tracker using the proper way to access wordpress.
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

class Gps_Tracker_Updater {
    private $update_location_route = 'gps-tracker\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)$';
    private $update_location_query = 'index.php?latitude=%s&longitude=%s&phonenumber=%s&sessionid=%s&speed=%s&direction=%s&distance=%s&gpstime=%s&locationmethod=%s&accuracy=%s&extrainfo=%s&eventtype=%s';
    private $update_location_permalink = 'gps-tracker/%s/%s/%s/%s/%s/%s/%s/%s/%s/%s/%s/%s';

    function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array($this, 'uninstall'));
        
        // to test this plugin, add this shortcode to any page or post: [gps-tracker-route]
        // to find rewrite rule in db, replace wp_ with your wordpress table prefix
        // select * from wp_options where option_name = 'rewrite_rules';    
        add_shortcode('gps-tracker-route', array($this,'gpstracker_shortcode'));
      
        add_action('generate_rewrite_rules', array($this, 'gpstracker_rewrite_rules'));
        add_filter('query_vars', array($this, 'gpstracker_query_vars'));
        add_action('parse_request', array($this, 'parse_location_request'));
    }

    function activate() {
        global $wp_rewrite; 
        $wp_rewrite->flush_rules();
        
        # exit(var_dump($_GET));
    }

    function deactivate() {
        remove_action('generate_rewrite_rules', array($this, 'gpstracker_rewrite_rules'));
        $rules = $GLOBALS['wp_rewrite']->wp_rewrite_rules();
        
        if (!isset($rules[$this->update_location_route])) {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }
        
        # exit(var_dump($_GET));
    }
    
    function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }          
        check_admin_referer('bulk-plugins');

        // check if the file is the one that was registered during the uninstall hook.
        if ( __FILE__ != WP_UNINSTALL_PLUGIN) {
            return;
        }
    }
    
    function gpstracker_shortcode($atts) {
        extract(shortcode_atts(array(
            // default values
            'latitude'         => '47.61',
            'longitude'        => '-122.33',
            'phonenumber'      => 'webUser',
            'sessionid'        => '1111-1111-1111-1111-1111-1111',
            'speed'            => '137',
            'direction'        => '237',
            'distance'         => '137.0',
            'gpstime'          => '2014-03-03%2013:22:08',
            'locationmethod'   => 'na',
            'accuracy'         => '37',
            'extrainfo'        => 'na',
            'eventtype'        => 'na'
        ), $atts));
        return sprintf('<a href="%s">Gps Tracker Route</a>',$this->gpstracker_url($latitude, $longitude, $phonenumber, $sessionid, 
            $speed, $direction, $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype));
    }
    
    function gpstracker_url($latitude, $longitude, $phonenumber, $sessionid, $speed, $direction,
                $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype) {
        if (get_option('permalink_structure')) { // check if the blog has a permalink structure
            $gpstracker_permalink_url = '%s/' . $this->update_location_permalink;
            return sprintf($gpstracker_permalink_url, home_url(), $latitude, $longitude, $phonenumber, $sessionid, $speed, $direction,
                $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype);
        } else {
            $gpstracker_url = '%s/' . $this->update_location_query;
            return sprintf($gpstracker_url, home_url(), $latitude, $longitude, $phonenumber, $sessionid, $speed, $direction,
                $distance, $gpstime, $locationmethod, $accuracy, $extrainfo, $eventtype);
        }
    }
    
    function gpstracker_rewrite_rules($wp_rewrite) {
        $new_rules = array(
            $this->update_location_route => sprintf($this->update_location_query,
                $wp_rewrite->preg_index(1),
                $wp_rewrite->preg_index(2),
                $wp_rewrite->preg_index(3),
                $wp_rewrite->preg_index(4),
                $wp_rewrite->preg_index(5),
                $wp_rewrite->preg_index(6),
                $wp_rewrite->preg_index(7),
                $wp_rewrite->preg_index(8),
                $wp_rewrite->preg_index(9),
                $wp_rewrite->preg_index(10),
                $wp_rewrite->preg_index(11),
                $wp_rewrite->preg_index(12))     
        );
      
        $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
        return $wp_rewrite->rules;
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
            // put code here
            
            // testing
            printf("<pre>%s</pre>",print_r($wp_query->query_vars, true));
            exit(0);
        }
    }
}

$Gps_Tracker_Updater = new Gps_Tracker_Updater();
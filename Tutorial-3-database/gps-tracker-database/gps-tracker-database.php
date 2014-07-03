<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Database
Plugin URI: https://www.websmithing.com/gps-tracker
Description: This plugin creates the database table and stored procedures for Gps Tracker
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

if (!class_exists('Gps_Tracker')) {
    class Gps_Tracker {
            
        public function __construct() {            
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_shortcode('gps-tracker', array($this, 'map_shortcode'));
        }

        public static function activate() {
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
                $sql = "INSERT INTO {$table_name} VALUES (45,'2014-03-03 13:22:10',47.475931,-122.021119,'wordpressUser','8BA21D90-3F90-407F-BAAE-800B04B1F5EC',0,0,0.0,'2014-03-03 13:22:08','n/a',65,'altitude: 120m','ios');";            
                $wpdb->query($sql);                
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
            CONCAT('<locations latitude=\"', CAST(latitude AS CHAR),'\" longitude=\"', CAST(longitude AS CHAR),
                  '\" speed=\"', CAST(speed AS CHAR), '\" direction=\"', CAST(direction AS CHAR), '\" distance=\"', CAST(distance AS CHAR),
                  '\" location_method=\"', location_method, '\" gps_time=\"', DATE_FORMAT(gps_time, '%b %e %Y %h:%i%p'), '\" phone_number=\"', phone_number,
                  '\" session_id=\"', CAST(session_id AS CHAR), '\" accuracy=\"', CAST(accuracy AS CHAR), '\" extraInfo=\"', extraInfo, '\" />') xml
            FROM {$table_name}
            WHERE session_id = _session_id
            AND phoneNumber = _phone_number
            ORDER BY last_update;
            END;";
                        
            $wpdb->query($sql);               
        }
        
        // THIS NEEDS TO BE MOVED TO UNINSTALL.PHP
        public static function deactivate() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gps_locations';
            $sql = "DROP TABLE IF EXISTS {$table_name};";
            // $wpdb->query($sql);
        }
        
        function add_action_links($links) {
             $links[] = '<a href="' . admin_url('admin.php?page=gps-tracker') . '">Settings</a>';
             return $links;
        }
        
        public function admin_init() {
            wp_register_style('leaflet_admin_stylesheet', plugins_url('style.css', __FILE__));
        }

        public function admin_menu() {
            add_menu_page("Gps Tracker", "Gps Tracker", 'manage_options', "gps-tracker", array(&$this, "settings_page"), plugins_url('images/satellite.png', __FILE__), 100);
        }

        public function settings_page() {
            wp_enqueue_style('leaflet_admin_stylesheet');
            include 'admin/admin.php';
        }

        public function map_shortcode() {
            wp_enqueue_script('google_maps', '//maps.google.com/maps/api/js?v=3&sensor=false&libraries=adsense', false);     
            
            wp_enqueue_script('leaflet_js', plugins_url('javascript/leaflet-0.7.3/leaflet.js', __FILE__), false);   
            wp_enqueue_style('leaflet_styles', plugins_url('javascript/leaflet-0.7.3/leaflet.css', __FILE__), false);
            
            // these quit working, the https was no longer accessible
            // wp_enqueue_script('leaflet_js', '//cdn.leafletjs.com/leaflet-0.7.3/leaflet.js', false);    
            // wp_enqueue_style('leaflet_styles', '//cdn.leafletjs.com/leaflet-0.7.3/leaflet.css', false);
            wp_enqueue_script('google_layer', plugins_url('javascript/leaflet-plugins/google.js', __FILE__), false);
            wp_enqueue_script('bing_layer', plugins_url('javascript/leaflet-plugins/bing.js', __FILE__), false);
            
            $html = '<div id="map" style="height:400px;width:600px;border:1px solid black;"></div>
            <script>
                var map;            
                jQuery(function($){
                    map = new L.map("map").setView([47.61,-122.33], 12);

                    var openStreetMapsLayer = new L.TileLayer("//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                        attribution: "&copy;2014 <a href=\'http://openstreetmap.org\'>OpenStreetMap</a> contributors",
                        maxZoom: 18
                    }).addTo(map);

                    // this fixes the zoom buttons from freezing
                    // https://github.com/shramov/leaflet-plugins/issues/62
                    L.polyline([[0, 0], ]).addTo(map);

                    // need to get your own bing maps key, http://www.microsoft.com/maps/create-a-bing-maps-key.aspx
                    var bingMapsLayer = new L.BingLayer("AnH1IKGCBwAiBWfYAHMtIfIhMVybHFx2GxsReNP5W0z6P8kRa67_QwhM4PglI9yL");
                    var googleMapsLayer = new L.Google("ROADMAP");

                    map.addControl(new L.Control.Layers({
                        "Bing Maps":bingMapsLayer,
                        "Google Maps":googleMapsLayer,
                        "OpenStreetMaps":openStreetMapsLayer
                    }));
                    
                });
            </script>';

            return $html;
        }
    } // end class

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('Gps_Tracker', 'add_action_links'));
    
    register_activation_hook( __FILE__, array('Gps_Tracker', 'activate'));
    
    // need to create an uninstall.php file and drop the table there
    // http://codex.wordpress.org/Function_Reference/register_uninstall_hook
    register_deactivation_hook( __FILE__, array('Gps_Tracker', 'deactivate') );
    
    $gps_tracker_plugin = new Gps_Tracker();
}
?>

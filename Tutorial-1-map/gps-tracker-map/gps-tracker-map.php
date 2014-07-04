<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Map
Plugin URI: https://www.websmithing.com/gps-tracker
Description: This plugin creates the Gps Tracker map using leaflet.
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

if (!class_exists('Gps_Tracker_Map')) {
    class Gps_Tracker_Map {
            
        public function __construct() {
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            register_uninstall_hook(__FILE__, array($this, 'uninstall'));
        
            // to test this plugin, add this shortcode to any page or post: [gps-tracker-map]  
            add_shortcode('gps-tracker-map', array($this,'map_shortcode'));
            
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('Gps_Tracker_Map', 'add_action_links'));
        }

        public static function activate() {
            // placeholder for future plugins
            
            // exit(var_dump($_GET));
        }
        
        public static function deactivate() {
            // placeholder for future plugins
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
    
    $gps_tracker_plugin = new Gps_Tracker_Map();
}
?>

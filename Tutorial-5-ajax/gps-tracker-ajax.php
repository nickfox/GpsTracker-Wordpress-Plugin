<?php
defined('ABSPATH') or exit;
/*
Plugin Name: Gps Tracker Ajax
Plugin URI: https://www.websmithing.com/gps-tracker
Description: This plugin creates the update the Gps Tracker Map with ajax calls to the database.
Version: 1.0.0
Author: Nick Fox
Author URI: https://www.websmithing.com/hire-me
License: GPL2
*/

if (!class_exists('Gps_Tracker_Ajax')) {
    class Gps_Tracker_Ajax {
            
        function __construct() {
            register_activation_hook(__FILE__, array(get_class($this), 'activate'));
            register_deactivation_hook(__FILE__, array(get_class($this), 'deactivate'));
            register_uninstall_hook(__FILE__, array(get_class($this), 'uninstall'));
        
            // to test this plugin, add this shortcode to any page or post: [gps_tracker_ajax]  
            add_shortcode('gps_tracker_ajax', array($this,'map_shortcode'));
            
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
            
            add_action('wp_ajax_get_routes', array($this, 'get_gps_routes'));
            add_action('wp_ajax_nopriv_get_routes', array($this, 'get_gps_routes')); 
            add_action('wp_ajax_get_route_for_map', array($this, 'get_gps_route_for_map'));
            add_action('wp_ajax_nopriv_get_route_for_map', array($this, 'get_gps_route_for_map'));
            add_action('wp_ajax_delete_route', array($this, 'delete_gps_route'));
            add_action('wp_ajax_nopriv_delete_route', array($this, 'delete_gps_route'));
        }

        static function activate() {
            // placeholder for future plugins
            
            // exit(var_dump($_GET));
        }
        
        static function deactivate() {
            // placeholder for future plugins
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
        }
        
        function add_action_links($links) {
             $links[] = '<a href="' . admin_url('admin.php?page=gps-tracker') . '">Settings</a>';
             return $links;
        }
        
        function admin_init() {
            wp_register_style('leaflet_admin_stylesheet', plugins_url('styles/style.css', __FILE__));
        }

        function admin_menu() {
            add_menu_page("Gps Tracker", "Gps Tracker", 'manage_options', "gps-tracker", array($this, "settings_page"), plugins_url('images/satellite.png', __FILE__), 100);
        }

        function settings_page() {
            wp_enqueue_style('leaflet_admin_stylesheet');
            include 'admin/admin.php';
        }
        
        function get_gps_routes() {
            if (!wp_verify_nonce($_POST['get_routes_nonce'], 'get-routes-nonce')) {
                die('security check');
            } else {          
                header('Content-Type: application/json');
            
                global $wpdb;
                $procedure_name =  $wpdb->prefix . "get_routes";
                $gps_routes = $wpdb->get_results("CALL {$procedure_name};"); 
           
                if ($wpdb->num_rows == 0) {
                    echo '0';
                    die();
                }
           
                $json = '{ "routes": [';
               
                foreach ($gps_routes as $route) {
                    $json .= $route->json;
                    $json .= ','; 
                }
            
                $json = rtrim($json, ",");
                $json .= '] }';   
               
                echo $json;
                die();
            }
        }
        
        function get_gps_route_for_map() {
            if (!wp_verify_nonce($_POST['get_route_for_map_nonce'], 'get-route-for-map-nonce')) {
                die('security check');
            } else {
                header('Content-Type: application/json');

                global $wpdb;
                $session_id = $_POST['session_id'];
                $procedure_name =  $wpdb->prefix . "get_route_for_map";
                $gps_locations = $wpdb->get_results($wpdb->prepare(
                    "CALL {$procedure_name}(%s);", 
                    array(
                        $session_id
                    )
                )); 
           
                if ($wpdb->num_rows == 0) {
                    echo '0';
                    die();
                }
           
                $json = '{ "locations": [';
               
                foreach ($gps_locations as $location) {
                    $json .= $location->json;
                    $json .= ','; 
                }
            
                $json = rtrim($json, ",");
                $json .= '] }';   
               
                echo $json;
                die();
            }            
        }
        
        function delete_gps_route() {
            if (!wp_verify_nonce($_POST['delete_route_nonce'], 'delete-route-nonce')) {
                die('security check');
            } else {          
                global $wpdb;
                $session_id = $_POST['session_id'];
                $table_name = $wpdb->prefix . 'gps_locations';
                
                // $wpdb->delete($table_name, array('session_id' => '8BA21D90-3F90-407F-BAAE-800B04B1F5EC'));
                $wpdb->delete($table_name, array('session_id' => $session_id));
                
                die();
            }
        }

        function map_shortcode() {
            wp_enqueue_script('google_maps', '//maps.google.com/maps/api/js?v=3&sensor=false&libraries=adsense', false);         
            wp_enqueue_script('leaflet_js', plugins_url('javascript/leaflet-0.7.3/leaflet.js', __FILE__), false);   
            wp_enqueue_style('leaflet_styles', plugins_url('javascript/leaflet-0.7.3/leaflet.css', __FILE__), false);
            wp_enqueue_script('google_layer', plugins_url('javascript/leaflet-plugins/google.js', __FILE__), false);
            wp_enqueue_script('bing_layer', plugins_url('javascript/leaflet-plugins/bing.js', __FILE__), false);
            wp_enqueue_script('map_js', plugins_url('javascript/map.js', __FILE__), array('jquery'));   
            wp_enqueue_style('gps_tracker_styles', plugins_url('styles/styles.css', __FILE__), false);
            
            wp_localize_script('map_js', 'map_js_vars', array(
                'plugin_url' => plugin_dir_url(__FILE__),
                'ajax_url' => admin_url('admin-ajax.php'),
                'get_routes_nonce' => wp_create_nonce('get-routes-nonce'),
                'get_route_for_map_nonce' => wp_create_nonce('get-route-for-map-nonce'),
                'delete_route_nonce' => wp_create_nonce('delete-route-nonce')
            	)
            );        
            
            $html = '
            <div id="gpstracker">
                <div id="messages">Gps Tracker</div>
                <div id="map" ></div>

                <select id="selectRoute" tabindex="1"></select>

                <select id="selectRefresh" tabindex="2">
                    <option value ="0">Auto Refresh - Off</option>
                    <option value ="60">Auto Refresh - 1 minute</option>
                    <option value ="120">Auto Refresh - 2 minutes</option>
                    <option value ="180">Auto Refresh - 3 minutes</option>
                    <option value ="300">Auto Refresh - 5 minutes</option>
                    <option value ="600">Auto Refresh - 10 minutes</option>
                </select>

                <select id="selectZoomLevel" tabindex="3">
                    <option value ="1">Zoom Level - 1</option>
                    <option value ="2">Zoom Level - 2</option>
                    <option value ="3">Zoom Level - 3</option>
                    <option value ="4">Zoom Level - 4</option>
                    <option value ="5">Zoom Level - 5</option>
                    <option value ="6">Zoom Level - 6</option>
                    <option value ="7">Zoom Level - 7</option>
                    <option value ="8">Zoom Level - 8</option>
                    <option value ="9">Zoom Level - 9</option>
                    <option value ="10">Zoom Level - 10</option>
                    <option value ="11">Zoom Level - 11</option>
                    <option value ="12">Zoom Level - 12</option>
                    <option value ="13">Zoom Level - 13</option>
                    <option value ="14">Zoom Level - 14</option>
                    <option value ="15">Zoom Level - 15</option>
                    <option value ="16">Zoom Level - 16</option>
                    <option value ="17">Zoom Level - 17</option>
                </select>

                <input type="button" id="delete" value="Delete" tabindex="4">
                <input type="button" id="refresh" value="Refresh" tabindex="5">
            </div>';

            return $html;
        }
    } // end class
    
    $gps_tracker_plugin = new Gps_Tracker_Ajax();
}
?>

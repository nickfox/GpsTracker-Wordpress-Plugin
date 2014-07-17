jQuery(document).ready(function($) {
    var pluginUrl = map_js_vars.plugin_url;
    var selectRoute = document.getElementById("selectRoute");
    var refreshSelect = document.getElementById("selectRefresh");
    var messages = document.getElementById("messages");
    var zoomLevelSelect = document.getElementById("selectZoomLevel");
    var map = document.getElementById('map');;
    var intervalID = 0;
    var newInterval = 0;
    var currentInterval = 0;
    var zoomLevel = 12; 
    
    zoomLevelSelect.selectedIndex = 11;
    refreshSelect.selectedIndex = 0;
    showWaitImage("Loading routes...");

    // when page first loads 
    loadRoutesIntoDropdownBox();
  
    $('#selectRoute').on('change', function(){ 
        getRouteForMap();
    }); 

    $('#selectRefresh').on('change', function(){ 
        autoRefresh();
    }); 

    $('#selectZoomLevel').on('change', function(){ 
        changeZoomLevel();
    }); 

    $("#delete").click(function() {
        deleteRoute();
    }); 

    $("#refresh").click(function() {
        getRouteForMap();
    });    

    function loadRoutesIntoDropdownBox() {
        $.post(
            map_js_vars.ajax_url,
            {
                'action': 'get_routes',
                'get_routes_nonce': map_js_vars.get_routes_nonce
            },
            function(response) {
                loadRoutes(response);
            }
        );    
    }

    function getRouteForMap() {
        if (hasMap()) {
            showWaitImage('Getting map...');

            $.post(
                map_js_vars.ajax_url,
                {
                    'action': 'get_route_for_map',
                    'session_id': $("#selectRoute").val(),
                    'get_route_for_map_nonce': map_js_vars.get_route_for_map_nonce
                },
                function(response) {
                    loadGPSLocations(response);
                }
            );  
        } else {
            alert("Please select a route before trying to refresh map.");
        }    
    }

    function loadRoutes(json) {
        //alert(JSON.stringify(json));
        if (json.length == 0 || json == '0') {
            showMessage('There are no routes available to view.');
            map.innerHTML = '';
        }
        else {
            // create the first option of the dropdown box
            var option = document.createElement('option');
            option.setAttribute('value', '0');
            option.innerHTML = 'Select Route...';
            selectRoute.appendChild(option);

            // iterate through the routes and load them into the dropdwon box.
            $(json.routes).each(function(key, value){
                var option = document.createElement('option');
                option.setAttribute('value', $(this).attr('session_id'));
                option.innerHTML = $(this).attr('user_name') + "  " + $(this).attr('times');
                selectRoute.appendChild(option);
            });

            // need to reset this for firefox
            selectRoute.selectedIndex = 0;

            hideWaitImage();
            showMessage('<span style="color:#F00;">Please select a route below.</span>');
        }
    }

    // check to see if we have a map loaded, don't want to autorefresh or delete without it
    function hasMap() {
        if (selectRoute.selectedIndex == 0) { // means no map
            return false;
        }
        else {
            return true;
        }
    }

    function loadGPSLocations(json) {
        if (json.length == 0 || json == '0') {
            showMessage('There is no tracking data to view.');
            map.innerHTML = '';
        }
        else {
            hideWaitImage();

            // make sure we only create map object once
            if (map.id == 'map') {
                // use leaflet (http://leafletjs.com/) to create our map and map layers
                map = new L.map('map');

                var openStreetMapsURL = ('https:' == document.location.protocol ? 'https://' : 'http://') +
                 '{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
                var openStreetMapsLayer = new L.TileLayer(openStreetMapsURL,
                {attribution:'&copy;2014 <a href="http://openstreetmap.org">OpenStreetMap</a> contributors'});

                // need to get your own bing maps key, http://www.microsoft.com/maps/create-a-bing-maps-key.aspx
                var bingMapsLayer = new L.BingLayer("AnH1IKGCBwAiBWfYAHMtIfIhMVybHFx2GxsReNP5W0z6P8kRa67_QwhM4PglI9yL");
                var googleMapsLayer = new L.Google('ROADMAP');
            
                // this fixes the zoom buttons from freezing
                // https://github.com/shramov/leaflet-plugins/issues/62
                L.polyline([[0, 0], ]).addTo(map);

                // this sets which map layer will first be displayed, go ahead and change it to bingMapsLayer or openStreetMapsLayer to see
                map.addLayer(googleMapsLayer);

                // this is the switcher control to switch between map types (upper right hand corner of map)
                map.addControl(new L.Control.Layers({
                    'Bing Maps':bingMapsLayer,
                    'Google Maps':googleMapsLayer,
                    'OpenStreetMaps':openStreetMapsLayer
                }, {}));
            }

                var finalLocation = false;
                var counter = 0;

                // iterate through the locations and create map markers for each location
                $(json.locations).each(function(key, value){
                    counter++;

                    // want to set the map center on the last location
                    if (counter == $(json.locations).length) {
                        map.setView(new L.LatLng($(this).attr('latitude'),$(this).attr('longitude')), zoomLevel);
                        finalLocation = true;
                    }

                    var marker = createMarker(
                        $(this).attr('latitude'),
                        $(this).attr('longitude'),
                        $(this).attr('speed'),
                        $(this).attr('direction'),
                        $(this).attr('distance'),
                        $(this).attr('location_method'),
                        $(this).attr('gps_time'),
                        $(this).attr('user_name'),
                        $(this).attr('session_id'),
                        $(this).attr('accuracy'),
                        $(this).attr('extra_info'),
                        map, finalLocation);
                });
            }

            // display route name above map
            showMessage($("#selectRoute option:selected").text());
    }

    function createMarker(latitude, longitude, speed, direction, distance, locationMethod, gpsTime,
                          userName, sessionID, accuracy, extraInfo, map, finalLocation) {
        var iconUrl;

        if (finalLocation) {
            iconUrl = pluginUrl + 'images/coolred_small.png';
        } else {
            iconUrl = pluginUrl + 'images/coolblue_small.png';
        }

        var markerIcon = new L.Icon({
                iconUrl:      iconUrl,
                shadowUrl:    pluginUrl + 'images/coolshadow_small.png',
                iconSize:     [12, 20],
                shadowSize:   [22, 20],
                iconAnchor:   [6, 20],
                shadowAnchor: [6, 20],
                popupAnchor:  [-3, -76]
        });

        var lastMarker = "</td></tr>";

        // when a user clicks on last marker, let them know it's final one
        if (finalLocation) {
            lastMarker = "</td></tr><tr><td colspan=2  style=\"text-align:center\"><b>Final location</b></td></tr>";
        }

        // convert from meters to feet
        accuracy = parseInt(accuracy * 3.28);

        var popupWindowText = "<table id=\"gps_info_table\" cellspacing=\"0\" cellpadding=\"0\">"
            + "<tr><td>&nbsp;</td><td>&nbsp;</td><td rowspan=2 align=right>"
            + "<img src=" + pluginUrl + "images/" + getCompassImage(direction) + ".jpg alt= />" + lastMarker
            + "<tr><td style=\"width:75px\">Speed:</td><td style=\"width:100px\">" + speed +  " mph</td></tr>"
            + "<tr><td>Distance:</td><td>" + distance +  " mi</td><td>&nbsp;</td></tr>"
            + "<tr><td>Time:</td><td>" + gpsTime +  "</td></tr>"
            + "<tr><td>User Name:</td><td>" + userName + "</td><td>&nbsp;</td></tr>"
            + "<tr><td>Accuracy:</td><td>" + accuracy + " ft</td><td>&nbsp;</td></tr>"
            + "<tr><td>Extra Info:</td><td>" + extraInfo + "</td><td>&nbsp;</td></tr></table>";

        L.marker(new L.LatLng(latitude, longitude), {icon: markerIcon}).bindPopup(popupWindowText).addTo(map);
    }

    function getCompassImage(azimuth) {
        if ((azimuth >= 337 && azimuth <= 360) || (azimuth >= 0 && azimuth < 23))
                return "compassN";
        if (azimuth >= 23 && azimuth < 68)
                return "compassNE";
        if (azimuth >= 68 && azimuth < 113)
                return "compassE";
        if (azimuth >= 113 && azimuth < 158)
                return "compassSE";
        if (azimuth >= 158 && azimuth < 203)
                return "compassS";
        if (azimuth >= 203 && azimuth < 248)
                return "compassSW";
        if (azimuth >= 248 && azimuth < 293)
                return "compassW";
        if (azimuth >= 293 && azimuth < 337)
                return "compassNW";

        return "";
    }

    function deleteRoute() {
        if (hasMap()) {
		
    		// comment out these two lines to get delete working
    		// var answer = confirm("Disabled here on test website, this works fine.");
    		// return false;
		
            var answer = confirm("This will permanently delete this route\n from the database. Do you want to delete?");
            if (answer){
                showWaitImage('Deleting route...');
 
                $.post(
                    map_js_vars.ajax_url,
                    {
                        'action': 'delete_route',
                        'session_id': $("#selectRoute").val(),
                        'delete_route_nonce': map_js_vars.delete_route_nonce
                    },
                    function(response) {
                        $('#map').html('');
                        selectRoute.length = 0;
                        
                        loadRoutesIntoDropdownBox();
                    }
                ); 
            }
            else {
                return false;
            }
        }
        else {
            alert("Please select a route before trying to delete.");
        }
    }

    // auto refresh the map. there are 3 transitions (shown below). transitions happen when a user
    // selects an option in the auto refresh dropdown box. an interval is an amount of time in between
    // refreshes of the map. for instance, auto refresh once a minute. in the method below, the 3 numbers
    // in the code show where the 3 transitions are handled. setInterval turns on a timer that calls
    // the getRouteForMap() method every so many seconds based on the value of newInterval.
    // clearInterval turns off the timer. if newInterval is 5, then the value passed to setInterval is
    // 5000 milliseconds or 5 seconds.
    function autoRefresh() {
        /*
            1) going from off to any interval
            2) going from any interval to off
            3) going from one interval to another
        */

        if (hasMap()) {
            newInterval = $("#refreshSelect").val();

            if (currentInterval > 0) { // currently running at an interval

                if (newInterval > 0) { // moving to another interval (3)
                    clearInterval(intervalID);
                    intervalID = setInterval("getRouteForMap();", newInterval * 1000);
                    currentInterval = newInterval;
                }
                else { // we are turning off (2)
                    clearInterval(intervalID);
                    newInterval = 0;
                    currentInterval = 0;
                }
            }
            else { // off and going to an interval (1)
                intervalID = setInterval("getRouteForMap();", newInterval * 1000);
                currentInterval = newInterval;
            }

            // show what auto refresh action was taken and after 5 seconds, display the route name again
            showMessage($("#refreshSelect option:selected").text());
            setTimeout('showRouteName();', 5000);
        }
        else {
            alert("Please select a route before trying to refresh map.");
            refreshSelect.selectedIndex = 0;
        }
    }

    function changeZoomLevel() {
        if (hasMap()) {
            zoomLevel = zoomLevelSelect.selectedIndex + 1;

            getRouteForMap();

            // show what zoom level action was taken and after 5 seconds, display the route name again
            showMessage($("#zoomLevelSelect option:selected").text());
            setTimeout('showRouteName();', 5000);
        }
        else {
            alert("Please select a route before selecting zoom level.");
            zoomLevelSelect.selectedIndex = zoomLevel - 1;
        }
    }

    function showMessage(message) {
        $('#messages').html('Gps Tracker: <strong>' + message + '</strong>');
    }

    function showRouteName() {
        showMessage($("#selectRoute option:selected").text());
    }

    function showWaitImage(theMessage) {
        $('#map').html('<img id="waitImage" src="' + pluginUrl + 'images/ajax-loader.gif">');
        showMessage(theMessage);
    }

    function hideWaitImage() {
        $('#map').html('');
        $('#messages').html('Gps Tracker');
    }

});

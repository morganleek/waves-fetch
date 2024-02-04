<?php 
	// https://api.willyweather.com.au/v2/API_KEY/locations/LOCATION_ID/weather.json
	// Headers
	// Content-Type: application/json
	// x-payload: {"forecasts": ["tides"], "days": 2, "startDate": "2024-02-01"}

  function waf_fetch_tides() {
    global $wpdb;

    // Get Willy Weather API Key
    if( $waf_willy_weather = get_option('waf_willy_weather') ) {
      if( !empty( $waf_willy_weather['api_key'] ) ) {
        // Get all buoys with location set
        $buoys = $wpdb->get_results( "SELECT `id`, `willy_weather_location_id` 
          FROM `wp_waf_buoys`  
          WHERE `willy_weather_location_id` > 0");

        foreach( $buoys as $buoy ) {
          // Fetch
          $curl = curl_init();
          $start_date = date('Y-m-d');

          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.willyweather.com.au/v2/{$waf_willy_weather['api_key']}/locations/{$buoy->willy_weather_location_id}/weather.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
              'Content-Type: application/json',
              'x-payload: {"forecasts": ["tides"], "days": 7, "startDate": "' . $start_date . '"}'
            ),
          ));

          $response = curl_exec($curl);

          curl_close($curl);

          // Process the tide data
          $tide_data = json_decode( $response );
          // print_r( $tide_data );

          $time_zone = $tide_data->location->timeZone; // Use to adjust times to GMT
          $tides = [];
          foreach($tide_data->forecasts->tides->days as $day) {
            foreach($day->entries as $entry) {
              // $time = strtotime($entry->dateTime);
              $time = new DateTime($entry->dateTime, new DateTimeZone($time_zone));
              $tides[$time->format('U')] = $entry->height;
            }
          }

          // Delete existing
          $timestamps = implode(",", array_keys( $tides ));
          $wpdb->query("DELETE FROM {$wpdb->prefix}waf_wave_tides WHERE `buoy_id` = $buoy->id AND `timestamp` IN ($timestamps)");
          
          foreach($tides as $timestamp => $height) {
            $wpdb->insert(
              $wpdb->prefix . "waf_wave_tides",
              array(
                'buoy_id' => $buoy->id,
                'height' => $height,
                'timestamp' => $timestamp
              ),
              array(
                '%d', '%f', '%s'
              )
            );
          }

          print 1;
        }
      }
    }
  }
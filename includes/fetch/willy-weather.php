<?php 
	// https://api.willyweather.com.au/v2/ZWI2MzBlMmE3MTk3ZDE1NjU1M2FkZT/locations/10520/weather.json
	// Headers
	// Content-Type: application/json
	// x-payload: {"forecasts": ["tides"], "days": 2, "startDate": "2024-02-01"}

	// Example

	// {
  //   "location": {
  //     "id": 10520,
  //     "name": "Devonport",
  //     "region": "North Western",
  //     "state": "TAS",
  //     "postcode": "7310",
  //     "timeZone": "Australia/Hobart",
  //     "lat": -41.1639,
  //     "lng": 146.3504,
  //     "typeId": 12
  //   },
  //   "forecasts": {
  //     "tides": {
  //       "days": [
  //         {
  //           "dateTime": "2024-02-01 00:00:00",
  //           "entries": [
  //             {
  //               "dateTime": "2024-02-01 04:59:00",
  //               "height": 3.12,
  //               "type": "high"
  //             },
  //             {
  //               "dateTime": "2024-02-01 11:19:00",
  //               "height": 0.93,
  //               "type": "low"
  //             },
  //             {
  //               "dateTime": "2024-02-01 17:16:00",
  //               "height": 2.97,
  //               "type": "high"
  //             },
  //             {
  //               "dateTime": "2024-02-01 23:29:00",
  //               "height": 0.91,
  //               "type": "low"
  //             }
  //           ]
  //         },
  //         {
  //           "dateTime": "2024-02-02 00:00:00",
  //           "entries": [
  //             {
  //               "dateTime": "2024-02-02 05:32:00",
  //               "height": 3.14,
  //               "type": "high"
  //             },
  //             {
  //               "dateTime": "2024-02-02 11:56:00",
  //               "height": 0.86,
  //               "type": "low"
  //             },
  //             {
  //               "dateTime": "2024-02-02 17:58:00",
  //               "height": 2.98,
  //               "type": "high"
  //             }
  //           ]
  //         }
  //       ],
  //       "units": {
  //         "height": "m"
  //       },
  //       "issueDateTime": "2023-11-05 11:06:30",
  //       "carousel": {
  //         "size": 2922,
  //         "start": 2588
  //       }
  //     }
  //   }
  // }

	// INSERT INTO `wp_waf_wave_tides`
	// ( buoy_id, height, timestamp )
	// VALUES
	// (31325, 3.12, 1706763579), (31325, 0.93, 1706786340), (31325, 2.97, 1706807760), (31325, 0.91, 1706830140),
	// (31325, 3.14, 1706851920), (31325, 0.86, 1706874960), (31325, 2.98, 1706896680)
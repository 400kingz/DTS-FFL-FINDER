<?php
ini_set('memory_limit', '1024M');
define('FFL_GEOCODE_CACHE', __DIR__ . '/geocode_cache.json');

// Load FFL dealers from CSV
function load_ffl_dealers() {
    static $dealers = null;
    if ($dealers !== null) {
        return $dealers;
    }

    $csv_file = __DIR__ . '/dealers.csv';
    $dealers = array();

    if (file_exists($csv_file)) {
        $csv_data = file_get_contents($csv_file);
        $lines = explode(PHP_EOL, $csv_data);
        $header = str_getcsv(array_shift($lines));
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) == count($header)) {
                $dealers[] = array_combine($header, $data);
            }
        }
    } else {
        error_log('Error: CSV file not found or unable to open.');
    }

    return $dealers;
}

// Geocode ZIP code
function geocode_zipcode($zipcode) {
    $api_key = 'AIzaSyAho6VTU5slTT2E3Ur-deTtaS36Frct9FE'; // Replace with your Google Maps API key
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$zipcode}&key={$api_key}";

    static $cache = null;
    if ($cache === null) {
        if (file_exists(FFL_GEOCODE_CACHE)) {
            $cache = json_decode(file_get_contents(FFL_GEOCODE_CACHE), true) ?: [];
        } else {
            $cache = [];
        }
    }

    if (isset($cache[$zipcode])) {
        return $cache[$zipcode];
    }

    if (!isset($cache[$zipcode])) {
        $response = file_get_contents($url);
        if ($response === FALSE) {
            return false;
        }

        $data = json_decode($response);

        if ($data->status === 'OK') {
            $location = $data->results[0]->geometry->location;
            $geocode = array('latitude' => $location->lat, 'longitude' => $location->lng);
            $cache[$zipcode] = $geocode;
            file_put_contents(FFL_GEOCODE_CACHE, json_encode($cache, JSON_PRETTY_PRINT));
        } else {
            return false;
        }
    }

    return $cache[$zipcode];
}

// Add FFL dealers to the map
function add_ffl_dealers_to_map() {
    try {
        $dealers = load_ffl_dealers();
    } catch (Exception $e) {
        error_log('Error loading FFL dealers: ' . $e->getMessage());
        return;
    }
    $formatted_dealers = array();

    foreach ($dealers as $dealer) {
        $geocode = geocode_zipcode($dealer['PREMISE_ZIP_CODE']);
        if ($geocode) {
            $formatted_dealers[] = array(
                'name' => $dealer['BUSINESS_NAME'],
                'license_name' => $dealer['LICENSE_NAME'],
                'address' => $dealer['PREMISE_STREET'] . ', ' . $dealer['PREMISE_CITY'] . ', ' . $dealer['PREMISE_STATE'] . ' ' . $dealer['PREMISE_ZIP_CODE'],
                'latitude' => $geocode['latitude'],
                'longitude' => $geocode['longitude'],
                'city' => $dealer['PREMISE_CITY'],
                'state' => $dealer['PREMISE_STATE'],
                'zip' => $dealer['PREMISE_ZIP_CODE'],
                'phone' => $dealer['VOICE_PHONE']
            );
        }
    }

    return $formatted_dealers;
}

$dealers = add_ffl_dealers_to_map();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFL Dealer Locator</title>
    <link rel="stylesheet" href="assets/css/ffl-selector.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAho6VTU5slTT2E3Ur-deTtaS36Frct9FE&callback=initMap" async defer></script>
    <script>
        var fflDealers = <?php echo json_encode($dealers); ?>;
    </script>
    <script src="assets/js/ffl-selector.js"></script>
</head>
<body>
    <div id="ffl_dealer_field">
        <h2>Select FFL Dealer</h2>
        <label for="ffl_zipcode">Enter ZIP Code</label>
        <input type="text" id="ffl_zipcode" name="ffl_zipcode" placeholder="ZIP Code" pattern="\d{5}" title="Enter a valid ZIP Code" required />
        <label for="ffl_radius">Select Radius</label>
        <select id="ffl_radius" name="ffl_radius">
            <option value="10">10 miles</option>
            <option value="25">25 miles</option>
            <option value="50">50 miles</option>
        </select>
        <button type="button" id="search_ffl_dealers">Search</button>
        <div id="ffl-dealer-map"></div>
    </div>
</body>
</html>

<?php
/*
Plugin Name: DTS FFL LOCATOR FINAL
Description: A plugin that integrates with WooCommerce to help customers select an FFL dealer for firearm product shipments. It features a Google Maps interface, dealer management, FFL-required product marking, and compliance with shipping regulations.
Version: 5.0
Author: Zain Omran
*/

ini_set('memory_limit', '1024M');
define('FFL_GEOCODE_CACHE', plugin_dir_path(__FILE__) . 'geocode_cache.json');

// Enqueue Google Maps API and custom scripts
function enqueue_ffl_selector_scripts() {
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAho6VTU5slTT2E3Ur-deTtaS36Frct9FE', array(), null, true);
    wp_enqueue_script('ffl-selector', plugin_dir_url(__FILE__) . 'assets/js/ffl-selector.js', array('jquery', 'google-maps'), null, true);
    wp_enqueue_style('ffl-selector-style', plugin_dir_url(__FILE__) . 'assets/css/ffl-selector.css');
}
add_action('wp_enqueue_scripts', 'enqueue_ffl_selector_scripts');

// Add FFL dealer selection field to checkout
function add_ffl_dealer_field($checkout) {
    echo '<div id="ffl_dealer_field"><h2>' . __('Select FFL Dealer') . '</h2>';
    echo '<label for="ffl_zipcode">' . __('Enter ZIP Code') . '</label>';
    echo '<input type="text" id="ffl_zipcode" name="ffl_zipcode" placeholder="ZIP Code" pattern="\d{5}" title="Enter a valid ZIP Code" required />';
    echo '<label for="ffl_radius">' . __('Select Radius') . '</label>';
    echo '<select id="ffl_radius" name="ffl_radius">';
    echo '<option value="10">10 miles</option>';
    echo '<option value="25">25 miles</option>';
    echo '<option value="50">50 miles</option>';
    echo '</select>';
    echo '<button type="button" id="search_ffl_dealers">' . __('Search') . '</button>';
    echo '<div id="ffl-dealer-map"></div>';
    $checkout->get_value('ffl_dealer');
    echo '</div>';
}
add_action('woocommerce_after_order_notes', 'add_ffl_dealer_field');

// Validate FFL dealer field
function validate_ffl_dealer_field() {
    if (empty($_POST['ffl_dealer']) || !preg_match('/^\d{5}$/', $_POST['ffl_zipcode'])) {
        wc_add_notice(__('Please select an FFL dealer.'), 'error');
    }
}
add_action('woocommerce_checkout_process', 'validate_ffl_dealer_field');

// Save FFL dealer field
function save_ffl_dealer_field($order_id) {
    if (!empty($_POST['ffl_dealer'])) {
        update_post_meta($order_id, '_ffl_dealer', sanitize_text_field($_POST['ffl_dealer']));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'save_ffl_dealer_field');

// Display FFL dealer field in order details
function display_ffl_dealer_field_in_order($order) {
    $ffl_dealer = get_post_meta($order->get_id(), '_ffl_dealer', true);
    if ($ffl_dealer) {
        echo '<p><strong>' . __('FFL Dealer') . ':</strong> ' . $ffl_dealer . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'display_ffl_dealer_field_in_order', 10, 1);
add_action('woocommerce_email_customer_details', 'display_ffl_dealer_field_in_order', 10, 1);

// Load FFL dealers from CSV
function load_ffl_dealers() {
    $csv_file = plugin_dir_path(__FILE__) . 'dealers.csv';
    $dealers = array();

    if (file_exists($csv_file) && ($handle = fopen($csv_file, 'r')) !== FALSE) {
        $header = fgetcsv($handle, 1000, ',');
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) == count($header)) {
                $dealers[] = array_combine($header, $data);
            }
        }
        fclose($handle);
    } else {
        error_log('Error: CSV file not found or unable to open.');
    }

    return $dealers;
}

// Geocode ZIP code
function geocode_zipcode($zipcode) {
    $api_key = 'AIzaSyAho6VTU5slTT2E3Ur-deTtaS36Frct9FE'; // Replace with your Google Maps API key
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$zipcode}&key={$api_key}";

    $cache = [];
    if (file_exists(FFL_GEOCODE_CACHE)) {
        $cache = json_decode(file_get_contents(FFL_GEOCODE_CACHE), true) ?: [];
    }
    if (isset($cache[$zipcode])) {
        return $cache[$zipcode];
    }

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data->status === 'OK') {
        $location = $data->results[0]->geometry->location;
        $geocode = array('latitude' => $location->lat, 'longitude' => $location->lng);
        $cache[$zipcode] = $geocode;
        file_put_contents(FFL_GEOCODE_CACHE, json_encode($cache, JSON_PRETTY_PRINT));
        return $geocode;
    }

    return false;
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

    wp_localize_script('ffl-selector', 'fflDealers', $formatted_dealers);
}
add_action('wp_enqueue_scripts', 'add_ffl_dealers_to_map');
?>

jQuery(document).ready(function($) {
    // Initialize Google Map
    function initMap(dealers) {
        var map = new google.maps.Map(document.getElementById('ffl-dealer-map'), {
            zoom: 4,
            center: {lat: 39.8283, lng: -98.5795} // Center of the USA
        });

        // Add markers for each FFL dealer
        dealers.forEach(function(dealer) {
            var marker = new google.maps.Marker({
                position: {lat: parseFloat(dealer.latitude), lng: parseFloat(dealer.longitude)},
                map: map,
                title: dealer.name
            });

            // Add info window for each marker
            var infoWindow = new google.maps.InfoWindow({
                content: '<div><strong>' + dealer.name + '</strong><br>' +
                         'License Name: ' + dealer.license_name + '<br>' +
                         'Address: ' + dealer.address + '<br>' +
                         dealer.city + ', ' + dealer.state + ' ' + dealer.zip + '<br>' +
                         'Phone: ' + dealer.phone + '</div>'
            });

            marker.addListener('click', function() {
                infoWindow.open(map, marker);
                $('#ffl_dealer').val(dealer.name + ', ' + dealer.address + ', ' + dealer.city + ', ' + dealer.state + ' ' + dealer.zip);
            });
        });
    }

    // Function to search dealers based on ZIP code and radius
    function searchDealers(zipcode, radius) {
        // Filter dealers based on ZIP code and radius
        var filteredDealers = fflDealers.filter(function(dealer) {
            // Implement your filtering logic here
            // For simplicity, this example does not include actual distance calculation
            return dealer.zip.startsWith(zipcode);
        });

        // Limit results to 10 dealers
        filteredDealers = filteredDealers.slice(0, 10);

        // Reinitialize the map with filtered dealers
        initMap(filteredDealers);
    }

    $('#search_ffl_dealers').on('click', function() {
        var zipcode = $('#ffl_zipcode').val();
        var radius = $('#ffl_radius').val();
        searchDealers(zipcode, radius);
    });

    // Load Google Map with all dealers initially
    if ($('#ffl-dealer-map').length) {
        google.maps.event.addDomListener(window, 'load', function() {
            initMap(fflDealers);
        });
    }
});
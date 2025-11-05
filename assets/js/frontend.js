/**
 * YARDLII Core - Location + Radius Slider with Tooltip + Locate Me
 * Uses Google Maps Autocomplete + Geocoding + Browser Geolocation
 * Formats FacetWP-compatible value: lat,lng,distance,address
 */
(function($) {
  $(document).ready(function() {
    const form = $('.yardlii-search-form');
    const input = document.getElementById('yardlii_location_input');
    const range = document.getElementById('yardlii_radius_range');
    const tooltip = document.getElementById('yardlii_radius_tooltip');
    const locateBtn = document.getElementById('yardlii_locate_me');
    let autocomplete, geocoder;

 // --- Live tooltip updater (mobile-safe) ---
if (range && tooltip) {
  const min = parseInt(range.min, 10);
  const max = parseInt(range.max, 10);
  let hideTimeout;

  const updateTooltip = () => {
    const val = parseInt(range.value, 10);
    tooltip.textContent = val + ' km';
    const percent = ((val - min) / (max - min)) * 100;
    tooltip.style.left = `calc(${percent}% + (${8 - percent * 0.16}px))`;

    // Show tooltip
    tooltip.classList.add('active');
    clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => tooltip.classList.remove('active'), 2000);
  };

  // Listen to both mouse + touch + pointer events (for iOS)
  range.addEventListener('input', updateTooltip);
  range.addEventListener('pointerdown', () => {
    tooltip.classList.add('active');
    clearTimeout(hideTimeout);
  });
  range.addEventListener('pointerup', () => {
    clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => tooltip.classList.remove('active'), 2000);
  });

  updateTooltip(); // initialize once
}




    // --- Initialize Google Places Autocomplete ---
    if (input && typeof google !== 'undefined' && google.maps && google.maps.places) {
      console.log('YARDLII: Initializing Google Places autocomplete...');
      autocomplete = new google.maps.places.Autocomplete(input, {
        types: ['(cities)'],
        fields: ['formatted_address', 'geometry']
      });
      geocoder = new google.maps.Geocoder();
    } else {
      console.warn('YARDLII: Google Maps Places API not loaded or input missing.');
    }

 // === 📍 Compact Compass Locate Feature ===
const locateIcon = document.getElementById('yardlii_locate_me');
if (locateIcon && typeof google !== 'undefined' && google.maps) {
  locateIcon.addEventListener('click', function() {
    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      return;
    }

    locateIcon.classList.add('locating');

    navigator.geolocation.getCurrentPosition(
      function(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const latlng = { lat: lat, lng: lng };
        const geocoder = new google.maps.Geocoder();

        geocoder.geocode({ location: latlng }, function(results, status) {
          if (status === 'OK' && results[0]) {
            const address = results[0].formatted_address;
            $('#yardlii_location_input').val(address);
            console.log('YARDLII: Current location →', address);
          } else {
            alert('Unable to determine your location. Please type manually.');
          }
          locateIcon.classList.remove('locating');
        });
      },
      function() {
        alert('Unable to access your location. Please allow permission.');
        locateIcon.classList.remove('locating');
      }
    );
  });
}


    // --- Handle form submission ---
    form.on('submit', function(e) {
      const address = input ? input.value.trim() : '';
      const distance = range ? range.value : 25;

      if (!address || typeof google === 'undefined' || !google.maps) {
        return true;
      }

      e.preventDefault(); // Wait for geocode before submit

      geocoder.geocode({ address: address }, function(results, status) {
        if (status === 'OK' && results[0]) {
          const loc = results[0].geometry.location;
          const lat = loc.lat();
          const lng = loc.lng();
          const encoded = `${lat},${lng},${distance},${address}`;

          // Remove any previous _location fields
          form.find('input[name="_location"]').remove();

          // Add hidden _location field
          $('<input>')
            .attr({
              type: 'hidden',
              name: '_location',
              value: encoded
            })
            .appendTo(form);

          console.log('YARDLII: Geocoded & submitting →', encoded);
          form.off('submit').submit();
        } else {
          console.warn('YARDLII: Geocode failed, submitting plain address.');
          form.off('submit').submit();
        }
      });
    });
  });
})(jQuery);

// === YARDLII: Auto-run FacetWP proximity facet when location prefilled (Listings page only) ===
(function($) {
  // Only run this on the Listings page
  if (window.location.pathname.includes('/listings')) {
    let firstLoad = true;

    $(document).on('facetwp-loaded', function() {
      if (firstLoad) {
        const $locFacet = $('[data-name="location"] input.facetwp-location');
        if ($locFacet.length && $locFacet.val().trim() !== '') {
          // 🧠 Show visual confirmation ONLY for logged-in admins
          if ($('#wpadminbar').length) {
            const msg = $('<div id="yardlii-filter-msg">Applying location filter…</div>').css({
              position: 'fixed',
              top: '80px',
              left: '50%',
              transform: 'translateX(-50%)',
              background: '#0b5eb8',
              color: '#fff',
              padding: '8px 16px',
              borderRadius: '6px',
              fontSize: '13px',
              zIndex: 9999,
              opacity: 0.95,
              boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
            });
            $('body').append(msg);
            setTimeout(() => msg.fadeOut(400, () => msg.remove()), 1500);
          }

          console.log('YARDLII: Auto-refreshing preloaded location facet...');
          FWP.refresh();
        }
        firstLoad = false;
      }
    });
  }
})(jQuery);



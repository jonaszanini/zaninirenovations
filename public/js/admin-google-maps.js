(function () {
  'use strict';

  var cfg = window.ZR_GOOGLE_MAPS || {};
  var loadPromise = null;

  function loadMapsApi() {
    if (!cfg.configured || !cfg.apiKey) {
      return Promise.reject(new Error('Google Maps is not configured.'));
    }
    if (window.google && window.google.maps && window.google.maps.places) {
      return Promise.resolve(window.google.maps);
    }
    if (loadPromise) {
      return loadPromise;
    }
    loadPromise = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key='
        + encodeURIComponent(cfg.apiKey)
        + '&libraries=places&callback=__zrGoogleMapsReady';
      script.async = true;
      script.defer = true;
      script.onerror = function () {
        reject(new Error('Failed to load Google Maps.'));
      };
      window.__zrGoogleMapsReady = function () {
        resolve(window.google.maps);
      };
      document.head.appendChild(script);
    });
    return loadPromise;
  }

  function fillFromPlace(input, place) {
    if (!place || !place.geometry || !place.geometry.location) {
      return;
    }
    var placeIdInput = document.getElementById(input.getAttribute('data-place-id-input'));
    var latInput = document.getElementById(input.getAttribute('data-lat-input'));
    var lngInput = document.getElementById(input.getAttribute('data-lng-input'));

    if (place.formatted_address) {
      input.value = place.formatted_address;
    }
    if (placeIdInput) {
      placeIdInput.value = place.place_id || '';
    }
    if (latInput) {
      latInput.value = place.geometry.location.lat();
    }
    if (lngInput) {
      lngInput.value = place.geometry.location.lng();
    }
  }

  function clearGeoFields(input) {
    var placeIdInput = document.getElementById(input.getAttribute('data-place-id-input'));
    var latInput = document.getElementById(input.getAttribute('data-lat-input'));
    var lngInput = document.getElementById(input.getAttribute('data-lng-input'));
    if (placeIdInput) placeIdInput.value = '';
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
  }

  function initAutocompleteInput(input) {
    if (input._zrPlacesWired) return;
    input._zrPlacesWired = true;

    loadMapsApi().then(function () {
      var autocomplete = new google.maps.places.Autocomplete(input, {
        fields: ['formatted_address', 'geometry', 'place_id'],
        componentRestrictions: { country: ['ca'] }
      });
      autocomplete.addListener('place_changed', function () {
        fillFromPlace(input, autocomplete.getPlace());
      });
    }).catch(function () {
      input.placeholder = 'Google Places unavailable — enter address manually';
    });

    input.addEventListener('input', function () {
      if (!input.value.trim()) {
        clearGeoFields(input);
      }
    });
  }

  function initAll() {
    document.querySelectorAll('[data-places-autocomplete]').forEach(initAutocompleteInput);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();

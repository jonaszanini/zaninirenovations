(function () {
  'use strict';

  var cfg = window.ZR_GOOGLE_MAPS || {};
  var loadPromise = null;
  var loadReject = null;
  var loadFailedMessage = null;

  function rejectPendingLoad(message) {
    loadFailedMessage = message;
    if (typeof loadReject === 'function') {
      var reject = loadReject;
      loadReject = null;
      loadPromise = null;
      reject(new Error(message));
    }
  }

  window.gm_authFailure = function () {
    rejectPendingLoad(
      'Google Maps rejected this API key. Check key restrictions and that Maps JavaScript API + Places API are enabled.'
    );
  };

  function loadMapsApi() {
    if (!cfg.configured || !cfg.apiKey) {
      return Promise.reject(new Error('GOOGLE_MAPS_API_KEY is not set on the server.'));
    }
    if (window.google && window.google.maps && window.google.maps.places) {
      return Promise.resolve(window.google.maps);
    }
    if (loadFailedMessage) {
      return Promise.reject(new Error(loadFailedMessage));
    }
    if (loadPromise) {
      return loadPromise;
    }

    loadPromise = new Promise(function (resolve, reject) {
      loadReject = reject;

      var settled = false;
      function finish(err, maps) {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        loadReject = null;
        loadPromise = null;
        if (err) {
          reject(err);
        } else {
          resolve(maps);
        }
      }

      var timer = setTimeout(function () {
        finish(new Error('Google Maps timed out loading. Check your network or API key restrictions.'));
      }, 15000);

      window.__zrGoogleMapsReady = function () {
        if (window.google && window.google.maps && window.google.maps.places) {
          finish(null, window.google.maps);
        } else {
          finish(new Error('Google Maps loaded but Places library is missing. Enable Places API in Google Cloud.'));
        }
      };

      var script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key='
        + encodeURIComponent(cfg.apiKey)
        + '&libraries=places&loading=async&callback=__zrGoogleMapsReady&region=CA';
      script.async = true;
      script.defer = true;
      script.onerror = function () {
        finish(new Error('Could not download Google Maps (blocked network, CSP, or invalid key).'));
      };
      document.head.appendChild(script);
    });

    return loadPromise;
  }

  function verifyPlacesApi(maps) {
    return new Promise(function (resolve, reject) {
      if (!maps.places || !maps.places.AutocompleteService) {
        reject(new Error('Places library is missing. Enable Places API in Google Cloud Console.'));
        return;
      }

      var settled = false;
      var timer = setTimeout(function () {
        if (settled) return;
        settled = true;
        reject(new Error('Places API did not respond. Check that Maps JavaScript API and Places API are enabled.'));
      }, 10000);

      var service = new maps.places.AutocompleteService();
      service.getPlacePredictions(
        { input: 'Toronto', componentRestrictions: { country: 'ca' } },
        function (predictions, status) {
          if (settled) return;
          settled = true;
          clearTimeout(timer);

          if (
            status === maps.places.PlacesServiceStatus.OK
            || status === maps.places.PlacesServiceStatus.ZERO_RESULTS
          ) {
            resolve();
            return;
          }

          if (status === maps.places.PlacesServiceStatus.REQUEST_DENIED) {
            reject(new Error(
              'Google rejected Places requests (ApiNotActivatedMapError). In Google Cloud Console enable Maps JavaScript API and Places API for this key, turn on billing, and allow your site URL under HTTP referrer restrictions.'
            ));
            return;
          }

          reject(new Error('Places API check failed (' + status + '). Verify Maps JavaScript API and Places API are enabled.'));
        }
      );
    });
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
    setFieldStatus(input, '', '');
  }

  function clearGeoFields(input) {
    var placeIdInput = document.getElementById(input.getAttribute('data-place-id-input'));
    var latInput = document.getElementById(input.getAttribute('data-lat-input'));
    var lngInput = document.getElementById(input.getAttribute('data-lng-input'));
    if (placeIdInput) placeIdInput.value = '';
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
  }

  function setFieldStatus(input, message, type) {
    var wrap = input.closest('.admin-form-group');
    if (!wrap) return;
    var el = wrap.querySelector('[data-places-status]');
    if (!el) return;
    el.textContent = message || '';
    el.className = 'admin-form-help admin-places-status'
      + (type === 'error' ? ' admin-places-status--error' : '')
      + (type === 'ok' ? ' admin-places-status--ok' : '');
    el.hidden = !message;
  }

  function initAutocompleteInput(input) {
    if (input._zrPlacesWired) return;
    input._zrPlacesWired = true;

    if (!cfg.configured || !cfg.apiKey) {
      input.placeholder = 'Enter address manually (Maps API key not configured)';
      setFieldStatus(
        input,
        'Add GOOGLE_MAPS_API_KEY to .env on this server, then run php artisan config:clear.',
        'error'
      );
      return;
    }

    setFieldStatus(input, 'Checking Google Places…', '');

    loadMapsApi()
      .then(verifyPlacesApi)
      .then(function () {
        input.placeholder = 'Start typing an address…';
        var autocomplete = new google.maps.places.Autocomplete(input, {
          fields: ['formatted_address', 'geometry', 'place_id'],
          componentRestrictions: { country: ['ca'] }
        });
        autocomplete.addListener('place_changed', function () {
          fillFromPlace(input, autocomplete.getPlace());
        });
        setFieldStatus(input, 'Address suggestions enabled — pick one from the list.', 'ok');
      }).catch(function (err) {
      input.placeholder = 'Enter address manually (Google Places unavailable)';
      var msg = (err && err.message) ? err.message : 'Google Places could not be loaded.';
      setFieldStatus(input, msg, 'error');
      if (window.console && console.warn) {
        console.warn('[admin-google-maps]', msg);
      }
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

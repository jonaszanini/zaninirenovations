(function () {
  'use strict';

  var cfg = window.ZR_GOOGLE_MAPS || {};
  var loadPromise = null;
  var loadReject = null;
  var loadFailedMessage = null;

  function currentReferrerPattern() {
    if (!window.location || !window.location.origin) {
      return 'https://your-domain.com/*';
    }
    return window.location.origin + '/*';
  }

  function apiKeyHint() {
    if (!cfg.apiKey || cfg.apiKey.length < 8) {
      return '';
    }
    return ' Server key starts with ' + cfg.apiKey.slice(0, 8) + '… — edit that exact key in Google Cloud.';
  }

  function referrerRestrictionMessage() {
    return 'Google blocked this page (RefererNotAllowedMapError). In Google Cloud Console → Credentials → your API key → Application restrictions → HTTP referrers, add: '
      + currentReferrerPattern()
      + (window.location.hostname === 'localhost' ? '' : ' and http://localhost/* for local dev.')
      + apiKeyHint();
  }

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
    rejectPendingLoad(referrerRestrictionMessage());
  };

  function watchMapsConsoleErrors(durationMs, onMatch) {
    if (!window.console || typeof console.error !== 'function') {
      return function () {};
    }
    var original = console.error.bind(console);
    var timer = setTimeout(restore, durationMs);

    function restore() {
      console.error = original;
      clearTimeout(timer);
    }

    console.error = function () {
      var text = Array.prototype.slice.call(arguments).join(' ');
      if (
        text.indexOf('RefererNotAllowedMapError') !== -1
        || text.indexOf('ApiNotActivatedMapError') !== -1
      ) {
        onMatch(text);
        restore();
      }
      original.apply(console, arguments);
    };

    return restore;
  }

  function loadMapsApi() {
    if (!cfg.configured || !cfg.apiKey) {
      return Promise.reject(new Error('GOOGLE_MAPS_API_KEY is not set on the server.'));
    }
    if (loadFailedMessage) {
      return Promise.reject(new Error(loadFailedMessage));
    }
    if (loadPromise) {
      return loadPromise;
    }

    loadPromise = new Promise(function (resolve, reject) {
      loadReject = reject;
      var stopWatching = watchMapsConsoleErrors(15000, function (text) {
        if (text.indexOf('RefererNotAllowedMapError') !== -1) {
          finish(new Error(referrerRestrictionMessage()));
        } else if (text.indexOf('ApiNotActivatedMapError') !== -1) {
          finish(new Error(
            'Enable Maps JavaScript API and Places API (New) for this key in Google Cloud Console, with billing on.'
          ));
        }
      });

      var settled = false;
      function finish(err, placesLib) {
        if (settled) return;
        settled = true;
        stopWatching();
        clearTimeout(timer);
        loadReject = null;
        loadPromise = null;
        if (err) {
          reject(err);
        } else {
          resolve(placesLib);
        }
      }

      var timer = setTimeout(function () {
        finish(new Error(
          'Google Maps timed out. If the console shows RefererNotAllowedMapError, add '
          + currentReferrerPattern()
          + ' to your API key HTTP referrer allowlist.'
        ));
      }, 15000);

      function importPlaces() {
        if (!window.google || !window.google.maps || !window.google.maps.importLibrary) {
          finish(new Error('Google Maps loaded but importLibrary is unavailable.'));
          return;
        }
        window.google.maps.importLibrary('places').then(function (placesLib) {
          if (!placesLib.PlaceAutocompleteElement) {
            finish(new Error(
              'Places API (New) is required. Enable Places API (New) in Google Cloud Console and add it to your API key restrictions.'
            ));
            return;
          }
          finish(null, placesLib);
        }).catch(function (err) {
          finish(err instanceof Error ? err : new Error('Could not load Google Places library.'));
        });
      }

      if (window.google && window.google.maps && window.google.maps.importLibrary) {
        importPlaces();
        return;
      }

      window.__zrGoogleMapsReady = function () {
        importPlaces();
      };

      var script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key='
        + encodeURIComponent(cfg.apiKey)
        + '&loading=async&callback=__zrGoogleMapsReady&region=CA';
      script.async = true;
      script.defer = true;
      script.onerror = function () {
        finish(new Error('Could not download Google Maps (blocked network, CSP, or invalid key).'));
      };
      document.head.appendChild(script);
    });

    return loadPromise;
  }

  function styleAutocompleteElement(element) {
    element.style.setProperty('width', '100%');
    element.style.setProperty('color-scheme', 'light');
    element.style.setProperty('background-color', '#fff');
    element.style.setProperty('border', '1px solid #d8dee4');
    element.style.setProperty('border-radius', '8px');
  }

  function getFieldRefs(mount) {
    return {
      wrap: mount.closest('.admin-form-group'),
      addressInput: document.getElementById(mount.getAttribute('data-address-input')),
      placeIdInput: document.getElementById(mount.getAttribute('data-place-id-input')),
      latInput: document.getElementById(mount.getAttribute('data-lat-input')),
      lngInput: document.getElementById(mount.getAttribute('data-lng-input'))
    };
  }

  function setFieldStatus(mount, message, type) {
    var wrap = mount.closest('.admin-form-group');
    if (!wrap) return;
    var el = wrap.querySelector('[data-places-status]');
    if (!el) return;
    el.textContent = message || '';
    el.className = 'admin-form-help admin-places-status'
      + (type === 'error' ? ' admin-places-status--error' : '')
      + (type === 'ok' ? ' admin-places-status--ok' : '');
    el.hidden = !message;
  }

  function clearGeoFields(refs) {
    if (refs.placeIdInput) refs.placeIdInput.value = '';
    if (refs.latInput) refs.latInput.value = '';
    if (refs.lngInput) refs.lngInput.value = '';
    if (refs.addressInput) refs.addressInput.value = '';
  }

  function fillFromPlace(mount, refs, place) {
    if (!place || !place.location) {
      setFieldStatus(
        mount,
        'Could not get map coordinates for this address. Pick another suggestion or check API key settings.',
        'error'
      );
      return;
    }

    if (refs.addressInput) {
      refs.addressInput.value = place.formattedAddress || '';
    }
    if (refs.placeIdInput) {
      refs.placeIdInput.value = place.id || '';
    }
    if (refs.latInput) {
      refs.latInput.value = place.location.lat();
    }
    if (refs.lngInput) {
      refs.lngInput.value = place.location.lng();
    }
    setFieldStatus(mount, 'Address selected — save the form to keep it.', 'ok');
  }

  function handlePlaceSelect(mount, refs, event) {
    if (!event.placePrediction) {
      return;
    }

    setFieldStatus(mount, 'Loading address…', '');

    var place = event.placePrediction.toPlace();
    place.fetchFields({ fields: ['formattedAddress', 'location', 'id'] }).then(function () {
      fillFromPlace(mount, refs, place);
    }).catch(function () {
      setFieldStatus(
        mount,
        'Could not load address details. Enable Places API (New) and check referrer restrictions.' + apiKeyHint(),
        'error'
      );
    });
  }

  function initAutocompleteMount(mount) {
    if (mount._zrPlacesWired) return;
    mount._zrPlacesWired = true;

    var refs = getFieldRefs(mount);

    if (!cfg.configured || !cfg.apiKey) {
      setFieldStatus(
        mount,
        'Add GOOGLE_MAPS_API_KEY to .env on this server, then run php artisan config:clear.',
        'error'
      );
      return;
    }

    setFieldStatus(mount, 'Checking Google Places…', '');

    loadMapsApi().then(function (placesLib) {
      var PlaceAutocompleteElement = placesLib.PlaceAutocompleteElement;
      var widgetId = mount.getAttribute('data-address-input') + '-widget';
      var autocomplete = new PlaceAutocompleteElement({
        includedRegionCodes: ['ca']
      });

      autocomplete.id = widgetId;
      autocomplete.placeholder = 'Start typing an address…';
      styleAutocompleteElement(autocomplete);

      if (refs.addressInput && refs.addressInput.value) {
        autocomplete.value = refs.addressInput.value;
      }

      mount.appendChild(autocomplete);

      autocomplete.addEventListener('gmp-select', function (event) {
        handlePlaceSelect(mount, refs, event);
      });

      autocomplete.addEventListener('gmp-error', function () {
        setFieldStatus(
          mount,
          'Google Places error. Check referrer restrictions and that Places API (New) is enabled.' + apiKeyHint(),
          'error'
        );
      });

      autocomplete.addEventListener('input', function () {
        if (!autocomplete.value || !String(autocomplete.value).trim()) {
          clearGeoFields(refs);
          setFieldStatus(mount, '', '');
        }
      });

      setFieldStatus(mount, 'Address suggestions enabled — pick one from the list.', 'ok');
    }).catch(function (err) {
      var msg = (err && err.message) ? err.message : 'Google Places could not be loaded.';
      setFieldStatus(mount, msg, 'error');
      if (window.console && console.warn) {
        console.warn('[admin-google-maps]', msg);
      }
    });
  }

  function initAll() {
    document.querySelectorAll('[data-places-mount]').forEach(initAutocompleteMount);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();

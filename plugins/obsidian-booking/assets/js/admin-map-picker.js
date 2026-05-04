/**
 * Obsidian Map Picker — Admin Leaflet Map for Location Editor
 *
 * Provides an interactive map that syncs bidirectionally with the
 * ACF Latitude / Longitude number fields:
 *
 *   Map click / drag  →  updates ACF field inputs
 *   ACF field change   →  moves the map pin
 *
 * Also includes a search-by-place feature using the free
 * Nominatim (OpenStreetMap) geocoding API.
 *
 * @package obsidian-booking
 */
(function () {
  "use strict";

  /* ── Constants ── */
  const PH_CENTER = [12.8797, 121.774];
  const DEFAULT_ZOOM = 6;
  const PIN_ZOOM = 16;

  /* ── DOM references ── */
  let wrapper, canvas, readout, clearBtn;
  let searchInput, searchBtn, searchResults;
  let manualLatInput, manualLngInput;

  /* ── Leaflet objects ── */
  let map, marker;

  /* ── ACF field inputs (resolved after DOMContentLoaded) ── */
  let latInput, lngInput;

  /* ──────────────────────────────────────────────────────────
     Initialise
     ────────────────────────────────────────────────────────── */
  document.addEventListener("DOMContentLoaded", function () {
    wrapper = document.querySelector(".obsidian-map-picker");
    if (!wrapper) return;

    canvas = document.getElementById("obsidian-map-picker-canvas");
    readout = document.getElementById("obsidian-map-picker-readout");
    clearBtn = document.getElementById("obsidian-map-clear-pin");
    searchInput = document.getElementById("obsidian-map-search");
    searchBtn = document.getElementById("obsidian-map-search-btn");
    searchResults = document.getElementById("obsidian-map-search-results");
    manualLatInput = document.getElementById("obsidian-map-lat-manual");
    manualLngInput = document.getElementById("obsidian-map-lng-manual");

    // Wait for Leaflet to be available
    waitForLeaflet(function () {
      initMap();
      bindSync();
      bindSearch();
    });
  });

  /* ── Wait for Leaflet ── */
  function waitForLeaflet(cb) {
    if (typeof window.L !== "undefined") {
      cb();
      return;
    }
    let tries = 0;
    const iv = setInterval(function () {
      if (typeof window.L !== "undefined") {
        clearInterval(iv);
        cb();
      } else if (++tries > 80) {
        clearInterval(iv);
        console.warn("Obsidian Map Picker: Leaflet did not load.");
      }
    }, 100);
  }

  /* ──────────────────────────────────────────────────────────
     Map Setup
     ────────────────────────────────────────────────────────── */
  function initMap() {
    const initLat = parseFloat(wrapper.dataset.lat);
    const initLng = parseFloat(wrapper.dataset.lng);
    const hasCoords = !isNaN(initLat) && !isNaN(initLng);

    map = L.map(canvas, {
      center: hasCoords ? [initLat, initLng] : PH_CENTER,
      zoom: hasCoords ? PIN_ZOOM : DEFAULT_ZOOM,
      scrollWheelZoom: true,
      zoomControl: true,
    });

    // Dark-themed tile layer (matches admin dark theme)
    L.tileLayer(
      "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
      {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
      }
    ).addTo(map);

    // Place initial pin if coordinates exist
    if (hasCoords) {
      placePin(initLat, initLng, false, false);
    }

    // Click to place / move pin
    map.on("click", function (e) {
      placePin(e.latlng.lat, e.latlng.lng, true, false);
    });

    // Clear pin
    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        removePin();
      });
    }

    // Fix tile rendering issues when the meta box is collapsed/expanded
    // by triggering invalidateSize after a short delay.
    setTimeout(function () {
      map.invalidateSize();
    }, 300);

    // Also fix if the postbox is toggled
    const postbox = canvas.closest(".postbox");
    if (postbox) {
      const toggleBtn = postbox.querySelector(".handlediv, .postbox-header");
      if (toggleBtn) {
        toggleBtn.addEventListener("click", function () {
          setTimeout(function () {
            map.invalidateSize();
          }, 350);
        });
      }
    }
  }

  /* ──────────────────────────────────────────────────────────
     Pin Management
     ────────────────────────────────────────────────────────── */
  function placePin(lat, lng, updateFields, shouldZoom) {
    lat = roundCoord(lat);
    lng = roundCoord(lng);

    if (marker) {
      marker.setLatLng([lat, lng]);
    } else {
      marker = L.marker([lat, lng], {
        draggable: true,
        icon: goldPinIcon(),
      }).addTo(map);

      // Drag end → update fields
      marker.on("dragend", function (e) {
        const pos = e.target.getLatLng();
        const rLat = roundCoord(pos.lat);
        const rLng = roundCoord(pos.lng);
        updateReadout(rLat, rLng);
        syncToInputs(rLat, rLng);
      });
    }

    updateReadout(lat, lng);

    if (updateFields) {
      syncToInputs(lat, lng);
    }

    if (shouldZoom) {
      map.setView([lat, lng], Math.max(map.getZoom(), PIN_ZOOM));
    }
  }

  function removePin() {
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }
    if (clearBtn) readout.textContent =
      "Click the map to place a pin, or use the search bar above.";
    if (clearBtn) clearBtn.style.display = "none";
    syncToInputs("", "");
    map.setView(PH_CENTER, DEFAULT_ZOOM);
  }

  function goldPinIcon() {
    return L.divIcon({
      className: "obsidian-map-picker-pin",
      html:
        '<svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M16 0C7.164 0 0 7.164 0 16c0 12 16 26 16 26s16-14 16-26C32 7.164 24.836 0 16 0z" fill="#c9a962"/>' +
        '<circle cx="16" cy="16" r="7" fill="#111318" stroke="#c9a962" stroke-width="1"/>' +
        "</svg>",
      iconSize: [32, 42],
      iconAnchor: [16, 42],
    });
  }

  function updateReadout(lat, lng) {
    readout.textContent = lat + ", " + lng;
    if (clearBtn) clearBtn.style.display = "";
  }

  function roundCoord(val) {
    return Math.round(parseFloat(val) * 1e6) / 1e6;
  }

  /* ──────────────────────────────────────────────────────────
     ACF Field Sync
     ────────────────────────────────────────────────────────── */
  function resolveInputs() {
    latInput = document.getElementById("obsidian-map-lat-hidden");
    lngInput = document.getElementById("obsidian-map-lng-hidden");
  }

  function syncToInputs(lat, lng) {
    if (!latInput || !lngInput) resolveInputs();

    // Update manual visible inputs
    if (manualLatInput) manualLatInput.value = lat;
    if (manualLngInput) manualLngInput.value = lng;

    // Update hidden inputs
    if (latInput && lngInput) {
      latInput.value = lat;
      lngInput.value = lng;
    }
  }

  function bindSync() {
    resolveInputs();

    const onFieldChange = debounce(function (e) {
      const lat = manualLatInput ? parseFloat(manualLatInput.value) : (latInput ? parseFloat(latInput.value) : 0);
      const lng = manualLngInput ? parseFloat(manualLngInput.value) : (lngInput ? parseFloat(lngInput.value) : 0);

      if (!isNaN(lat) && !isNaN(lng)) {
        // If the values are the same as the current marker, don't do anything
        if (marker) {
          const curr = marker.getLatLng();
          if (roundCoord(curr.lat) === roundCoord(lat) && roundCoord(curr.lng) === roundCoord(lng)) {
            return;
          }
        }

        placePin(lat, lng, true, true);
      }
    }, 600);

    if (manualLatInput) manualLatInput.addEventListener("input", onFieldChange);
    if (manualLngInput) manualLngInput.addEventListener("input", onFieldChange);

    // Still listen to hidden inputs
    if (latInput) latInput.addEventListener("input", onFieldChange);
    if (lngInput) lngInput.addEventListener("input", onFieldChange);
  }

  /* ──────────────────────────────────────────────────────────
     Place Search (Nominatim / OpenStreetMap)
     ────────────────────────────────────────────────────────── */
  function bindSearch() {
    if (!searchInput || !searchBtn) return;

    searchBtn.addEventListener("click", function () {
      doSearch(searchInput.value.trim());
    });

    searchInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        doSearch(searchInput.value.trim());
      }
    });

    // Close results when clicking outside
    document.addEventListener("click", function (e) {
      if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput && e.target !== searchBtn) {
        searchResults.hidden = true;
      }
    });
  }

  function doSearch(query) {
    if (!query || query.length < 2) return;

    searchResults.innerHTML =
      '<div class="obsidian-map-picker__search-loading">Searching…</div>';
    searchResults.hidden = false;

    // Nominatim free geocoder — no API key, 1 req/s rate limit
    const url =
      "https://nominatim.openstreetmap.org/search?" +
      "format=json&limit=5&countrycodes=ph&q=" +
      encodeURIComponent(query);

    fetch(url, {
      headers: { Accept: "application/json" },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (results) {
        if (!results.length) {
          searchResults.innerHTML =
            '<div class="obsidian-map-picker__search-empty">No results found. Try a different query.</div>';
          return;
        }

        let html = "";
        results.forEach(function (r) {
          html +=
            '<button type="button" class="obsidian-map-picker__search-item" ' +
            'data-lat="' +
            r.lat +
            '" data-lng="' +
            r.lon +
            '">' +
            '<span class="dashicons dashicons-location-alt"></span> ' +
            escapeHTML(r.display_name) +
            "</button>";
        });
        searchResults.innerHTML = html;

        // Bind clicks on results
        searchResults.querySelectorAll(".obsidian-map-picker__search-item").forEach(function (item) {
          item.addEventListener("click", function () {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            placePin(lat, lng, true, true);
            searchResults.hidden = true;
            searchInput.value = "";
          });
        });
      })
      .catch(function () {
        searchResults.innerHTML =
          '<div class="obsidian-map-picker__search-empty">Search failed. Please try again.</div>';
      });
  }

  /* ── Helpers ── */
  function debounce(fn, ms) {
    let timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(fn, ms);
    };
  }

  function escapeHTML(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }
})();

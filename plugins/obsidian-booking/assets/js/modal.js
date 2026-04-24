/**
 * Obsidian Booking — Modal JS
 * Phase 5: Two-column modal with gallery, specs, customer type, and date pickers.
 */
(function () {
   'use strict';

   var cfg = window.obsidianBooking || {};

   var modal, overlay, loader, content;
   var heroImg, thumbsContainer, colorsContainer;
   var nameEl, classEl, rateEl, specsEl, ctaTextEl;
   var pickupInput, dropoffInput, proceedBtn;
   var totalWrap, totalValue, totalBreakdown;
   var statusEl, statusTextEl;
   var pickupFP, dropoffFP;
   var currentCar = null;
   var selectedColor = null;

   /* ── Phase 11.13: pickup-branch picker ── */
   var branchEl, branchNameEl, branchChangeBtn, branchSelectEl;
   var selectedLocationId = 0;          // current scope (0 = "All Locations" — picker mode)
   var branchLocked = false;            // true when ?location/?region pre-scoped the modal
   var branchesCache = null;            // [{id,name,region_name,status,...}, ...]

   function init() {
      modal = document.getElementById('obsidian-booking-modal');
      if (!modal) return;

      overlay         = modal.querySelector('.obsidian-modal-overlay');
      loader          = document.getElementById('obsidian-modal-loader');
      content         = document.getElementById('obsidian-modal-content');
      heroImg         = document.getElementById('obsidian-modal-hero');
      thumbsContainer = document.getElementById('obsidian-modal-thumbs');
      colorsContainer = document.getElementById('obsidian-modal-colors');
      nameEl          = document.getElementById('obsidian-modal-name');
      classEl         = document.getElementById('obsidian-modal-class');
      rateEl          = document.getElementById('obsidian-modal-rate-value');
      specsEl         = document.getElementById('obsidian-modal-specs');
      ctaTextEl       = document.getElementById('obsidian-modal-cta-text');
      pickupInput     = document.getElementById('obsidian-pickup-date');
      dropoffInput    = document.getElementById('obsidian-dropoff-date');
      proceedBtn      = document.getElementById('obsidian-modal-proceed');
      totalWrap       = document.getElementById('obsidian-modal-total');
      totalValue      = document.getElementById('obsidian-modal-total-value');
      totalBreakdown  = document.getElementById('obsidian-modal-total-breakdown');
      statusEl        = document.getElementById('obsidian-modal-status');
      statusTextEl    = document.getElementById('obsidian-modal-status-text');

      branchEl         = document.getElementById('obsidian-modal-branch');
      branchNameEl     = document.getElementById('obsidian-modal-branch-name');
      branchChangeBtn  = document.getElementById('obsidian-modal-branch-change');
      branchSelectEl   = document.getElementById('obsidian-modal-branch-select');

      modal.querySelector('.obsidian-modal-close').addEventListener('click', closeModal);
      overlay.addEventListener('click', closeModal);
      document.addEventListener('keydown', function (e) {
         if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeModal();
         }
      });

      document.addEventListener('click', function (e) {
         var btn = e.target.closest('.car-book-btn[data-car-id]');
         if (btn) {
            e.preventDefault();
            openModal(parseInt(btn.getAttribute('data-car-id'), 10));
         }
      });

      proceedBtn.addEventListener('click', handleProceed);

      // Re-validate (and refresh the status hint) whenever the customer-type
      // radios change, so the user sees the same UX feedback for them too.
      modal.querySelectorAll('input[name="obsidian_customer_type"]').forEach(function (r) {
         r.addEventListener('change', validateForm);
      });

      /* Branch picker events (Phase 11.13). */
      branchChangeBtn.addEventListener('click', function () {
         // "Edit" toggle when branch was pre-locked from the fleet URL.
         branchLocked = false;
         enterBranchPickMode();
      });
      branchSelectEl.addEventListener('change', function () {
         var id = parseInt(branchSelectEl.value, 10) || 0;
         if (!id) {
            selectedLocationId = 0;
            disableFormUntilBranchPicked();
            return;
         }
         applySelectedBranch(id);
      });
   }

   /* ── Open / Close ── */

   function handleResponse(r) {
      if (!r.ok) {
         return r.text().then(function (body) {
            throw new Error('HTTP ' + r.status + ': ' + body.substring(0, 200));
         });
      }
      return r.json();
   }

   function openModal(carId) {
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('obsidian-modal-open');
      loader.innerHTML = '<span class="obsidian-modal-spinner"></span>';
      loader.style.display = '';
      content.style.display = 'none';

      // Phase 11.13: read URL filters set by the fleet sidebar/header dropdown.
      // We support `?location=<slug>` (specific branch — locks the modal),
      // `?region=<slug>`  (group — falls through to picker scoped to that region),
      // and nothing       (full picker, no preselect).
      var urlParams = new URLSearchParams(window.location.search);
      var urlLocationSlug = urlParams.get('location') || '';
      var urlRegionSlug   = urlParams.get('region') || '';

      // Reset state from any prior open.
      currentCar           = null;
      selectedColor        = null;
      selectedLocationId   = 0;
      branchLocked         = false;

      // We need the branch list before deciding pickup-at vs. picker. Fetching
      // it once and caching keeps subsequent opens fast.
      ensureBranchesLoaded(carId).then(function (branches) {

         // 1. Resolve URL filter → numeric branch ID (the rest of the system
         //    works in IDs because slugs aren't unique to a single endpoint).
         var preSelectedId = 0;
         if (urlLocationSlug) {
            var match = branches.filter(function (b) { return b.slug === urlLocationSlug; })[0];
            if (match) {
               preSelectedId = match.id;
               branchLocked = true; // user picked one specific branch — lock it.
            }
         }

         // 2. Filter the picker options by region if `?region=` is present.
         //    If the filter wipes the list (no stocked branch in that region),
         //    fall back to the full list so the user can still book.
         var pickerBranches = branches;
         if (!preSelectedId && urlRegionSlug) {
            var regionFiltered = branches.filter(function (b) { return b.region_slug === urlRegionSlug; });
            if (regionFiltered.length > 0) {
               pickerBranches = regionFiltered;
            }
         }

         buildBranchSelect(pickerBranches);

         // 3. Either lock to the chosen branch OR enter pick mode.
         if (preSelectedId) {
            applySelectedBranch(preSelectedId);
         } else {
            // No specific branch yet — fetch the car (aggregated, no scope)
            // and show the picker. Form stays disabled until a branch is chosen.
            return loadCar(carId, 0).then(disableFormUntilBranchPicked);
         }
      }).catch(function (err) {
         console.error('Obsidian Modal Error:', err);
         loader.innerHTML = '<p style="color:#ff6b6b;text-align:center;padding:40px 24px;font-size:14px;">'
            + 'Failed to load vehicle data.<br><small style="opacity:.6">' + err.message + '</small></p>';
      });
   }

   /**
    * Fetch (and cache) every branch the car is stocked at, regardless of URL.
    * The endpoint /cars/{id} (no scope) returns a `branches` array thanks to
    * `obsidian_format_car_data()` in the REST layer.
    */
   function ensureBranchesLoaded(carId) {
      if (branchesCache && branchesCache._car === carId) {
         return Promise.resolve(branchesCache);
      }
      return fetch(cfg.restUrl + 'cars/' + carId, { headers: { 'X-WP-Nonce': cfg.nonce } })
         .then(handleResponse)
         .then(function (car) {
            if (car.code) {
               throw new Error('Car API error: ' + (car.message || car.code));
            }
            // Stash the bare car too so loadCar() with location_id=0 later can
            // reuse this response (and we don't blink the spinner twice).
            currentCar = car;
            currentCar._unavailable         = [];
            currentCar._unavailableByColor  = {};
            branchesCache = (car.branches || []).slice();
            branchesCache._car = carId;
            return branchesCache;
         });
   }

   /**
    * Re-fetch /cars/{id}?location_id=X and /availability/{car_id}?location_id=X
    * so color_variants, unit counts, and disabled dates all reflect the chosen
    * branch's stock — not aggregated across branches.
    */
   function loadCar(carId, locationId) {
      var carUrl    = cfg.restUrl + 'cars/'         + carId + (locationId ? '?location_id=' + locationId : '');
      var availUrl  = cfg.restUrl + 'availability/' + carId + (locationId ? '?location_id=' + locationId : '');

      return Promise.all([
         fetch(carUrl,   { headers: { 'X-WP-Nonce': cfg.nonce } }).then(handleResponse),
         fetch(availUrl, { headers: { 'X-WP-Nonce': cfg.nonce } }).then(handleResponse)
      ]).then(function (results) {
         var car   = results[0];
         var avail = results[1];

         if (car.code) {
            throw new Error('Car API error: ' + (car.message || car.code));
         }

         currentCar = car;
         currentCar._unavailable         = (avail && avail.unavailable_dates) ? avail.unavailable_dates : [];
         currentCar._unavailableByColor  = (avail && avail.unavailable_dates_by_color) ? avail.unavailable_dates_by_color : {};
         selectedColor = null;

         populateModal(currentCar);

         // Re-init Flatpickr on first load; on subsequent (branch swap) loads,
         // just push the new disabled dates into the existing instances.
         if (!pickupFP || !dropoffFP) {
            initFlatpickr(getDisableDatesForCurrentColor());
         }
         applyColorDisableDates();

         loader.style.display = 'none';
         content.style.display = '';
      });
   }

   /* ── Phase 11.13 helpers ── */

   function buildBranchSelect(branches) {
      branchSelectEl.innerHTML = '<option value="">Select branch…</option>';
      branches.forEach(function (b) {
         if (b.status && b.status !== 'active') { return; } // hide coming_soon/closed
         var opt = document.createElement('option');
         opt.value = b.id;
         opt.textContent = b.region_name ? (b.name + ' — ' + b.region_name) : b.name;
         branchSelectEl.appendChild(opt);
      });
   }

   function applySelectedBranch(branchId) {
      selectedLocationId = branchId;
      var branch = (branchesCache || []).filter(function (b) { return b.id === branchId; })[0];

      // Render the locked state: name + ✏️ edit button, hide the <select>.
      branchEl.hidden = false;
      branchEl.classList.remove('is-pickable');
      branchEl.classList.add('is-locked');
      branchSelectEl.hidden = true;
      branchChangeBtn.hidden = false;
      branchNameEl.textContent = branch ? branch.name : '';

      // Re-fetch car/availability scoped to the chosen branch so swatches,
      // unit counts, and Flatpickr reflect the branch's actual stock.
      if (!currentCar) { return; }
      loader.style.display = '';
      content.style.display = 'none';
      loadCar(currentCar.id, branchId).then(enableForm);
   }

   function enterBranchPickMode() {
      branchEl.hidden = false;
      branchEl.classList.add('is-pickable');
      branchEl.classList.remove('is-locked');
      branchSelectEl.hidden = false;
      branchChangeBtn.hidden = true;
      branchSelectEl.value = ''; // force a fresh choice
      disableFormUntilBranchPicked();
   }

   /**
    * When the modal is open but no branch is chosen yet, lock down everything
    * downstream — radios, dates, CTAs — so nothing can be filled in against
    * stale "all branches" stock numbers.
    */
   function disableFormUntilBranchPicked() {
      branchEl.hidden = false;
      branchEl.classList.add('is-pickable');
      branchEl.classList.remove('is-locked');
      branchSelectEl.hidden = false;
      branchChangeBtn.hidden = true;

      var inputs = modal.querySelectorAll(
         'input[name="obsidian_customer_type"], #obsidian-pickup-date, #obsidian-dropoff-date, ' +
         '.obsidian-modal-color-radio, .obsidian-modal-field .flatpickr-input'
      );
      inputs.forEach(function (i) { i.disabled = true; });

      // Also disable via flatpickr API if instances exist (covers edge cases
      // where flatpickr recreates the altInput after a DOM disable pass).
      if (pickupFP)  { pickupFP.altInput  && (pickupFP.altInput.disabled  = true); }
      if (dropoffFP) { dropoffFP.altInput && (dropoffFP.altInput.disabled = true); }

      proceedBtn.disabled = true;

      loader.style.display = 'none';
      content.style.display = '';

      // Visually grey out the color swatch area + add a small "why" line.
      colorsContainer.classList.add('is-disabled');
      setColorsNote('Choose a branch above to see colors and stock at that branch.');

      // Make sure the user knows *why* the form is locked.
      validateForm();
   }

   function enableForm() {
      var inputs = modal.querySelectorAll(
         'input[name="obsidian_customer_type"], #obsidian-pickup-date, #obsidian-dropoff-date, ' +
         '.obsidian-modal-color-radio, .obsidian-modal-field .flatpickr-input'
      );
      inputs.forEach(function (i) { i.disabled = false; });

      // Explicitly re-enable flatpickr alt inputs (the visible date fields).
      if (pickupFP)  { pickupFP.altInput  && (pickupFP.altInput.disabled  = false); }
      if (dropoffFP) { dropoffFP.altInput && (dropoffFP.altInput.disabled = false); }

      colorsContainer.classList.remove('is-disabled');
      setColorsNote('');

      validateForm();
   }

   /**
    * Inject (or clear) a small inline note above the color swatches.
    * Used to explain why the swatches look unclickable.
    */
   function setColorsNote(text) {
      var note = colorsContainer.querySelector('.obsidian-modal-colors-note');
      if (!text) {
         if (note) note.remove();
         return;
      }
      if (!note) {
         note = document.createElement('div');
         note.className = 'obsidian-modal-colors-note';
         note.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFB04A" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span>';
         colorsContainer.insertBefore(note, colorsContainer.firstChild);
      }
      note.querySelector('span').textContent = text;
   }

   function closeModal() {
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('obsidian-modal-open');

      if (pickupFP) { pickupFP.destroy(); pickupFP = null; }
      if (dropoffFP) { dropoffFP.destroy(); dropoffFP = null; }

      currentCar = null;
      selectedColor = null;
      selectedLocationId = 0;
      branchLocked = false;
      branchesCache = null;
      proceedBtn.disabled = true;
      totalWrap.style.display = 'none';

      // Reset branch picker UI for next open.
      branchEl.hidden = true;
      branchEl.classList.remove('is-locked', 'is-pickable');
      branchNameEl.textContent = '';
      branchSelectEl.innerHTML = '<option value="">Select branch…</option>';

      // Reset status hint + colors-locked state.
      if (statusEl) {
         statusEl.hidden = true;
         statusEl.classList.remove('is-info', 'is-warn', 'is-success');
      }
      if (colorsContainer) {
         colorsContainer.classList.remove('is-disabled');
      }

      var localRadio = modal.querySelector('input[name="obsidian_customer_type"][value="local"]');
      if (localRadio) localRadio.checked = true;
   }

   /* ── Populate ── */

   function populateModal(car) {
      nameEl.textContent = car.name;
      classEl.textContent = car.car_class || '';
      rateEl.textContent = '\u20B1' + numberFormat(car.daily_rate);

      // Specifications — textarea with one spec per line → bulleted list
      if (car.specifications) {
         var lines = car.specifications.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
         specsEl.innerHTML = '<ul>' + lines.map(function (line) {
            // Highlight everything up to the first colon in gold
            var formattedLine = line.trim().replace(/^([^:]+:)/, '<span style="color: #C5A059;">$1</span>');
            return '<li>' + formattedLine + '</li>';
         }).join('') + '</ul>';
         specsEl.style.display = '';
      } else {
         specsEl.innerHTML = '';
         specsEl.style.display = 'none';
      }

      // CTA text with car name
      var shortName = car.make ? car.make + ' ' + (car.model || '') : car.name;
      ctaTextEl.textContent = 'Reserve ' + shortName.trim();

      // Gallery + colors
      if (car.color_variants && car.color_variants.length > 0) {
         selectedColor = car.color_variants[0].color;
         buildGallery(car.color_variants[0].gallery || [], car.image);
      } else {
         selectedColor = null;
         buildGallery([], car.image);
      }

      buildColors(car.color_variants || [], car.image);
   }

   function buildGallery(galleryUrls, fallbackImg) {
      thumbsContainer.innerHTML = '';

      var all = galleryUrls.length > 0 ? galleryUrls : (fallbackImg ? [fallbackImg] : []);

      if (all.length === 0) {
         heroImg.src = '';
         return;
      }

      heroImg.src = all[0];
      heroImg.alt = currentCar ? currentCar.name : '';

      // Thumbnails = images 2–6 (indices 1+)
      var modalImages = all.length > 1 ? all.slice(1) : all;

      modalImages.forEach(function (url, idx) {
         var btn = document.createElement('button');
         btn.className = 'obsidian-modal-thumb' + (idx === 0 ? ' active' : '');

         var img = document.createElement('img');
         img.src = url;
         img.alt = 'Image ' + (idx + 1);
         img.loading = 'lazy';
         btn.appendChild(img);

         btn.addEventListener('click', function () {
            heroImg.src = url;
            thumbsContainer.querySelectorAll('.obsidian-modal-thumb').forEach(function (t) {
               t.classList.remove('active');
            });
            btn.classList.add('active');
         });

         thumbsContainer.appendChild(btn);
      });
   }

   function buildColors(variants, fallbackImg) {
      colorsContainer.innerHTML = '';

      if (!variants.length) {
         colorsContainer.style.display = 'none';
         return;
      }
      colorsContainer.style.display = '';

      variants.forEach(function (v, idx) {
         var label = document.createElement('label');
         label.className = 'obsidian-modal-color-option' + (idx === 0 ? ' active' : '');

         var radio = document.createElement('input');
         radio.type = 'radio';
         radio.name = 'obsidian_modal_color';
         radio.value = v.color;
         radio.className = 'obsidian-modal-color-radio';
         if (idx === 0) radio.checked = true;

         var swatch = document.createElement('span');
         swatch.className = 'obsidian-modal-color-dot';
         swatch.style.backgroundColor = v.hex;

         var name = document.createElement('span');
         name.className = 'obsidian-modal-color-name';
         name.textContent = capitalize(v.color);

         var units = document.createElement('span');
         units.className = 'obsidian-modal-color-units';
         units.textContent = v.units + ' available';

         label.appendChild(radio);
         label.appendChild(swatch);
         label.appendChild(name);
         label.appendChild(units);

         radio.addEventListener('change', function () {
            selectedColor = v.color;

            colorsContainer.querySelectorAll('.obsidian-modal-color-option').forEach(function (opt) {
               opt.classList.remove('active');
            });
            label.classList.add('active');

            buildGallery(v.gallery || [], fallbackImg);
            applyColorDisableDates();
            validateForm();
         });

         colorsContainer.appendChild(label);
      });
   }

   /* ── Flatpickr ── */

   /**
    * Merge the car-wide unavailable dates with the dates that are sold-out
    * for the *currently selected* color. The result is the array of dates
    * that should be disabled in the pickup/dropoff calendars.
    */
   function getDisableDatesForCurrentColor() {
      if (!currentCar) return [];

      var carWide = currentCar._unavailable || [];

      if (!selectedColor || !currentCar._unavailableByColor) {
         return carWide.slice();
      }

      var colorDates = currentCar._unavailableByColor[selectedColor] || [];

      // Use a Set for de-duplication.
      var merged = {};
      carWide.forEach(function (d) { merged[d] = true; });
      colorDates.forEach(function (d) { merged[d] = true; });

      return Object.keys(merged);
   }

   /**
    * Refresh the calendars' disabled dates based on the selected color.
    * If the previously selected pickup/dropoff dates are now disabled,
    * clear them so the user is forced to re-pick valid dates.
    */
   function applyColorDisableDates() {
      var dates = getDisableDatesForCurrentColor();

      if (pickupFP) {
         pickupFP.set('disable', dates);
         if (pickupFP.selectedDates.length > 0) {
            var p = pickupFP.formatDate(pickupFP.selectedDates[0], 'Y-m-d');
            if (dates.indexOf(p) !== -1) {
               pickupFP.clear();
            }
         }
      }

      if (dropoffFP) {
         dropoffFP.set('disable', dates);
         if (dropoffFP.selectedDates.length > 0) {
            var d = dropoffFP.formatDate(dropoffFP.selectedDates[0], 'Y-m-d');
            if (dates.indexOf(d) !== -1) {
               dropoffFP.clear();
            }
         }
      }

      calculateTotal();
      validateForm();
   }

   function initFlatpickr(unavailableDates) {
      pickupFP = flatpickr(pickupInput, {
         minDate: 'today',
         dateFormat: 'Y-m-d',
         altInput: true,
         altFormat: 'M d, Y',
         disable: unavailableDates,
         onChange: function (selectedDates) {
            if (selectedDates.length > 0) {
               var nextDay = new Date(selectedDates[0]);
               nextDay.setDate(nextDay.getDate() + 1);
               dropoffFP.set('minDate', nextDay);
               dropoffFP.open();
            }
            calculateTotal();
            validateForm();
         }
      });

      dropoffFP = flatpickr(dropoffInput, {
         minDate: 'today',
         dateFormat: 'Y-m-d',
         altInput: true,
         altFormat: 'M d, Y',
         disable: unavailableDates,
         onChange: function () {
            calculateTotal();
            validateForm();
         }
      });
   }

   /* ── Price calculation ── */

   function calculateTotal() {
      if (!currentCar) return;

      var start = pickupFP.selectedDates[0];
      var end   = dropoffFP.selectedDates[0];

      if (!start || !end) {
         totalWrap.style.display = 'none';
         return;
      }

      var days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
      if (days < 1) days = 1;

      var total = days * currentCar.daily_rate;
      totalValue.textContent = '\u20B1' + numberFormat(total);
      totalBreakdown.textContent = '(' + days + ' day' + (days > 1 ? 's' : '') + ' \u00D7 \u20B1' + numberFormat(currentCar.daily_rate) + '/day)';
      totalWrap.style.display = '';
   }

   /* ── Validation ── */

   function validateForm() {
      var hasPickup  = pickupFP && pickupFP.selectedDates.length > 0;
      var hasDropoff = dropoffFP && dropoffFP.selectedDates.length > 0;
      var hasColor   = selectedColor || !(currentCar && currentCar.color_variants && currentCar.color_variants.length > 0);

      // Block booking if selected color has 0 units
      var colorAvailable = true;
      if (selectedColor && currentCar && currentCar.color_variants) {
         for (var i = 0; i < currentCar.color_variants.length; i++) {
            if (currentCar.color_variants[i].color === selectedColor) {
               if (currentCar.color_variants[i].units <= 0) {
                  colorAvailable = false;
               }
               break;
            }
         }
      }

      // Phase 11.13: also require a chosen branch.
      var hasBranch = selectedLocationId > 0;

      proceedBtn.disabled = !(hasBranch && hasPickup && hasDropoff && hasColor && colorAvailable);

      // ── UX status hint ──
      // Surface *why* the form / Reserve button is locked. Shown above the
      // CTA so the user doesn't have to guess. Order matters here — start
      // with the earliest gate (branch) and walk down to the latest (dates).
      updateStatusHint({
         hasBranch:      hasBranch,
         hasColor:       hasColor,
         colorAvailable: colorAvailable,
         hasPickup:      hasPickup,
         hasDropoff:     hasDropoff
      });
   }

   /**
    * Decide which contextual hint to show under the Reserve button.
    * Falls back to a quiet success message when everything is good so the
    * user knows the action is intentional.
    */
   function updateStatusHint(state) {
      if (!statusEl || !statusTextEl) return;

      var msg  = '';
      var kind = 'info'; // 'info' | 'warn' | 'success'

      if (!state.hasBranch) {
         msg  = 'Select a pickup branch first to see colors and dates that are actually available.';
         kind = 'warn';
      } else if (!state.hasColor) {
         msg  = 'Choose a color to continue.';
         kind = 'info';
      } else if (!state.colorAvailable) {
         msg  = 'This color is sold out at the chosen branch — pick another color or another branch.';
         kind = 'warn';
      } else if (!state.hasPickup) {
         msg  = 'Pick your delivery date.';
         kind = 'info';
      } else if (!state.hasDropoff) {
         msg  = 'Pick your return date.';
         kind = 'info';
      } else {
         msg  = 'Looks good — ready to reserve.';
         kind = 'success';
      }

      statusEl.hidden = false;
      statusEl.classList.remove('is-info', 'is-warn', 'is-success');
      statusEl.classList.add('is-' + kind);
      statusTextEl.textContent = msg;
   }

   /* ── Proceed → redirect to booking page ── */

   function handleProceed() {
      if (proceedBtn.disabled || !currentCar) return;

      var start        = pickupFP.selectedDates[0];
      var end          = dropoffFP.selectedDates[0];
      var customerType = modal.querySelector('input[name="obsidian_customer_type"]:checked');

      var params = new URLSearchParams({
         car_id:        currentCar.id,
         location_id:   selectedLocationId || '',
         start:         formatDate(start),
         end:           formatDate(end),
         color:         selectedColor || '',
         customer_type: customerType ? customerType.value : 'local'
      });

      window.location.href = (cfg.bookingPageUrl || '/booking/') + '?' + params.toString();
   }

   /* ── Utilities ── */

   function numberFormat(num) {
      return Math.round(num).toLocaleString('en-PH');
   }

   function capitalize(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
   }

   function formatDate(d) {
      var y   = d.getFullYear();
      var m   = ('0' + (d.getMonth() + 1)).slice(-2);
      var day = ('0' + d.getDate()).slice(-2);
      return y + '-' + m + '-' + day;
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
   } else {
      init();
   }
})();

/* ══════════════════════════════════════════════════════════════
   Text Modal Logic (Phase 12)
   ══════════════════════════════════════════════════════════════ */
(function () {
   'use strict';

   var modal = document.getElementById('obsidian-text-modal');
   if (!modal) return;

   var overlay = modal.querySelector('.obsidian-text-modal-overlay');
   var closeBtn = modal.querySelector('.obsidian-text-modal-close');
   var loader = document.getElementById('obsidian-text-modal-loader');
   var contentWrap = document.getElementById('obsidian-text-modal-content');
   var titleEl = document.getElementById('obsidian-text-modal-title');
   var bodyEl = document.getElementById('obsidian-text-modal-body');

   function openModal() {
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
   }

   function closeModal() {
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      // Reset content after transition
      setTimeout(function() {
         titleEl.textContent = '';
         bodyEl.innerHTML = '';
      }, 300);
   }

   // Global event listener for any link that wants to open the modal
   document.addEventListener('click', function(e) {
      var trigger = e.target.closest('a[data-modal="text"]');
      if (!trigger) return;

      e.preventDefault();
      var slug = trigger.getAttribute('data-page-slug');
      if (!slug) return;

      openModal();
      loader.style.display = 'flex';
      contentWrap.style.display = 'none';

      // Use the native WP REST API to fetch the page content
      var endpoint = '/wp-json/wp/v2/pages?slug=' + encodeURIComponent(slug);
      
      fetch(endpoint)
         .then(function(response) {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
         })
         .then(function(pages) {
            loader.style.display = 'none';
            contentWrap.style.display = '';

            if (pages && pages.length > 0) {
               var page = pages[0];
               titleEl.textContent = page.title.rendered || '';
               bodyEl.innerHTML = page.content.rendered || '';
            } else {
               titleEl.textContent = 'Content Not Found';
               bodyEl.innerHTML = '<p>The requested content could not be found. Please ensure a page with the slug "' + slug + '" exists.</p>';
            }
         })
         .catch(function(error) {
            console.error('Error fetching modal content:', error);
            loader.style.display = 'none';
            contentWrap.style.display = '';
            titleEl.textContent = 'Error Loading Content';
            bodyEl.innerHTML = '<p>There was a problem loading the content. Please try again later.</p>';
         });
   });

   closeBtn.addEventListener('click', closeModal);
   overlay.addEventListener('click', closeModal);

   document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
         closeModal();
      }
   });

})();

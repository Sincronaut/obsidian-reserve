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
   var pickupInput, dropoffInput, proceedBtn, checkAvailBtn;
   var pickupFP, dropoffFP;
   var currentCar = null;
   var selectedColor = null;

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
      checkAvailBtn   = document.getElementById('obsidian-modal-check-avail');

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
      checkAvailBtn.addEventListener('click', function () {
         if (pickupFP) pickupFP.open();
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

      Promise.all([
         fetch(cfg.restUrl + 'cars/' + carId, {
            headers: { 'X-WP-Nonce': cfg.nonce }
         }).then(handleResponse),
         fetch(cfg.restUrl + 'availability/' + carId, {
            headers: { 'X-WP-Nonce': cfg.nonce }
         }).then(handleResponse)
      ]).then(function (results) {
         var car   = results[0];
         var avail = results[1];

         if (car.code) {
            throw new Error('Car API error: ' + (car.message || car.code));
         }

         currentCar = car;
         currentCar._unavailable = (avail && avail.unavailable_dates) ? avail.unavailable_dates : [];
         selectedColor = null;

         populateModal(currentCar);
         initFlatpickr(currentCar._unavailable);

         loader.style.display = 'none';
         content.style.display = '';
      }).catch(function (err) {
         console.error('Obsidian Modal Error:', err);
         loader.innerHTML = '<p style="color:#ff6b6b;text-align:center;padding:40px 24px;font-size:14px;">'
            + 'Failed to load vehicle data.<br><small style="opacity:.6">' + err.message + '</small></p>';
      });
   }

   function closeModal() {
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('obsidian-modal-open');

      if (pickupFP) { pickupFP.destroy(); pickupFP = null; }
      if (dropoffFP) { dropoffFP.destroy(); dropoffFP = null; }

      currentCar = null;
      selectedColor = null;
      proceedBtn.disabled = true;

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
            return '<li>' + line.trim() + '</li>';
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
            validateForm();
         });

         colorsContainer.appendChild(label);
      });
   }

   /* ── Flatpickr ── */

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
            validateForm();
         }
      });
   }

   /* ── Validation ── */

   function validateForm() {
      var hasPickup  = pickupFP && pickupFP.selectedDates.length > 0;
      var hasDropoff = dropoffFP && dropoffFP.selectedDates.length > 0;
      var hasColor   = selectedColor || !(currentCar && currentCar.color_variants && currentCar.color_variants.length > 0);

      proceedBtn.disabled = !(hasPickup && hasDropoff && hasColor);
   }

   /* ── Proceed → redirect to booking page ── */

   function handleProceed() {
      if (proceedBtn.disabled || !currentCar) return;

      var start        = pickupFP.selectedDates[0];
      var end          = dropoffFP.selectedDates[0];
      var customerType = modal.querySelector('input[name="obsidian_customer_type"]:checked');

      var params = new URLSearchParams({
         car_id:        currentCar.id,
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

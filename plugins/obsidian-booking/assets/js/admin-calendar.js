(function ($) {
	'use strict';

	function initCalendar() {
		var calendarEl = document.getElementById('obsidian-booking-calendar');
		if (!calendarEl || !window.FullCalendar || !window.obsidianBookingCalendar) {
			return;
		}

		var statusSelect = document.getElementById('obm-calendar-status');
		var locationSelect = document.getElementById('obm-calendar-location');
		var refreshButton = document.getElementById('obm-calendar-refresh');

		var statusColors = {
			pending_review: '#F0AD4E',
			awaiting_payment: '#E67E22',
			paid: '#3498DB',
			confirmed: '#5CB85C',
			active: '#5BC0DE',
			completed: '#777777',
			denied: '#D9534F',
			cancelled: '#95A5A6'
		};

		var calendar = new FullCalendar.Calendar(calendarEl, {
			height: 'auto',
			initialView: 'dayGridMonth',
			firstDay: 1,
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
			},
			events: function (fetchInfo, successCallback, failureCallback) {
				var params = new URLSearchParams();
				params.append('start', fetchInfo.startStr);
				params.append('end', fetchInfo.endStr);

				if (statusSelect && statusSelect.value) {
					params.append('status', statusSelect.value);
				}
				if (locationSelect && locationSelect.value) {
					params.append('location_id', locationSelect.value);
				}

				fetch(obsidianBookingCalendar.restUrl + 'calendar?' + params.toString(), {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': obsidianBookingCalendar.nonce
					}
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('Calendar request failed');
						}
						return response.json();
					})
					.then(function (data) {
						if (!Array.isArray(data)) {
							throw new Error('Invalid calendar response');
						}
						successCallback(data);
					})
					.catch(function () {
						failureCallback();
					});
			},
			eventDidMount: function (info) {
				var status = info.event.extendedProps.status;
				if (status && statusColors[status]) {
					info.el.style.backgroundColor = statusColors[status];
					info.el.style.borderColor = statusColors[status];
				}

				var tooltip = [];
				if (info.event.extendedProps.booking_reference) {
					tooltip.push(info.event.extendedProps.booking_reference);
				}
				if (info.event.extendedProps.car_name) {
					tooltip.push(info.event.extendedProps.car_name);
				}
				if (info.event.extendedProps.customer_name) {
					tooltip.push(info.event.extendedProps.customer_name);
				}
				if (info.event.extendedProps.location_label) {
					tooltip.push(info.event.extendedProps.location_label);
				}
				if (tooltip.length) {
					info.el.setAttribute('title', tooltip.join(' | '));
				}
			},
			eventClick: function (info) {
				if (info.event.url) {
					window.open(info.event.url, '_blank', 'noopener');
					info.jsEvent.preventDefault();
				}
			}
		});

		calendar.render();

		if (refreshButton) {
			refreshButton.addEventListener('click', function () {
				calendar.refetchEvents();
			});
		}

		if (statusSelect) {
			statusSelect.addEventListener('change', function () {
				calendar.refetchEvents();
			});
		}

		if (locationSelect) {
			locationSelect.addEventListener('change', function () {
				calendar.refetchEvents();
			});
		}
	}

	$(document).ready(initCalendar);
})(jQuery);

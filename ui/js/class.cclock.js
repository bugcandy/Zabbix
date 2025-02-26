/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

jQuery(function($) {
	/**
	 * Create clock element.
	 *
	 * @param int    options['time']				time in seconds
	 * @param int    options['time_zone_string']	time zone string like 'GMT+02:00'
	 * @param int    options['time_zone_offset']	time zone offset in seconds
	 * @param int    options['clock_id']			id of clock wrapper div (used to find time string div)
	 *
	 * @return object
	 */
	if (typeof($.fn.zbx_clock) === 'undefined') {
		$.fn.zbx_clock = function(options) {
			var obj = $(this);

			if (obj.length == 0) {
				return false;
			}

			clock_hands_start();

			return this;

			function clock_hands_start() {
				var time_offset = 0,
					now = new Date();

				if (options.time !== null) {
					time_offset = now.getTime() - options.time * 1000;
				}

				if (options.time_zone_offset !== null) {
					time_offset += (- now.getTimezoneOffset() * 60 - options.time_zone_offset) * 1000;
				}

				clock_hands_rotate(time_offset);

				var refreshId = setInterval(function() {
					clock_hands_rotate(time_offset);

					// stop execution, if element was removed from DOM
					if(!jQuery.contains(document.documentElement, obj[0])) {
						clearInterval(refreshId);
					}
				}, 1000);
			}

			function clock_hands_rotate(time_offset) {
				var now = new Date();

				if (time_offset != 0) {
					now.setTime(now.getTime() - time_offset);
				}

				if (options.clock_id !== null) {
					var header = now.toTimeString().replace(/.*(\d{2}:\d{2}:\d{2}).*/, "$1");

					if (options.time_zone_string !== null) {
						header = header + ' ' + options.time_zone_string;
					}

					$('.time-zone-'+options.clock_id).text(header);
				}

				var h = now.getHours() % 12,
					m = now.getMinutes(),
					s = now.getSeconds();

				clock_hand_rotate($('.clock-hand-h', obj), 30 * (h + m / 60 + s / 3600));
				clock_hand_rotate($('.clock-hand-m', obj), 6 * (m + s / 60));
				clock_hand_rotate($('.clock-hand-s', obj), 6 * s);
			}

			function clock_hand_rotate(clock_hand, degree) {
				$(clock_hand).attr('transform', 'rotate(' + degree + ' 50 50)');
			}
		};
	}
});

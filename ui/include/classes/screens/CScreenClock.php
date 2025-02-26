<?php
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


class CScreenClock extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$time = null;
		$title = null;
		$time_zone_string = null;
		$time_zone_offset = null;
		$error = null;

		switch ($this->screenitem['style']) {
			case TIME_TYPE_HOST:
				$itemid = $this->screenitem['resourceid'];

				if (!empty($this->hostid)) {
					$new_itemid = get_same_item_for_host($itemid, $this->hostid);
					$itemid = !empty($new_itemid) ? $new_itemid : '';
				}

				$items = API::Item()->get([
					'output' => ['itemid', 'value_type'],
					'selectHosts' => ['name'],
					'itemids' => [$itemid]
				]);

				if ($items) {
					$item = $items[0];
					$title = $item['hosts'][0]['name'];
					unset($items, $item['hosts']);

					$last_value = Manager::History()->getLastValues([$item]);

					if ($last_value) {
						$last_value = $last_value[$item['itemid']][0];

						try {
							$now = new DateTime($last_value['value']);

							$time_zone_string = _s('GMT%1$s', $now->format('P'));
							$time_zone_offset = $now->format('Z');

							$time = time() - ($last_value['clock'] - $now->getTimestamp());
						}
						catch (Exception $e) {
							$error = _('No data');
						}
					}
					else {
						$error = _('No data');
					}
				}
				else {
					$error = _('No data');
				}
				break;

			case TIME_TYPE_SERVER:
				$title = _('Server');

				$now = new DateTime();
				$time = $now->getTimestamp();
				$time_zone_string = _s('GMT%1$s', $now->format('P'));
				$time_zone_offset = $now->format('Z');
				break;

			default:
				$title = _('Local');
				break;
		}

		if ($this->screenitem['width'] > $this->screenitem['height']) {
			$this->screenitem['width'] = $this->screenitem['height'];
		}

		$clock = (new CClock())
			->setWidth($this->screenitem['width'])
			->setHeight($this->screenitem['height'])
			->setTimeZoneString($time_zone_string);

		if ($error !== null) {
			$clock->setError($error);
		}

		if ($time !== null) {
			$clock->setTime($time);
		}

		if ($time_zone_offset !== null) {
			$clock->setTimeZoneOffset($time_zone_offset);
		}

		if ($error === null) {
			if (!defined('ZBX_CLOCK')) {
				define('ZBX_CLOCK', 1);
				insert_js(file_get_contents($clock->getScriptFile()));
			}
			zbx_add_post_js($clock->getScriptRun());
		}

		$item = [];
		$item[] = $clock->getTimeDiv()
			->addClass(ZBX_STYLE_TIME_ZONE);
		$item[] = $clock;
		$item[] = (new CDiv($title))
			->addClass(ZBX_STYLE_LOCAL_CLOCK)
			->addClass(ZBX_STYLE_GREY);

		return $this->getOutput($item);
	}
}

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


abstract class CAbstractScreenImporter extends CImporter {

	/**
	 * Prepare screen data for import.
	 * Each screen element has reference to resource it represents, reference structure may differ depending on type.
	 * Referenced database objects ids are stored to 'resourceid' field of screen items.
	 *
	 * @todo: api requests probably should be done in CReferencer class
	 * @throws Exception if referenced object is not found in database
	 *
	 * @param array $screen
	 *
	 * @return array
	 */
	protected function resolveScreenReferences(array $screen) {
		if (!empty($screen['screenitems'])) {
			foreach ($screen['screenitems'] as &$screenItem) {
				$resource = $screenItem['resource'];
				if (empty($resource)) {
					$screenItem['resourceid'] = 0;
					continue;
				}
				if ($screenItem['rowspan'] == 0) {
					$screenItem['rowspan'] = 1;
				}
				if ($screenItem['colspan'] == 0) {
					$screenItem['colspan'] = 1;
				}
				if (!isset($screenItem['max_columns'])) {
					$screenItem['max_columns'] = SCREEN_SURROGATE_MAX_COLUMNS_DEFAULT;
				}
				switch ($screenItem['resourcetype']) {
					case SCREEN_RESOURCE_HOST_INFO:
					case SCREEN_RESOURCE_TRIGGER_INFO:
					case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$screenItem['resourceid'] = $this->referencer->resolveGroup($resource['name']);
						if (!$screenItem['resourceid']) {
							throw new Exception(_s('Cannot find group "%1$s" used in screen "%2$s".',
								$resource['name'], $screen['name']));
						}
						break;

					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$screenItem['resourceid'] = $this->referencer->resolveHost($resource['host']);
						if (!$screenItem['resourceid']) {
							throw new Exception(_s('Cannot find host "%1$s" used in screen "%2$s".',
								$resource['host'], $screen['name']));
						}
						break;

					case SCREEN_RESOURCE_GRAPH:
					case SCREEN_RESOURCE_LLD_GRAPH:
						$hostId = $this->referencer->resolveHostOrTemplate($resource['host']);
						$graphId = $this->referencer->resolveGraph($hostId, $resource['name']);

						if (!$graphId) {
							throw new Exception(_s('Cannot find graph "%1$s" used in screen "%2$s".',
								$resource['name'], $screen['name']));
						}

						$screenItem['resourceid'] = $graphId;
						break;

					case SCREEN_RESOURCE_CLOCK:
						if ($screenItem['style'] != TIME_TYPE_HOST) {
							break;
						}
						// break; is not missing here

					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$hostId = $this->referencer->resolveHostOrTemplate($resource['host']);
						$screenItem['resourceid'] = $this->referencer->resolveItem($hostId, $resource['key']);
						if (!$screenItem['resourceid']) {
							throw new Exception(_s('Cannot find item "%1$s" used in screen "%2$s".',
									$resource['host'].':'.$resource['key'], $screen['name']));
						}
						break;

					case SCREEN_RESOURCE_MAP:
						$screenItem['resourceid'] = $this->referencer->resolveMap($resource['name']);
						if (!$screenItem['resourceid']) {
							throw new Exception(_s('Cannot find map "%1$s" used in screen "%2$s".',
								$resource['name'], $screen['name']));
						}
						break;

					default:
						$screenItem['resourceid'] = 0;
						break;
				}
			}
			unset($screenItem);
		}

		return $screen;
	}
}

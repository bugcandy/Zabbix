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


/**
 * Class containing methods for operations with value maps.
 */
class CValueMap extends CApiService {

	protected $tableName = 'valuemaps';
	protected $tableAlias = 'vm';
	protected $sortColumns = ['valuemapid', 'name'];

	/**
	 * Get value maps.
	 *
	 * @param array  $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'valuemapids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'valuemapid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['valuemapid', 'name']), 'default' => API_OUTPUT_EXTEND],
			'selectMappings' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['value', 'newvalue']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['editable'] && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return $options['countOutput'] ? 0 : [];
		}

		$db_valuemaps = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_valuemaps[$row['valuemapid']] = $row;
		}

		if ($db_valuemaps) {
			$db_valuemaps = $this->addRelatedObjects($options, $db_valuemaps);
			$db_valuemaps = $this->unsetExtraFields($db_valuemaps, ['valuemapid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_valuemaps = zbx_cleanHashes($db_valuemaps);
			}
		}

		return $db_valuemaps;
	}

	/**
	 * @param array  $valuemaps
	 *
	 * @return array
	 */
	public function create(array $valuemaps) {
		$this->validateCreate($valuemaps);

		$valuemapids = DB::insertBatch('valuemaps', $valuemaps);

		$mappings = [];

		foreach ($valuemaps as $index => &$valuemap) {
			$valuemap['valuemapid'] = $valuemapids[$index];

			foreach ($valuemap['mappings'] as $mapping) {
				$mappings[] = [
					'valuemapid' => $valuemap['valuemapid'],
					'value' => $mapping['value'],
					'newvalue' => $mapping['newvalue']
				];
			}
		}
		unset($valuemap);

		DB::insertBatch('mappings', $mappings);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_VALUE_MAP, $valuemaps);

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * @param array $valuemap
	 *
	 * @return array
	 */
	public function update(array $valuemaps) {
		$this->validateUpdate($valuemaps, $db_valuemaps);

		$upd_valuemaps = [];
		$mappings = [];

		foreach ($valuemaps as $valuemap) {
			$valuemapid = $valuemap['valuemapid'];

			$db_valuemap = $db_valuemaps[$valuemapid];

			if (array_key_exists('name', $valuemap) && $valuemap['name'] !== $db_valuemap['name']) {
				$upd_valuemaps[] = [
					'values' => ['name' => $valuemap['name']],
					'where' => ['valuemapid' => $valuemap['valuemapid']]
				];
			}

			if (array_key_exists('mappings', $valuemap)) {
				$mappings[$valuemapid] = [];
				foreach ($valuemap['mappings'] as $mapping) {
					$mappings[$valuemapid][$mapping['value']] = $mapping['newvalue'];
				}
			}
		}

		if ($upd_valuemaps) {
			DB::update('valuemaps', $upd_valuemaps);
		}

		if ($mappings) {
			$db_mappings = DB::select('mappings', [
				'output' => ['mappingid', 'valuemapid', 'value', 'newvalue'],
				'filter' => ['valuemapid' => array_keys($mappings)]
			]);

			$ins_mapings = [];
			$upd_mapings = [];
			$del_mapingids = [];

			foreach ($db_mappings as $db_mapping) {
				$mapping = &$mappings[$db_mapping['valuemapid']];

				if (array_key_exists($db_mapping['value'], $mapping)) {
					if ($mapping[$db_mapping['value']] !== $db_mapping['newvalue']) {
						$upd_mapings[] = [
							'values' => ['newvalue' => $mapping[$db_mapping['value']]],
							'where' => ['mappingid' => $db_mapping['mappingid']]
						];
					}
					unset($mapping[$db_mapping['value']]);
				}
				else {
					$del_mapingids[] = $db_mapping['mappingid'];
				}
			}
			unset($mapping);

			foreach ($mappings as $valuemapid => $mapping) {
				foreach ($mapping as $value => $newvalue) {
					$ins_mapings[] = ['valuemapid' => $valuemapid, 'value' => $value, 'newvalue' => $newvalue];
				}
			}

			if ($del_mapingids) {
				DB::delete('mappings', ['mappingid' => $del_mapingids]);
			}

			if ($upd_mapings) {
				DB::update('mappings', $upd_mapings);
			}

			if ($ins_mapings) {
				DB::insertBatch('mappings', $ins_mapings);
			}
		}

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_VALUE_MAP, $valuemaps, $db_valuemaps);

		return ['valuemapids' => zbx_objectValues($valuemaps, 'valuemapid')];
	}

	/**
	 * @param array $valuemapids
	 *
	 * @return array
	 */
	public function delete(array $valuemapids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can delete value maps.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $valuemapids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_valuemaps = DB::select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => $valuemapids,
			'preservekeys' => true
		]);

		foreach ($valuemapids as $valuemapid) {
			if (!array_key_exists($valuemapid, $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$result = DB::update('items', [[
			'values' => ['valuemapid' => 0],
			'where' => ['valuemapid' => $valuemapids]
		]]);

		if ($result) {
			$this->deleteByIds($valuemapids);
		}

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP, $db_valuemaps);

		return ['valuemapids' => $valuemapids];
	}

	/**
	 * Check for duplicated value maps.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if value map already exists.
	 */
	private function checkDuplicates(array $names) {
		$db_valuemaps = DB::select('valuemaps', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_valuemaps) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Value map "%1$s" already exists.', $db_valuemaps[0]['name'])
			);
		}
	}

	/**
	 * @param array $valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$valuemaps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can create value maps.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemaps', 'name')],
			'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['value']], 'fields' => [
				'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('mappings', 'value')],
				'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('mappings', 'newvalue')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($valuemaps, 'name'));
	}

	/**
	 * @param array $valuemaps
	 * @param array $db_valuemaps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$valuemaps, array &$db_valuemaps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can update value maps.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
			'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemaps', 'name')],
			'mappings' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['value']], 'fields' => [
				'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('mappings', 'value')],
				'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('mappings', 'newvalue')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check value map names.
		$db_valuemaps = DB::select('valuemaps', [
			'output' => ['valuemapid', 'name'],
			'valuemapids' => zbx_objectValues($valuemaps, 'valuemapid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($valuemaps as $valuemap) {
			// Check if this value map exists.
			if (!array_key_exists($valuemap['valuemapid'], $db_valuemaps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_valuemap = $db_valuemaps[$valuemap['valuemapid']];

			if (array_key_exists('name', $valuemap) && $valuemap['name'] !== $db_valuemap['name']) {
				$names[] = $valuemap['name'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}
	}

	protected function addRelatedObjects(array $options, array $db_valuemaps) {
		$db_valuemaps = parent::addRelatedObjects($options, $db_valuemaps);

		// Select mappings for value map.
		if ($options['selectMappings'] !== null) {
			$def_mappings = ($options['selectMappings'] == API_OUTPUT_COUNT) ? '0' : [];

			foreach ($db_valuemaps as $valuemapid => $db_valuemap) {
				$db_valuemaps[$valuemapid]['mappings'] = $def_mappings;
			}

			if ($options['selectMappings'] == API_OUTPUT_COUNT) {
				$db_mappings = DBselect(
					'SELECT m.valuemapid,COUNT(*) AS cnt'.
					' FROM mappings m'.
					' WHERE '.dbConditionInt('m.valuemapid', array_keys($db_valuemaps)).
					' GROUP BY m.valuemapid'
				);

				while ($db_mapping = DBfetch($db_mappings)) {
					$db_valuemaps[$db_mapping['valuemapid']]['mappings'] = $db_mapping['cnt'];
				}
			}
			else {
				$db_mappings = API::getApiService()->select('mappings', [
					'output' => $this->outputExtend($options['selectMappings'], ['valuemapid']),
					'filter' => ['valuemapid' => array_keys($db_valuemaps)]
				]);

				foreach ($db_mappings as $db_mapping) {
					$valuemapid = $db_mapping['valuemapid'];
					unset($db_mapping['mappingid'], $db_mapping['valuemapid']);

					$db_valuemaps[$valuemapid]['mappings'][] = $db_mapping;
				}
			}
		}

		return $db_valuemaps;
	}
}

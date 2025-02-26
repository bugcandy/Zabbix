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
 * Class containing methods for operations with graphs.
 */
abstract class CGraphGeneral extends CApiService {

	const ERROR_TEMPLATE_HOST_MIX = 'templateHostMix';
	const ERROR_MISSING_GRAPH_NAME = 'missingGraphName';
	const ERROR_MISSING_GRAPH_ITEMS = 'missingGraphItems';
	const ERROR_MISSING_REQUIRED_VALUE = 'missingRequiredValue';
	const ERROR_GRAPH_SUM = 'graphSum';

	/**
	 * Update graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function update(array $graphs) {
		$graphs = zbx_toArray($graphs);
		$graphIds = zbx_objectValues($graphs, 'graphid');

		$graphs = $this->extendObjects($this->tableName(), $graphs,
			['name', 'graphtype', 'ymin_type', 'ymin_itemid', 'ymax_type', 'ymax_itemid', 'yaxismin', 'yaxismax']
		);

		$dbGraphs = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'graphids' => $graphIds,
			'editable' => true,
			'preservekeys' => true
		]);

		$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
			'messageAllowed' => _('Cannot update a discovered graph.')
		]);

		foreach ($graphs as $key => &$graph) {
			// check permissions
			if (!isset($dbGraphs[$graph['graphid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			// Cannot update templated graph.
			if ($dbGraphs[$graph['graphid']]['templateid'] != 0
					&& $dbGraphs[$graph['graphid']]['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update a templated graph "%1$s".',
					$graph['name']
				));
			}

			// cannot update discovered graphs
			$this->checkPartialValidator($graph, $updateDiscoveredValidator, $dbGraphs[$graph['graphid']]);

			// Allow for template inherited graphs to update discover parameter.
			if ($dbGraphs[$graph['graphid']]['templateid'] != 0) {
				if ($dbGraphs[$graph['graphid']]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
						&& array_key_exists('discover', $graph)) {
					$graph = ['discover' => $graph['discover']] + $dbGraphs[$graph['graphid']];
					unset($graph['templateid'], $graph['flags'], $graph['uuid']);
				}
				else {
					unset($graphs[$key]);
				}

				continue;
			}

			// validate items on set or pass existing items from DB
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as $item) {
					if (isset($item['gitemid']) && !$item['gitemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "gitemid" field for item.'));
					}

					if (isset($item['gitemid']) && $item['gitemid']) {
						$validGraphItemIds = [];

						foreach ($dbGraphs[$graph['graphid']]['gitems'] as $dbItem) {
							$validGraphItemIds[$dbItem['gitemid']] = $dbItem['gitemid'];
						}

						if (!in_array($item['gitemid'], $validGraphItemIds)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No permissions to referred object or it does not exist!')
							);
						}
					}
				}
			}
			else {
				$graph['gitems'] = $dbGraphs[$graph['graphid']]['gitems'];
			}
		}
		unset($graph);

		if ($graphs) {
			$this->validateUpdate($graphs, $dbGraphs);
		}

		foreach ($graphs as &$graph) {
			unset($graph['templateid']);

			$graph['gitems'] = isset($graph['gitems']) ? $graph['gitems'] : $dbGraphs[$graph['graphid']]['gitems'];

			// Y axis min clean unused fields
			if (isset($graph['ymin_type'])) {
				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['yaxismin'] = null;
				}
				else {
					$graph['ymin_itemid'] = null;
				}
			}

			// Y axis max clean unused fields
			if (isset($graph['ymax_type'])) {
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['yaxismax'] = null;
				}
				else {
					$graph['ymax_itemid'] = null;
				}
			}

			$this->updateReal($graph, $dbGraphs[$graph['graphid']]);
			$this->inherit($graph);
		}

		return ['graphids' => $graphIds];
	}

	/**
	 * Create graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	public function create(array $graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = [];

		// set default parameters
		foreach ($graphs as &$graph) {
			if (!isset($graph['graphtype'])) {
				$graph['graphtype'] = GRAPH_TYPE_NORMAL;
			}
			if (!isset($graph['ymin_type'])) {
				$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
			}
			if (!isset($graph['ymax_type'])) {
				$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
			}
		}
		unset($graph);

		$this->validateCreate($graphs);

		foreach ($graphs as $graph) {
			$graph['graphid'] = $this->createReal($graph);

			$this->inherit($graph);

			$graphids[] = $graph['graphid'];
		}

		return ['graphids' => $graphids];
	}

	/**
	 * Creates a new graph and returns it's ID.
	 *
	 * @param $graph
	 *
	 * @return mixed
	 */
	protected function createReal($graph) {
		$graphids = DB::insert('graphs', [$graph]);
		$graphid = reset($graphids);
		$sort_order = 0;

		foreach ($graph['gitems'] as &$gitem) {
			$gitem['graphid'] = $graphid;

			if (!array_key_exists('sortorder', $gitem)) {
				$gitem['sortorder'] = $sort_order;
			}

			$sort_order++;
		}
		unset($gitem);

		DB::insert('graphs_items', $graph['gitems']);

		return $graphid;
	}

	/**
	 * Updates the graph if $graph differs from $dbGraph.
	 *
	 * @param array $graph
	 * @param array $dbGraph
	 *
	 * @return string
	 */
	protected function updateReal(array $graph, array $dbGraph) {
		$dbGitems = zbx_toHash($dbGraph['gitems'], 'gitemid');
		$dbGitemIds = zbx_toHash(zbx_objectValues($dbGitems, 'gitemid'));

		// update the graph if it's modified
		if (DB::recordModified('graphs', $dbGraph, $graph)) {
			DB::updateByPk($this->tableName(), $graph['graphid'], $graph);
		}

		// delete remaining items only if new items or items that require update are set
		if ($graph['gitems']) {
			$insertGitems = [];
			$deleteGitemIds = $dbGitemIds;
			$sort_order = 0;

			foreach ($graph['gitems'] as $gitem) {
				// updating an existing item
				if (!empty($gitem['gitemid']) && isset($dbGitemIds[$gitem['gitemid']])) {
					if (DB::recordModified('graphs_items', $dbGitems[$gitem['gitemid']], $gitem)) {
						DB::updateByPk('graphs_items', $gitem['gitemid'], $gitem);
					}

					// remove this graph item from the collection so it won't get deleted
					unset($deleteGitemIds[$gitem['gitemid']]);
				}
				// adding a new item
				else {
					$gitem['graphid'] = $graph['graphid'];

					if (!array_key_exists('sortorder', $gitem)) {
						$gitem['sortorder'] = $sort_order;
					}

					$sort_order++;

					$insertGitems[] = $gitem;
				}
			}

			if ($deleteGitemIds) {
				DB::delete('graphs_items', ['gitemid' => $deleteGitemIds]);
			}

			if ($insertGitems) {
				DB::insert('graphs_items', $insertGitems);
			}
		}

		return $graph['graphid'];
	}

	/**
	 * Check values for Y axis items and values.
	 *
	 * @param array $graph
	 * @param bool  $tpl
	 * @param array $db_graph
	 */
	protected function checkAxisItems(array $graph, $tpl = false, array $db_graph = null) {
		$axisItems = [];
		if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			if ($db_graph === null || $graph['ymin_itemid'] != $db_graph['ymin_itemid']) {
				$axisItems[$graph['ymin_itemid']] = 1;
			}
		}
		if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			if ($db_graph === null || $graph['ymax_itemid'] != $db_graph['ymax_itemid']) {
				$axisItems[$graph['ymax_itemid']] = 1;
			}
		}

		if (count($axisItems)) {
			$options = [
				'output' => ['itemid'],
				'filter' => ['flags' => null, 'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]],
				'itemids' => array_keys($axisItems),
				'webitems' => true,
				'countOutput' => true
			];

			if ($tpl) {
				$options['hostids'] = $tpl;
			}
			else {
				$options['templated'] = false;
			}

			$cntExist = API::Item()->get($options);

			if ($cntExist != count($axisItems)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect item for axis value.'));
			}
		}

		// more than one sum type item for pie graph
		if ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED) {
			$sumItems = 0;
			foreach ($graph['gitems'] as $gitem) {
				if (array_key_exists('type', $gitem) && $gitem['type'] == GRAPH_ITEM_SUM) {
					$sumItems++;
				}
			}
			if ($sumItems > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_GRAPH_SUM), $graph['name'])
				);
			}
		}

		// Y axis MIN value < Y axis MAX value
		if (($graph['graphtype'] == GRAPH_TYPE_NORMAL || $graph['graphtype'] == GRAPH_TYPE_STACKED)
			&& $graph['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED
			&& $graph['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED
			&& $graph['yaxismin'] >= $graph['yaxismax']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Y axis MAX value must be greater than Y axis MIN value.'));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding GraphItems
		if ($options['selectGraphItems'] !== null && $options['selectGraphItems'] !== API_OUTPUT_COUNT) {
			$gitems = API::GraphItem()->get([
				'output' => $this->outputExtend($options['selectGraphItems'], ['graphid', 'gitemid']),
				'graphids' => $graphids,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($gitems, 'graphid', 'gitemid');

			$gitems = $this->unsetExtraFields($gitems, ['graphid', 'gitemid'], $options['selectGraphItems']);
			$result = $relationMap->mapMany($result, $gitems, 'gitems');
		}

		// adding HostGroups
		if ($options['selectGroups'] !== null && $options['selectGroups'] !== API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,hg.groupid'.
				' FROM graphs_items gi,items i,hosts_groups hg'.
				' WHERE '.dbConditionInt('gi.graphid', $graphids).
				' AND gi.itemid=i.itemid'.
				' AND i.hostid=hg.hostid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['groupid']);
			}

			$groups = API::HostGroup()->get([
				'output' => $options['selectGroups'],
				'groupids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// adding Hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT) {
			$hosts = [];
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,i.hostid'.
				' FROM graphs_items gi,items i'.
				' WHERE '.dbConditionInt('gi.graphid', $graphids).
				' AND gi.itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['hostid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = API::Host()->get([
					'output' => $options['selectHosts'],
					'hostids' => $related_ids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding Templates
		if ($options['selectTemplates'] !== null && $options['selectTemplates'] !== API_OUTPUT_COUNT) {
			$templates = [];
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT gi.graphid,i.hostid'.
				' FROM graphs_items gi,items i'.
				' WHERE '.dbConditionInt('gi.graphid', $graphids).
				' AND gi.itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['hostid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$templates = API::Template()->get([
					'output' => $options['selectTemplates'],
					'templateids' => $related_ids,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $templates, 'templates');
		}

		return $result;
	}

	/**
	 * Validate graph name and graph items including Y axis item ID's and graph item fields on Create method
	 * and return valid item ID's on success or throw an error on failure.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function validateItemsCreate(array $graphs) {
		$itemIds = [];
		$itemid_rules = ['type' => API_ID];

		foreach ($graphs as $graph) {
			// validate graph name
			$fields = ['name' => null];
			if (!check_db_fields($fields, $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _($this->getErrorMsg(self::ERROR_MISSING_GRAPH_NAME)));
			}

			// graph items are mandatory
			if (!isset($graph['gitems']) || !is_array($graph['gitems']) || !$graph['gitems']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_MISSING_GRAPH_ITEMS), $graph['name'])
				);
			}

			// validate item fields
			if (isset($graph['gitems'])) {
				$fields = ['itemid' => null];
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required
					if (!check_db_fields($fields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y min axis item ID for permission validation
			if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymin_itemid'], 'ymin_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				else {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}

			// add Y max axis item ID for permission validation
			if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymax_itemid'], 'ymax_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				else {
					$itemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
				}
			}
		}

		return $itemIds;
	}

	/**
	 * Validate graph gerenal data on Create method.
	 * Check if new items are from same templated host, validate Y axis items and values and hosts and templates.
	 *
	 * @param array $graphs
	 */
	protected function validateCreate(array $graphs) {
		$colorValidator = new CColorValidator();

		switch (get_class($this)) {
			case 'CGraph':
				$error_cannot_set = _('Cannot set "%1$s" for graph "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('graphs', 'name')]
				]];
				break;

			case 'CGraphPrototype':
				$error_cannot_set = _('Cannot set "%1$s" for graph prototype "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('graphs', 'name')],
					'discover' =>	['type' => API_INT32, 'in' => implode(',', [GRAPH_DISCOVER, GRAPH_NO_DISCOVER])]
				]];
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$read_only_fields = ['templateid', 'flags'];

		foreach ($graphs as $key => $graph) {
			$this->checkNoParameters($graph, $read_only_fields, $error_cannot_set, $graph['name']);

			$data = array_intersect_key($graph, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $data, '/'.($key + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$templatedGraph = false;

			if (isset($graph['gitems'])) {
				// check if new items are from same templated host
				$graphHosts = API::Host()->get([
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => ['hostid', 'status'],
					'editable' => true,
					'templated_hosts' => true
				]);

				// check - items from one template. at least one item belongs to template
				foreach ($graphHosts as $host) {
					if (HOST_STATUS_TEMPLATE == $host['status']) {
						$templatedGraph = $host['hostid'];
						break;
					}
				}

				if ($templatedGraph && count($graphHosts) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name'])
					);
				}

				// check color
				foreach ($graph['gitems'] as $gitem) {
					if (!isset($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s($this->getErrorMsg(self::ERROR_MISSING_REQUIRED_VALUE), 'color')
						);
					}

					if (!$colorValidator->validate($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
					}
				}
			}

			// check graph type and ymin/ymax items
			$this->checkAxisItems($graph, $templatedGraph);
		}

		$this->validateHostsAndTemplates($graphs);
	}

	/**
	 * Validate graph items including valid Y axis item ID's on Update method
	 * and return valid item ID's on success or throw an error on failure.
	 *
	 * @param array $graphs
	 * @param array $db_graphs
	 *
	 * @return array
	 */
	protected function validateItemsUpdate(array $graphs, array $db_graphs) {
		$dbFields = ['itemid' => null];
		$itemid_rules = ['type' => API_ID];

		foreach ($graphs as $graph) {
			// graph items are optional
			if (isset($graph['gitems']) && (!is_array($graph['gitems']) || !$graph['gitems'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s($this->getErrorMsg(self::ERROR_MISSING_GRAPH_ITEMS), $graph['name'])
				);
			}

			// validate item fields
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as $gitem) {
					// "itemid" is required only if no "gitemid" is set
					if (!isset($gitem['gitemid']) && !check_db_fields($dbFields, $gitem)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing "itemid" field for item.'));
					}

					// assigning with key preserves unique itemids
					$itemIds[$gitem['itemid']] = $gitem['itemid'];
				}
			}

			// add Y min axis item ID for permission validation
			if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymin_itemid'], 'ymin_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				// Skip itemid if ymin_itemid stay the same.
				elseif ($graph['ymin_itemid'] != $db_graphs[$graph['graphid']]['ymin_itemid']) {
					$itemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
				}
			}

			// add Y max axis item ID for permission validation
			if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				if (!CApiInputValidator::validate($itemid_rules, $graph['ymax_itemid'], 'ymax_itemid', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
				// Skip itemid if ymax_itemid stay the same.
				elseif ($graph['ymax_itemid'] != $db_graphs[$graph['graphid']]['ymax_itemid']) {
					$itemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
				}
			}
		}

		return $itemIds;
	}

	/**
	 * Validate graph general data on Update method.
	 * When updating graph check to what host graph belongs to and throw an error if new items added from other hosts.
	 * Includes Y axis validation and if graph already exists somewhere in DB.
	 *
	 * @param array $graphs
	 * @param array $dbGraphs
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		$colorValidator = new CColorValidator();

		switch (get_class($this)) {
			case 'CGraph':
				$error_cannot_update = _('Cannot update "%1$s" for graph "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'name' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('graphs', 'name')]
				]];
				break;

			case 'CGraphPrototype':
				$error_cannot_update = _('Cannot update "%1$s" for graph prototype "%2$s".');
				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('graphs', 'name')],
					'discover' => 	['type' => API_INT32, 'in' => implode(',', [GRAPH_DISCOVER, GRAPH_NO_DISCOVER])]
				]];
				break;

			default:
				self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$read_only_fields = ['templateid', 'flags'];

		foreach ($graphs as $key => $graph) {
			$this->checkNoParameters($graph, $read_only_fields, $error_cannot_update, $graph['name']);

			$data = array_intersect_key($graph, $api_input_rules['fields']);

			if (!CApiInputValidator::validate($api_input_rules, $data, '/'.($key + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$templatedGraph = false;

			if (isset($graph['gitems'])) {
				// first item determines to which host graph belongs to
				$gitem = array_shift($dbGraphs[$graph['graphid']]['gitems']);

				$graphHosts = API::Host()->get([
					'itemids' => $gitem['itemid'],
					'output' => ['hostid', 'status'],
					'editable' => true,
					'templated_hosts' => true
				]);

				$host = array_shift($graphHosts);

				// if the current graph is templated and new items to be added
				if (HOST_STATUS_TEMPLATE == $host['status']) {
					$templatedGraph = $host['hostid'];

					$itemIds = [];

					foreach ($graph['gitems'] as $gitem) {
						if (!isset($gitem['gitemid']) && isset($gitem['itemid'])) {
							$itemIds[] = $gitem['itemid'];
						}
					}

					if ($itemIds) {
						$itemHosts = API::Host()->get([
							'itemids' => $itemIds,
							'output' => ['hostid'],
							'editable' => true,
							'templated_hosts' => true
						]);

						// only one host is allowed and it has to be the current. other templated hosts are allowed
						$itemHosts = array_unique(zbx_objectValues($itemHosts, 'hostid'));

						if (count($itemHosts) > 1 || !in_array($host['hostid'], $itemHosts)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s($this->getErrorMsg(self::ERROR_TEMPLATE_HOST_MIX), $graph['name'])
							);
						}
					}
				}

				// items fields
				foreach ($graph['gitems'] as $gitem) {
					// check color
					if (isset($gitem['color']) && !$colorValidator->validate($gitem['color'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
					}
				}
			}

			// check ymin, ymax items
			$this->checkAxisItems($graph, $templatedGraph, $dbGraphs[$graph['graphid']]);
		}

		$this->validateHostsAndTemplates($graphs);
	}

	/**
	 * Check if graph already exists somewhere in DB.
	 *
	 * @param array $graphs
	 */
	protected function validateHostsAndTemplates(array $graphs) {
		$graphNames = [];

		foreach ($graphs as $graph) {
			// check if the host has any graphs in DB with the same name within host
			$hostsAndTemplates = API::Host()->get([
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => ['hostid'],
				'nopermissions' => true,
				'preservekeys' => true,
				'templated_hosts' => true
			]);

			$hostAndTemplateIds = array_keys($hostsAndTemplates);

			$dbGraphs = API::Graph()->get([
				'hostids' => $hostAndTemplateIds,
				'output' => ['graphid'],
				'filter' => ['name' => $graph['name'], 'flags' => null], // 'flags' => null overrides default behaviour
				'nopermissions' => true
			]);

			if ($dbGraphs) {
				$duplicateGraphsFound = false;

				if (isset($graph['graphid'])) {
					foreach ($dbGraphs as $dbGraph) {
						if (bccomp($dbGraph['graphid'], $graph['graphid']) != 0) {
							$duplicateGraphsFound = true;
							break;
						}
					}
				}
				else {
					$duplicateGraphsFound = true;
				}

				if ($duplicateGraphsFound) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph with name "%1$s" already exists in graphs or graph prototypes.', $graph['name'])
					);
				}
			}

			// checks that there are no two graphs with the same name within host
			foreach ($hostAndTemplateIds as $id) {
				if (!isset($graphNames[$graph['name']])) {
					$graphNames[$graph['name']] = [];
				}

				if (isset($graphNames[$graph['name']][$id])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('More than one graph with name "%1$s" within host.', $graph['name'])
					);
				}
				else {
					$graphNames[$graph['name']][$id] = true;
				}
			}
		}
	}
}

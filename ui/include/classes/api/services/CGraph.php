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
 * Class containing methods for operations with graph.
 */
class CGraph extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = ['graphid', 'name', 'graphtype'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_TEMPLATE_HOST_MIX =>
				_('Graph "%1$s" with templated items cannot contain items from other hosts.'),
			self::ERROR_MISSING_GRAPH_NAME => _('Missing "name" field for graph.'),
			self::ERROR_MISSING_GRAPH_ITEMS => _('Missing items for graph "%1$s".'),
			self::ERROR_MISSING_REQUIRED_VALUE => _('No "%1$s" given for graph.'),
			self::ERROR_GRAPH_SUM => _('Cannot add more than one item with type "Graph sum" on graph "%1$s".')
		]);
	}

	/**
	 * Get graph data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['graphs' => 'g.graphid'],
			'from'		=> ['graphs' => 'graphs g'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'templated'					=> null,
			'inherited'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectTemplates'			=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectGraphItems'			=> null,
			'selectDiscoveryRule'		=> null,
			'selectGraphDiscovery'		=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			// check permissions by graph items
			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM graphs_items gi,items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE g.graphid=gi.graphid'.
					' AND gi.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY i.hostid'.
				' HAVING MAX(permission)<'.zbx_dbstr($permission).
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
				')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['where'][] = dbConditionInt('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);

			if ($options['groupCount']) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['ggi'] = 'g.graphid=gi.graphid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'g.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'g.templateid IS NULL';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('graphs g', $options, $sqlParts);
		}

		// filter
		if (is_null($options['filter'])) {
			$options['filter'] = [];
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED];
			}

			$this->dbFilter('graphs g', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($graph = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $graph;
				}
				else {
					$result = $graph['rowscount'];
				}
			}
			else {
				// Graphs share table with graph prototypes. Therefore remove graph unrelated fields.
				unset($graph['discover']);

				$result[$graph['graphid']] = $graph;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if (isset($options['expandName'])) {
			$result = CMacrosResolverHelper::resolveGraphNameByIds($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function inherit($graph, $hostids = null) {
		$graphTemplates = API::Template()->get([
			'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
			'output' => ['templateid'],
			'nopermissions' => true
		]);

		if (empty($graphTemplates)) {
			return true;
		}

		$graphTemplate = reset($graphTemplates);

		$chdHosts = API::Host()->get([
			'templateids' => $graphTemplate['templateid'],
			'output' => ['hostid', 'host'],
			'preservekeys' => true,
			'hostids' => $hostids,
			'nopermissions' => true,
			'templated_hosts' => true
		]);

		$graph = $this->get([
			'graphids' => $graph['graphid'],
			'nopermissions' => true,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		]);
		$graph = reset($graph);

		foreach ($chdHosts as $chdHost) {
			$tmpGraph = $graph;
			$tmpGraph['templateid'] = $graph['graphid'];

			$tmpGraph['gitems'] = getSameGraphItemsForHost($tmpGraph['gitems'], $chdHost['hostid']);
			if (!$tmpGraph['gitems']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s".', $tmpGraph['name'], $chdHost['host']));
			}

			if ($tmpGraph['ymax_itemid'] > 0) {
				$ymaxItemid = getSameGraphItemsForHost([['itemid' => $tmpGraph['ymax_itemid']]], $chdHost['hostid']);
				if (!$ymaxItemid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s" (Ymax value item).', $tmpGraph['name'], $chdHost['host']));
				}
				$ymaxItemid = reset($ymaxItemid);
				$tmpGraph['ymax_itemid'] = $ymaxItemid['itemid'];
			}
			if ($tmpGraph['ymin_itemid'] > 0) {
				$yminItemid = getSameGraphItemsForHost([['itemid' => $tmpGraph['ymin_itemid']]], $chdHost['hostid']);
				if (!$yminItemid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s" (Ymin value item).', $tmpGraph['name'], $chdHost['host']));
				}
				$yminItemid = reset($yminItemid);
				$tmpGraph['ymin_itemid'] = $yminItemid['itemid'];
			}

			// check if templated graph exists
			$chdGraphs = $this->get([
				'filter' => ['templateid' => $tmpGraph['graphid'], 'flags' => [ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_NORMAL]],
				'output' => API_OUTPUT_EXTEND,
				'selectGraphItems' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'hostids' => $chdHost['hostid'],
				'nopermissions' => true
			]);

			if ($chdGraph = reset($chdGraphs)) {
				if ($tmpGraph['name'] !== $chdGraph['name']) {
					$graphExists = $this->get([
						'output' => ['graphid'],
						'hostids' => $chdHost['hostid'],
						'filter' => [
							'name' => $tmpGraph['name'],
							'flags' => null
						],
						'nopermissions' => true,
						'limit' => 1
					]);
					if ($graphExists) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Graph "%1$s" already exists on "%2$s".',
							$tmpGraph['name'],
							$chdHost['host']
						));
					}
				}
				elseif ($chdGraph['flags'] != $tmpGraph['flags']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist.'));
				}

				$tmpGraph['graphid'] = $chdGraph['graphid'];
				$this->updateReal($tmpGraph, $chdGraph);
			}
			// check if graph with same name and items exists
			else {
				$chdGraph = $this->get([
					'filter' => ['name' => $tmpGraph['name'], 'flags' => null],
					'output' => API_OUTPUT_EXTEND,
					'selectGraphItems' => API_OUTPUT_EXTEND,
					'preservekeys' => true,
					'nopermissions' => true,
					'hostids' => $chdHost['hostid']
				]);
				if ($chdGraph = reset($chdGraph)) {
					if ($chdGraph['templateid'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (inherited from another template).', $tmpGraph['name'], $chdHost['host']));
					}
					elseif ($chdGraph['flags'] != $tmpGraph['flags']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist.'));
					}

					$chdGraphItemItems = API::Item()->get([
						'output' => ['itemid', 'key_', 'hostid'],
						'itemids' => zbx_objectValues($chdGraph['gitems'], 'itemid'),
						'preservekeys' => true
					]);

					if (count($chdGraph['gitems']) == count($tmpGraph['gitems'])) {
						foreach ($tmpGraph['gitems'] as $gitem) {
							foreach ($chdGraph['gitems'] as $chdGraphItem) {
								$chdGraphItemItem = $chdGraphItemItems[$chdGraphItem['itemid']];
								if ($gitem['key_'] == $chdGraphItemItem['key_']
										&& bccomp($chdHost['hostid'], $chdGraphItemItem['hostid']) == 0) {
									continue 2;
								}
							}
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $tmpGraph['name'], $chdHost['host']));
						}

						$tmpGraph['graphid'] = $chdGraph['graphid'];
						$this->updateReal($tmpGraph, $chdGraph);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $tmpGraph['name'], $chdHost['host']));
					}
				}
				else {
					$graphid = $this->createReal($tmpGraph);
					$tmpGraph['graphid'] = $graphid;
				}
			}
			$this->inherit($tmpGraph);
		}
	}

	/**
	 * Inherit template graphs from template to host.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$dbLinks = DBSelect(
			'SELECT ht.hostid,ht.templateid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionInt('ht.hostid', $data['hostids']).
				' AND '.dbConditionInt('ht.templateid', $data['templateids'])
		);
		$linkage = [];
		while ($link = DBfetch($dbLinks)) {
			if (!isset($linkage[$link['templateid']])) {
				$linkage[$link['templateid']] = [];
			}
			$linkage[$link['templateid']][$link['hostid']] = 1;
		}

		$graphs = $this->get([
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid']
		]);

		foreach ($graphs as $graph) {
			foreach ($data['hostids'] as $hostid) {
				if (isset($linkage[$graph['hosts'][0]['hostid']][$hostid])) {
					$this->inherit($graph, $hostid);
				}
			}
		}

		return true;
	}

	/**
	 * Delete graphs.
	 *
	 * @param array $graphids
	 *
	 * @return array
	 */
	public function delete(array $graphids) {
		$this->validateDelete($graphids, $db_graphs);

		CGraphManager::delete($graphids);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_GRAPH, $db_graphs);

		return ['graphids' => $graphids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $graphids   [IN/OUT]
	 * @param array $db_graphs  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$graphids, array &$db_graphs = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $graphids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_graphs = $this->get([
			'output' => ['graphid', 'name', 'templateid'],
			'graphids' => $graphids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($graphids as $graphid) {
			if (!array_key_exists($graphid, $db_graphs)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			if ($db_graphs[$graphid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated graph.'));
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding Items
		if ($options['selectItems'] !== null && $options['selectItems'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'graphid', 'itemid', 'graphs_items');
			$items = API::Item()->get([
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discoveryRule
		if ($options['selectDiscoveryRule'] !== null) {
			$discoveryRules = [];
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT id.parent_itemid,gd.graphid'.
					' FROM graph_discovery gd,item_discovery id,graphs_items gi,items i'.
					' WHERE '.dbConditionInt('gd.graphid', $graphids).
					' AND gd.parent_graphid=gi.graphid'.
						' AND gi.itemid=id.itemid'.
						' AND id.parent_itemid=i.itemid'.
						' AND i.flags='.ZBX_FLAG_DISCOVERY_RULE
			);

			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['parent_itemid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$discoveryRules = API::DiscoveryRule()->get([
					'output' => $options['selectDiscoveryRule'],
					'itemids' => $related_ids,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding graph discovery
		if ($options['selectGraphDiscovery'] !== null) {
			$graphDiscoveries = API::getApiService()->select('graph_discovery', [
				'output' => $this->outputExtend($options['selectGraphDiscovery'], ['graphid']),
				'filter' => ['graphid' => array_keys($result)],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($graphDiscoveries, 'graphid', 'graphid');

			$graphDiscoveries = $this->unsetExtraFields($graphDiscoveries, ['graphid'],
				$options['selectGraphDiscovery']
			);
			$result = $relationMap->mapOne($result, $graphDiscoveries, 'graphDiscovery');
		}

		return $result;
	}

	/**
	 * Validate create.
	 *
	 * @param array $graphs
	 */
	protected function validateCreate(array $graphs) {
		$itemIds = $this->validateItemsCreate($graphs);
		$this->validateItems($itemIds, $graphs);

		parent::validateCreate($graphs);
	}

	/**
	 * Validate update.
	 *
	 * @param array $graphs
	 * @param array $dbGraphs
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		// check for "itemid" when updating graph with only "gitemid" passed
		foreach ($graphs as &$graph) {
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as &$gitem) {
					if (isset($gitem['gitemid']) && !isset($gitem['itemid'])) {
						$dbGitems = zbx_toHash($dbGraphs[$graph['graphid']]['gitems'], 'gitemid');
						$gitem['itemid'] = $dbGitems[$gitem['gitemid']]['itemid'];
					}
				}
				unset($gitem);
			}
		}
		unset($graph);

		$itemIds = $this->validateItemsUpdate($graphs, $dbGraphs);
		$this->validateItems($itemIds, $graphs);

		parent::validateUpdate($graphs, $dbGraphs);
	}

	/**
	 * Validates items.
	 *
	 * @param array $itemIds
	 * @param array $graphs
	 */
	protected function validateItems(array $itemIds, array $graphs) {
		$dbItems = API::Item()->get([
			'output' => ['name', 'value_type'],
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'preservekeys' => true
		]);

		// check if items exist and user has permission to access those items
		foreach ($itemIds as $itemId) {
			if (!isset($dbItems[$itemId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$allowedValueTypes = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];

		// get value type and name for these items
		foreach ($graphs as $graph) {
			// graph items
			foreach ($graph['gitems'] as $graphItem) {
				$item = $dbItems[$graphItem['itemid']];

				if (!in_array($item['value_type'], $allowedValueTypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
						$item['name'],
						$graph['name']
					));
				}
			}

			// Y axis min
			if (array_key_exists('ymin_type', $graph) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE
					&& array_key_exists('ymin_itemid', $graph) && $graph['ymin_itemid'] != 0) {
				if (array_key_exists($graph['ymin_itemid'], $dbItems)) {
					$item = $dbItems[$graph['ymin_itemid']];

					if (!in_array($item['value_type'], $allowedValueTypes)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
							$item['name'],
							$graph['name']
						));
					}
				}
			}

			// Y axis max
			if (array_key_exists('ymax_type', $graph) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE
					&& array_key_exists('ymax_itemid', $graph) && $graph['ymax_itemid'] != 0) {
				if (array_key_exists($graph['ymax_itemid'], $dbItems)) {
					$item = $dbItems[$graph['ymax_itemid']];

					if (!in_array($item['value_type'], $allowedValueTypes)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
							$item['name'],
							$graph['name']
						));
					}
				}
			}
		}
	}
}

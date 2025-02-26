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
 * Class containing methods for operations with template.
 */
class CTemplate extends CHostGeneral {

	protected $sortColumns = ['hostid', 'host', 'name'];

	/**
	 * Overrides the parent function so that templateids will be used instead of hostids for the template API.
	 */
	public function pkOption($tableName = null) {
		if ($tableName && $tableName != $this->tableName()) {
			return parent::pkOption($tableName);
		}
		else {
			return 'templateids';
		}
	}

	/**
	 * Get template data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['templates' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['h.status='.HOST_STATUS_TEMPLATE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'parentTemplateids'			=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'with_items'				=> null,
			'with_triggers'				=> null,
			'with_graphs'				=> null,
			'with_httptests'			=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'evaltype'					=> TAG_EVAL_TYPE_AND_OR,
			'tags'						=> null,
			'filter'					=> null,
			'search'					=> '',
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'selectParentTemplates'		=> null,
			'selectItems'				=> null,
			'selectDiscoveries'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectMacros'				=> null,
			'selectScreens'				=> null,
			'selectHttpTests'			=> null,
			'selectTags'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE h.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['where']['templateid'] = dbConditionInt('h.hostid', $options['templateids']);
		}

		// parentTemplateids
		if (!is_null($options['parentTemplateids'])) {
			zbx_value2array($options['parentTemplateids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['parentTemplateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.hostid', $options['hostids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.templateid';

			if ($options['groupCount']) {
				$sqlParts['group']['ht'] = 'ht.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// with_items
		if (!is_null($options['with_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i'.
				' WHERE h.hostid=i.hostid'.
					' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,functions f,triggers t'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=f.itemid'.
					' AND f.triggerid=t.triggerid'.
					' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,graphs_items gi,graphs g'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=gi.itemid'.
					' AND gi.graphid=g.graphid'.
					' AND g.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
				')';
		}

		// with_httptests
		if (!empty($options['with_httptests'])) {
			$sqlParts['where'][] = 'EXISTS (SELECT ht.httptestid FROM httptest ht WHERE ht.hostid=h.hostid)';
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'h',
				'host_tag', 'hostid'
			);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

		$upcased_index = array_search($this->tableAlias().'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($template = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $template;
				}
				else {
					$result = $template['rowscount'];
				}
			}
			else {
				$template['templateid'] = $template['hostid'];
				// Templates share table with hosts and host prototypes. Therefore remove template unrelated fields.
				unset($template['hostid'], $template['discover']);

				$result[$template['templateid']] = $template;
			}

		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['name_upper'], []);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add template.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	public function create(array $templates) {
		$templates = zbx_toArray($templates);

		$this->validateCreate($templates);

		$ins_templates = [];

		foreach ($templates as &$template) {
			// If visible name is not given or empty it should be set to host name.
			if (!array_key_exists('name', $template) || trim($template['name']) === '') {
				$template['name'] = $template['host'];
			}

			$ins_templates[] = array_intersect_key($template, array_flip(['host', 'name', 'description'])) +
				['status' => HOST_STATUS_TEMPLATE];
		}
		unset($template);

		$hosts_groups = [];
		$hosts_tags = [];
		$hosts_macros = [];
		$templates_hostids = [];
		$hostids = [];

		$templateids = DB::insert('hosts', $ins_templates);

		foreach ($templates as $index => &$template) {
			$template['templateid'] = $templateids[$index];

			foreach (zbx_toArray($template['groups']) as $group) {
				$hosts_groups[] = [
					'hostid' => $template['templateid'],
					'groupid' => $group['groupid']
				];
			}

			if (array_key_exists('tags', $template)) {
				foreach (zbx_toArray($template['tags']) as $tag) {
					$hosts_tags[] = ['hostid' => $template['templateid']] + $tag;
				}
			}

			if (array_key_exists('macros', $template)) {
				foreach (zbx_toArray($template['macros']) as $macro) {
					$hosts_macros[] = ['hostid' => $template['templateid']] + $macro;
				}
			}

			if (array_key_exists('templates', $template)) {
				foreach (zbx_toArray($template['templates']) as $link_template) {
					$templates_hostids[$link_template['templateid']][] = $template['templateid'];
				}
			}

			if (array_key_exists('hosts', $template)) {
				foreach (zbx_toArray($template['hosts']) as $host) {
					$templates_hostids[$template['templateid']][] = $host['hostid'];
					$hostids[$host['hostid']] = true;
				}
			}
		}
		unset($template);

		DB::insertBatch('hosts_groups', $hosts_groups);

		if ($hosts_tags) {
			DB::insert('host_tag', $hosts_tags);
		}

		if ($hosts_macros) {
			API::UserMacro()->create($hosts_macros);
		}

		if ($hostids) {
			$this->checkHostPermissions(array_keys($hostids));
		}

		while ($templates_hostids) {
			$templateid = key($templates_hostids);
			$link_hostids = reset($templates_hostids);
			$link_templateids = [$templateid];
			unset($templates_hostids[$templateid]);

			foreach ($templates_hostids as $templateid => $hostids) {
				if ($link_hostids === $hostids) {
					$link_templateids[] = $templateid;
					unset($templates_hostids[$templateid]);
				}
			}

			$this->link($link_templateids, $link_hostids);
		}

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TEMPLATE, $templates);

		return ['templateids' => array_column($templates, 'templateid')];
	}

	/**
	 * Validate create template.
	 *
	 * @param array $templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $templates) {
		$groupIds = [];

		foreach ($templates as &$template) {
			// check if hosts have at least 1 group
			if (!isset($template['groups']) || !$template['groups']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template "%1$s" cannot be without host group.', $template['host'])
				);
			}

			$template['groups'] = zbx_toArray($template['groups']);

			foreach ($template['groups'] as $group) {
				if (!is_array($group) || (is_array($group) && !array_key_exists('groupid', $group))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'groups',
							_s('the parameter "%1$s" is missing', 'groupid')
						)
					);
				}

				$groupIds[$group['groupid']] = $group['groupid'];
			}
		}
		unset($template);

		$dbHostGroups = API::HostGroup()->get([
			'output' => ['groupid'],
			'groupids' => $groupIds,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groupIds as $groupId) {
			if (!isset($dbHostGroups[$groupId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$templateDbFields = ['host' => null];

		$host_name_parser = new CHostNameParser();

		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			if (!check_db_fields($templateDbFields, $template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Field "host" is mandatory.'));
			}

			// Property 'auto_compress' is not supported for templates.
			if (array_key_exists('auto_compress', $template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			if ($host_name_parser->parse($template['host']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect characters used for template name "%1$s".', $template['host'])
				);
			}

			if (isset($template['host'])) {
				$templateExists = API::Template()->get([
					'output' => ['templateid'],
					'filter' => ['host' => $template['host']],
					'nopermissions' => true,
					'limit' => 1
				]);
				if ($templateExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $template['host']));
				}

				$hostExists = API::Host()->get([
					'output' => ['hostid'],
					'filter' => ['host' => $template['host']],
					'nopermissions' => true,
					'limit' => 1
				]);
				if ($hostExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $template['host']));
				}
			}

			if (isset($template['name'])) {
				$templateExists = API::Template()->get([
					'output' => ['templateid'],
					'filter' => ['name' => $template['name']],
					'nopermissions' => true,
					'limit' => 1
				]);
				if ($templateExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Template with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}

				$hostExists = API::Host()->get([
					'output' => ['hostid'],
					'filter' => ['name' => $template['name']],
					'nopermissions' => true,
					'limit' => 1
				]);
				if ($hostExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Host with the same visible name "%1$s" already exists.',
						$template['name']
					));
				}
			}

			// Validate tags.
			if (array_key_exists('tags', $template)) {
				$this->validateTags($template);
			}
		}
	}

	/**
	 * Update template.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	public function update(array $templates) {
		$templates = zbx_toArray($templates);

		$this->validateUpdate($templates);

		$macros = [];
		foreach ($templates as &$template) {
			if (isset($template['macros'])) {
				$macros[$template['templateid']] = zbx_toArray($template['macros']);

				unset($template['macros']);
			}

			if (array_key_exists('tags', $template)) {
				$template['tags'] = zbx_toArray($template['tags']);
			}
		}
		unset($template);

		if ($macros) {
			API::UserMacro()->replaceMacros($macros);
		}

		foreach ($templates as $template) {
			// if visible name is not given or empty it should be set to host name
			if ((!isset($template['name']) || zbx_empty(trim($template['name']))) && isset($template['host'])) {
				$template['name'] = $template['host'];
			}

			$templateCopy = $template;

			$template['templates_link'] = array_key_exists('templates', $template) ? $template['templates'] : null;

			unset($template['templates'], $template['templateid'], $templateCopy['templates']);
			$template['templates'] = [$templateCopy];

			if (!$this->massUpdate($template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Failed to update template.'));
			}
		}

		$this->updateTags($templates, 'templateid');

		return ['templateids' => zbx_objectValues($templates, 'templateid')];
	}

	/**
	 * Validate update template.
	 *
	 * @param array $templates
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $templates) {
		$dbTemplates = $this->get([
			'output' => ['templateid'],
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($templates as $template) {
			if (!isset($dbTemplates[$template['templateid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}

			// Property 'auto_compress' is not supported for templates.
			if (array_key_exists('auto_compress', $template)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			// Validate tags.
			if (array_key_exists('tags', $template)) {
				$this->validateTags($template);
			}
		}
	}

	/**
	 * Delete template.
	 *
	 * @param array $templateids
	 * @param array $templateids['templateids']
	 *
	 * @return array
	 */
	public function delete(array $templateids) {
		if (empty($templateids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_templates = $this->get([
			'output' => ['templateid', 'name'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (array_diff_key(array_flip($templateids), $db_templates)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		API::Template()->unlink($templateids, null, true);

		// delete the discovery rules first
		$del_rules = API::DiscoveryRule()->get([
			'output' => [],
			'hostids' => $templateids,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($del_rules) {
			CDiscoveryRuleManager::delete(array_keys($del_rules));
		}

		// delete the items
		$del_items = API::Item()->get([
			'output' => [],
			'templateids' => $templateids,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($del_items) {
			CItemManager::delete(array_keys($del_items));
		}

		// delete host from maps
		if (!empty($templateids)) {
			DB::delete('sysmaps_elements', ['elementtype' => SYSMAP_ELEMENT_TYPE_HOST, 'elementid' => $templateids]);
		}

		// disable actions
		// actions from conditions
		$actionids = [];
		$sql = 'SELECT DISTINCT actionid'.
			' FROM conditions'.
			' WHERE conditiontype='.CONDITION_TYPE_TEMPLATE.
			' AND '.dbConditionString('value', $templateids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
			' FROM operations o,optemplate ot'.
			' WHERE o.operationid=ot.operationid'.
			' AND '.dbConditionInt('ot.templateid', $templateids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionids]
			]);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_TEMPLATE,
			'value' => $templateids
		]);

		// delete action operation commands
		$operationids = [];
		$sql = 'SELECT DISTINCT ot.operationid'.
			' FROM optemplate ot'.
			' WHERE '.dbConditionInt('ot.templateid', $templateids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('optemplate', [
			'templateid'=>$templateids
		]);

		// delete empty operations
		$delOperationids = [];
		$sql = 'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
			' AND NOT EXISTS(SELECT NULL FROM optemplate ot WHERE ot.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', [
			'operationid'=>$delOperationids
		]);

		// http tests
		$delHttpTests = API::HttpTest()->get([
			'templateids' => $templateids,
			'output' => ['httptestid'],
			'nopermissions' => 1,
			'preservekeys' => true
		]);
		if (!empty($delHttpTests)) {
			API::HttpTest()->delete(array_keys($delHttpTests), true);
		}

		// Applications
		$delApplications = API::Application()->get([
			'templateids' => $templateids,
			'output' => ['applicationid'],
			'nopermissions' => 1,
			'preservekeys' => true
		]);
		if (!empty($delApplications)) {
			API::Application()->delete(array_keys($delApplications), true);
		}

		// Get host prototype operations from LLD overrides where this template is linked.
		$lld_override_operationids = [];

		$db_lld_override_operationids = DBselect(
			'SELECT loo.lld_override_operationid'.
			' FROM lld_override_operation loo'.
			' WHERE EXISTS('.
				'SELECT NULL'.
				' FROM lld_override_optemplate lot'.
				' WHERE lot.lld_override_operationid=loo.lld_override_operationid'.
				' AND '.dbConditionId('lot.templateid', $templateids).
			')'
		);
		while ($db_lld_override_operationid = DBfetch($db_lld_override_operationids)) {
			$lld_override_operationids[] = $db_lld_override_operationid['lld_override_operationid'];
		}

		if ($lld_override_operationids) {
			DB::delete('lld_override_optemplate', ['templateid' => $templateids]);

			// Make sure there no other operations left to safely delete the operation.
			$delete_lld_override_operationids = [];

			$db_delete_lld_override_operationids = DBselect(
				'SELECT loo.lld_override_operationid'.
				' FROM lld_override_operation loo'.
				' WHERE NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opstatus los'.
						' WHERE los.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opdiscover lod'.
						' WHERE lod.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_opinventory loi'.
						' WHERE loi.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM lld_override_optemplate lot'.
						' WHERE lot.lld_override_operationid=loo.lld_override_operationid'.
					')'.
					' AND '.dbConditionId('loo.lld_override_operationid', $lld_override_operationids)
			);

			while ($db_delete_lld_override_operationid = DBfetch($db_delete_lld_override_operationids)) {
				$delete_lld_override_operationids[] = $db_delete_lld_override_operationid['lld_override_operationid'];
			}

			if ($delete_lld_override_operationids) {
				DB::delete('lld_override_operation', ['lld_override_operationid' => $delete_lld_override_operationids]);
			}
		}

		// Finally delete the template.
		DB::delete('hosts', ['hostid' => $templateids]);

		// TODO: remove info from API
		foreach ($db_templates as $db_template) {
			info(_s('Deleted: Template "%1$s".', $db_template['name']));
		}

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TEMPLATE, $db_templates);

		return ['templateids' => $templateids];
	}

	/**
	 * Additionally allows to link templates to hosts and other templates.
	 *
	 * Checks write permissions for templates.
	 *
	 * Additional supported $data parameters are:
	 * - hosts  - an array of hosts or templates to link the given templates to
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : [];
		$templateIds = zbx_objectValues($templates, 'templateid');

		$this->checkPermissions($templateIds, _('No permissions to referred object or it does not exist!'));

		// link hosts to the given templates
		if (isset($data['hosts']) && !empty($data['hosts'])) {
			$hostIds = zbx_objectValues($data['hosts'], 'hostid');

			$this->checkHostPermissions($hostIds);

			// check if any of the hosts are discovered
			$this->checkValidator($hostIds, new CHostNormalValidator([
				'message' => _('Cannot update templates on discovered host "%1$s".')
			]));

			$this->link($templateIds, $hostIds);
		}

		$data['hosts'] = [];

		return parent::massAdd($data);
	}

	/**
	 * Mass update.
	 *
	 * @param string $data['host']
	 * @param string $data['name']
	 * @param string $data['description']
	 * @param array  $data['templates']
	 * @param array  $data['templates_clear']
	 * @param array  $data['templates_link']
	 * @param array  $data['groups']
	 * @param array  $data['hosts']
	 * @param array  $data['macros']
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		if (!array_key_exists('templates', $data) || !is_array($data['templates'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'templates'));
		}

		$this->validateMassUpdate($data);

		$templates = zbx_toArray($data['templates']);
		$templateIds = zbx_objectValues($templates, 'templateid');

		$fieldsToUpdate = [];

		if (isset($data['host'])) {
			$fieldsToUpdate[] = 'host='.zbx_dbstr($data['host']);
		}

		if (isset($data['name'])) {
			// if visible name is empty replace it with host name
			if (zbx_empty(trim($data['name'])) && isset($data['host'])) {
				$fieldsToUpdate[] = 'name='.zbx_dbstr($data['host']);
			}
			// we cannot have empty visible name
			elseif (zbx_empty(trim($data['name'])) && !isset($data['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot have empty visible template name.'));
			}
			else {
				$fieldsToUpdate[] = 'name='.zbx_dbstr($data['name']);
			}
		}

		if (isset($data['description'])) {
			$fieldsToUpdate[] = 'description='.zbx_dbstr($data['description']);
		}

		if ($fieldsToUpdate) {
			DBexecute('UPDATE hosts SET '.implode(', ', $fieldsToUpdate).' WHERE '.dbConditionInt('hostid', $templateIds));
		}

		$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : [];
		$templateIdsClear = zbx_objectValues($data['templates_clear'], 'templateid');

		if ($data['templates_clear']) {
			$this->massRemove([
				'templateids' => $templateIds,
				'templateids_clear' => $templateIdsClear
			]);
		}

		// update template linkage
		// firstly need to unlink all things, to correctly check circulars
		if (isset($data['hosts']) && $data['hosts'] !== null) {
			/*
			 * Get all currently linked hosts and templates (skip discovered hosts) to these templates
			 * that user has read permissions.
			 */
			$templateHosts = API::Host()->get([
				'output' => ['hostid'],
				'templateids' => $templateIds,
				'templated_hosts' => true,
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
			]);
			$templateHostIds = zbx_objectValues($templateHosts, 'hostid');
			$newHostIds = zbx_objectValues($data['hosts'], 'hostid');

			$hostsToDelete = array_diff($templateHostIds, $newHostIds);
			$hostIdsToDelete = array_diff($hostsToDelete, $templateIdsClear);
			$hostIdsToAdd = array_diff($newHostIds, $templateHostIds);

			if ($hostIdsToDelete) {
				$result = $this->massRemove([
					'hostids' => $hostIdsToDelete,
					'templateids' => $templateIds
				]);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template.'));
				}
			}
		}

		if (isset($data['templates_link']) && $data['templates_link'] !== null) {
			$templateTemplates = API::Template()->get([
				'output' => ['templateid'],
				'hostids' => $templateIds
			]);
			$templateTemplateIds = zbx_objectValues($templateTemplates, 'templateid');
			$newTemplateIds = zbx_objectValues($data['templates_link'], 'templateid');

			$templatesToDelete = array_diff($templateTemplateIds, $newTemplateIds);
			$templateIdsToDelete = array_diff($templatesToDelete, $templateIdsClear);

			if ($templateIdsToDelete) {
				$result = $this->massRemove([
					'templateids' => $templateIds,
					'templateids_link' => $templateIdsToDelete
				]);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template.'));
				}
			}
		}

		if (isset($data['hosts']) && $data['hosts'] !== null && $hostIdsToAdd) {
			$result = $this->massAdd([
				'templates' => $templates,
				'hosts' => $hostIdsToAdd
			]);

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template.'));
			}
		}

		if (isset($data['templates_link']) && $data['templates_link'] !== null) {
			$templatesToAdd = array_diff($newTemplateIds, $templateTemplateIds);

			if ($templatesToAdd) {
				$result = $this->massAdd([
					'templates' => $templates,
					'templates_link' => $templatesToAdd
				]);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template.'));
				}
			}
		}

		// macros
		if (isset($data['macros'])) {
			DB::delete('hostmacro', ['hostid' => $templateIds]);

			$this->massAdd([
				'templates' => $templates,
				'macros' => $data['macros']
			]);
		}

		/*
		 * Update template and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read premissions. Thus other procedures, like
		 * host-template linking, macros update, must be done before this.
		 */
		if (isset($data['groups']) && $data['groups'] !== null && is_array($data['groups'])) {
			$updateGroups = zbx_toArray($data['groups']);

			$templateGroups = API::HostGroup()->get([
				'output' => ['groupid'],
				'templateids' => $templateIds
			]);
			$templateGroupIds = zbx_objectValues($templateGroups, 'groupid');
			$newGroupIds = zbx_objectValues($updateGroups, 'groupid');

			$groupsToAdd = array_diff($newGroupIds, $templateGroupIds);
			if ($groupsToAdd) {
				$this->massAdd([
					'templates' => $templates,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				]);
			}

			$groupIdsToDelete = array_diff($templateGroupIds, $newGroupIds);
			if ($groupIdsToDelete) {
				$this->massRemove([
					'templateids' => $templateIds,
					'groupids' => $groupIdsToDelete
				]);
			}
		}

		return ['templateids' => $templateIds];
	}

	/**
	 * Validate mass update.
	 *
	 * @param string $data['host']
	 * @param string $data['name']
	 * @param array  $data['templates']
	 * @param array  $data['groups']
	 * @param array  $data['hosts']
	 *
	 * @return array
	 */
	protected function validateMassUpdate(array $data) {
		$templates = zbx_toArray($data['templates']);

		$dbTemplates = $this->get([
			'output' => ['templateid', 'host'],
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		// check permissions
		foreach ($templates as $template) {
			if (!isset($dbTemplates[$template['templateid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		if (array_key_exists('groups', $data) && !$data['groups'] && $dbTemplates) {
			$template = reset($dbTemplates);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Template "%1$s" cannot be without host group.', $template['host'])
			);
		}

		// check name
		if (isset($data['name'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible template name.'));
			}

			$template = reset($templates);

			$templateExists = $this->get([
				'output' => ['templateid'],
				'filter' => ['name' => $data['name']],
				'nopermissions' => true
			]);
			$templateExist = reset($templateExists);
			if ($templateExist && bccomp($templateExist['templateid'], $template['templateid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same visible name "%1$s" already exists.',
					$data['name']
				));
			}

			// can't set the same name as existing host
			$hostExists = API::Host()->get([
				'output' => ['hostid'],
				'filter' => ['name' => $data['name']],
				'nopermissions' => true
			]);
			if ($hostExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same visible name "%1$s" already exists.',
					$data['name']
				));
			}
		}

		// check host
		if (isset($data['host'])) {
			if (count($templates) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update template name.'));
			}

			$template = reset($templates);

			$templateExists = $this->get([
				'output' => ['templateid'],
				'filter' => ['host' => $data['host']],
				'nopermissions' => true
			]);
			$templateExist = reset($templateExists);
			if ($templateExist && bccomp($templateExist['templateid'], $template['templateid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Template with the same name "%1$s" already exists.',
					$template['host']
				));
			}

			// can't set the same name as existing host
			$hostExists = API::Host()->get([
				'output' => ['hostid'],
				'filter' => ['host' => $template['host']],
				'nopermissions' => true
			]);
			if ($hostExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Host with the same name "%1$s" already exists.',
					$template['host']
				));
			}
		}

		$host_name_parser = new CHostNameParser();

		if (array_key_exists('host', $data) && $host_name_parser->parse($data['host']) != CParser::PARSE_SUCCESS) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect characters used for template name "%1$s".', $data['host'])
			);
		}
	}

	/**
	 * Additionally allows to unlink templates from hosts and other templates.
	 *
	 * Checks write permissions for templates.
	 *
	 * Additional supported $data parameters are:
	 * - hostids  - an array of host or template IDs to unlink the given templates from
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$templateids = zbx_toArray($data['templateids']);

		$this->checkPermissions($templateids, _('You do not have permission to perform this operation.'));

		if (isset($data['hostids'])) {
			// check if any of the hosts are discovered
			$this->checkValidator($data['hostids'], new CHostNormalValidator([
				'message' => _('Cannot update templates on discovered host "%1$s".')
			]));

			API::Template()->unlink($templateids, zbx_toArray($data['hostids']));
		}

		$data['hostids'] = [];

		return parent::massRemove($data);
	}

	/**
	 * Check if user has write permissions for templates.
	 *
	 * @param array  $templateids
	 * @param string $error
	 *
	 * @return bool
	 */
	private function checkPermissions(array $templateids, $error) {
		if ($templateids) {
			$templateids = array_unique($templateids);

			$count = $this->get([
				'countOutput' => true,
				'templateids' => $templateids,
				'editable' => true
			]);

			if ($count != count($templateids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$templateids = array_keys($result);

		// Adding Templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$templates = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$templates = API::Template()->get([
						'output' => $options['selectTemplates'],
						'templateids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($templates, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $templates, 'templates', $options['limitSelects']);
			}
			else {
				$templates = API::Template()->get([
					'parentTemplateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['templates'] = array_key_exists($templateid, $templates)
						? $templates[$templateid]['rowscount']
						: '0';
				}
			}
		}

		// Adding Hosts
		if ($options['selectHosts'] !== null) {
			if ($options['selectHosts'] != API_OUTPUT_COUNT) {
				$hosts = [];
				$relationMap = $this->createRelationMap($result, 'templateid', 'hostid', 'hosts_templates');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$hosts = API::Host()->get([
						'output' => $options['selectHosts'],
						'hostids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($hosts, 'host');
					}
				}

				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hosts = zbx_toHash($hosts, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['hosts'] = array_key_exists($templateid, $hosts)
						? $hosts[$templateid]['rowscount']
						: '0';
				}
			}
		}

		// Adding screens
		if ($options['selectScreens'] !== null) {
			if ($options['selectScreens'] != API_OUTPUT_COUNT) {
				$screens = API::TemplateScreen()->get([
					'output' => $this->outputExtend($options['selectScreens'], ['templateid']),
					'templateids' => $templateids,
					'nopermissions' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($screens, 'name');
				}

				// preservekeys is not supported by templatescreen.get, so we're building a map using array keys
				$relationMap = new CRelationMap();
				foreach ($screens as $key => $screen) {
					$relationMap->addRelation($screen['templateid'], $key);
				}

				$screens = $this->unsetExtraFields($screens, ['templateid'], $options['selectScreens']);
				$result = $relationMap->mapMany($result, $screens, 'screens', $options['limitSelects']);
			}
			else {
				$screens = API::TemplateScreen()->get([
					'templateids' => $templateids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$screens = zbx_toHash($screens, 'templateid');
				foreach ($result as $templateid => $template) {
					$result[$templateid]['screens'] = array_key_exists($templateid, $screens)
						? $screens[$templateid]['rowscount']
						: '0';
				}
			}
		}

		return $result;
	}
}

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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of host prototypes');
$page['file'] = 'host_prototypes.php';
$page['scripts'] = ['effects.js', 'class.cviewswitcher.js', 'multiselect.js', 'textareaflexible.js',
	'class.cverticalaccordion.js', 'inputsecret.js', 'macrovalue.js'
];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID, '(isset({form}) && ({form} == "update")) || (isset({action}) && {action} == "hostprototype.updatediscover")'],
	'parent_discoveryid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null],
	'host' =>					[T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({add}) || isset({update})', _('Host name')],
	'name' =>					[T_ZBX_STR, O_OPT, null,		null,		'isset({add}) || isset({update})'],
	'status' =>					[T_ZBX_INT, O_OPT, null,		IN([HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED]), null],
	'discover' =>				[T_ZBX_INT, O_OPT, null, IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'inventory_mode' =>			[T_ZBX_INT, O_OPT, null, IN([HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]), null],
	'templates' =>				[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,	null],
	'add_templates' =>			[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,	null],
	'group_links' =>			[T_ZBX_STR, O_OPT, P_ONLY_ARRAY, NOT_EMPTY,	null],
	'group_prototypes' =>		[T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY, NOT_EMPTY,	null],
	'unlink' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT|P_ONLY_ARRAY,	null,	null],
	'group_hostid' =>			[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'show_inherited_macros' =>	[T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'macros' =>					[null,      O_OPT, P_SYS|P_ONLY_TD_ARRAY,			null,		null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"hostprototype.massdelete","hostprototype.massdisable",'.
										'"hostprototype.massenable","hostprototype.updatediscover"'
									),
									null
								],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status","discover"'),						null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

// permissions
if (getRequest('parent_discoveryid')) {
	$discoveryRule = API::DiscoveryRule()->get([
		'itemids' => getRequest('parent_discoveryid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['flags'],
		'editable' => true
	]);
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule || $discoveryRule['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		access_deny();
	}

	if (getRequest('hostid')) {
		$hostPrototype = API::HostPrototype()->get([
			'hostids' => getRequest('hostid'),
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => ['templateid', 'name'],
			'selectParentHost' => ['hostid'],
			'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description'],
			'editable' => true
		]);
		$hostPrototype = reset($hostPrototype);
		if (!$hostPrototype) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

// Remove inherited macros data (actions: 'add', 'update' and 'form').
$macros = cleanInheritedMacros(getRequest('macros', []));

// Remove empty new macro lines.
$macros = array_filter($macros, function($macro) {
	$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

	return (bool) array_filter(array_intersect_key($macro, $keys));
});

/*
 * Actions
 */
if (getRequest('unlink')) {
	foreach (getRequest('unlink') as $templateId => $value) {
		unset($_REQUEST['templates'][$templateId]);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {
	DBstart();
	$result = API::HostPrototype()->delete([getRequest('hostid')]);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, _('Host prototype deleted'), _('Cannot delete host prototypes'));

	unset($_REQUEST['hostid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	unset($_REQUEST['hostid']);
	if (hasRequest('group_prototypes')) {
		foreach ($_REQUEST['group_prototypes'] as &$groupPrototype) {
			unset($groupPrototype['group_prototypeid']);
		}
		unset($groupPrototype);
	}

	if ($macros && in_array(ZBX_MACRO_TYPE_SECRET, array_column($macros, 'type'))) {
		// Reset macro type and value.
		$macros = array_map(function($value) {
			return ($value['type'] == ZBX_MACRO_TYPE_SECRET)
				? ['value' => '', 'type' => ZBX_MACRO_TYPE_TEXT] + $value
				: $value;
		}, $macros);

		warning(_('The cloned host prototype contains user defined macros with type "Secret text". The value and type of these macros were reset.'));
	}

	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	DBstart();

	$newHostPrototype = [
		'host' => getRequest('host', ''),
		'name' => (getRequest('name', '') === '') ? getRequest('host', '') : getRequest('name', ''),
		'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
		'discover' => getRequest('discover', DB::getDefault('hosts', 'discover')),
		'groupLinks' => [],
		'groupPrototypes' => [],
		'macros' => $macros,
		'templates' => array_merge(getRequest('templates', []), getRequest('add_templates', []))
	];

	if (hasRequest('inventory_mode')) {
		$newHostPrototype['inventory_mode'] = getRequest('inventory_mode');
	}

	// API requires 'templateid' property.
	if ($newHostPrototype['templates']) {
		$newHostPrototype['templates'] = zbx_toObject($newHostPrototype['templates'], 'templateid');
	}

	// add custom group prototypes
	foreach (getRequest('group_prototypes', []) as $groupPrototype) {
		if (!$groupPrototype['group_prototypeid']) {
			unset($groupPrototype['group_prototypeid']);
		}

		if ($groupPrototype['name'] !== '') {
			$newHostPrototype['groupPrototypes'][] = $groupPrototype;
		}
	}

	if (getRequest('hostid')) {
		$newHostPrototype['hostid'] = getRequest('hostid');

		if (!$hostPrototype['templateid']) {
			// add group prototypes based on existing host groups
			$groupPrototypesByGroupId = zbx_toHash($hostPrototype['groupLinks'], 'groupid');
			unset($groupPrototypesByGroupId[0]);
			foreach (getRequest('group_links', []) as $groupId) {
				if (isset($groupPrototypesByGroupId[$groupId])) {
					$newHostPrototype['groupLinks'][] = [
						'groupid' => $groupPrototypesByGroupId[$groupId]['groupid'],
						'group_prototypeid' => $groupPrototypesByGroupId[$groupId]['group_prototypeid']
					];
				}
				else {
					$newHostPrototype['groupLinks'][] = [
						'groupid' => $groupId
					];
				}
			}
		}
		else {
			unset($newHostPrototype['groupPrototypes'], $newHostPrototype['groupLinks']);
		}

		$newHostPrototype = CArrayHelper::unsetEqualValues($newHostPrototype, $hostPrototype, ['hostid']);
		$result = API::HostPrototype()->update($newHostPrototype);

		show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
	}
	else {
		$newHostPrototype['ruleid'] = getRequest('parent_discoveryid');

		// add group prototypes based on existing host groups
		foreach (getRequest('group_links', []) as $groupId) {
			$newHostPrototype['groupLinks'][] = [
				'groupid' => $groupId
			];
		}

		/*
		 * Sanitize macros array. When we clone, we have old hostmacroid.
		 * We need delete them before we push array to API.
		*/
		foreach ($newHostPrototype['macros'] as &$macro) {
			if (array_key_exists('hostmacroid', $macro)) {
				unset($macro['hostmacroid']);
			}
		}
		unset($macro);

		$result = API::HostPrototype()->create($newHostPrototype);

		show_messages($result, _('Host prototype added'), _('Cannot add host prototype'));
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
		unset($_REQUEST['itemid'], $_REQUEST['form']);
	}
}
elseif (getRequest('hostid', '') && getRequest('action', '') === 'hostprototype.updatediscover') {
	$result = API::HostPrototype()->update([
		'hostid' => getRequest('hostid'),
		'discover' => getRequest('discover', DB::getDefault('hosts', 'discover'))
	]);

	show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
}
// GO
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['hostprototype.massenable', 'hostprototype.massdisable']) && hasRequest('group_hostid')) {
	$status = (getRequest('action') == 'hostprototype.massenable') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$update = [];

	DBstart();
	foreach ((array) getRequest('group_hostid') as $hostPrototypeId) {
		$update[] = [
			'hostid' => $hostPrototypeId,
			'status' => $status
		];
	}

	$result = API::HostPrototype()->update($update);
	$result = DBend($result);

	$updated = count($update);

	$messageSuccess = _n('Host prototype updated', 'Host prototypes updated', $updated);
	$messageFailed = _n('Cannot update host prototype', 'Cannot update host prototypes', $updated);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'hostprototype.massdelete' && getRequest('group_hostid')) {
	DBstart();
	$result = API::HostPrototype()->delete(getRequest('group_hostid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows($discoveryRule['itemid']);
	}
	show_messages($result, _('Host prototypes deleted'), _('Cannot delete host prototypes'));
}

if (hasRequest('action') && hasRequest('group_hostid') && !$result) {
	$host_prototypes = API::HostPrototype()->get([
		'output' => [],
		'hostids' => getRequest('group_hostid'),
		'editable' => true
	]);
	uncheckTableRows($discoveryRule['itemid'], zbx_objectValues($host_prototypes, 'hostid'));
}

$config = select_config();

/*
 * Display
 */
if (hasRequest('form')) {
	$data = [
		'form_refresh' => getRequest('form_refresh', 0),
		'discovery_rule' => $discoveryRule,
		'host_prototype' => [
			'hostid' => getRequest('hostid', 0),
			'templateid' => getRequest('hostid') ? $hostPrototype['templateid'] : 0,
			'host' => getRequest('host'),
			'name' => getRequest('name'),
			'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
			'discover' => getRequest('discover', DB::getDefault('hosts', 'discover')),
			'templates' => [],
			'add_templates' => [],
			'inventory_mode' => getRequest('inventory_mode', $config['default_inventory_mode']),
			'groupPrototypes' => getRequest('group_prototypes', []),
			'macros' => $macros
		],
		'show_inherited_macros' => getRequest('show_inherited_macros', 0),
		'readonly' => (getRequest('hostid') && $hostPrototype['templateid']),
		'groups' => [],
		// Parent discovery rules.
		'templates' => []
	];

	// Add already linked and new templates.
	$templates = [];
	$request_templates = getRequest('templates', []);
	$request_add_templates = getRequest('add_templates', []);

	if ($request_templates || $request_add_templates) {
		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => array_merge($request_templates, $request_add_templates),
			'preservekeys' => true
		]);

		$data['host_prototype']['templates'] = array_intersect_key($templates, array_flip($request_templates));
		CArrayHelper::sort($data['host_prototype']['templates'], ['name']);

		$data['host_prototype']['add_templates'] = array_intersect_key($templates, array_flip($request_add_templates));

		foreach ($data['host_prototype']['add_templates'] as &$template) {
			$template = CArrayHelper::renameKeys($template, ['templateid' => 'id']);
		}
		unset($template);
	}

	// add parent host
	$parentHost = API::Host()->get([
		'output' => API_OUTPUT_EXTEND,
		'selectGroups' => ['groupid', 'name'],
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'hostids' => $discoveryRule['hostid'],
		'templated_hosts' => true
	]);
	$parentHost = reset($parentHost);
	$data['parent_host'] = $parentHost;
	$data['parent_hostid'] = $parentHost['hostid'];

	if (getRequest('group_links')) {
		$data['groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => getRequest('group_links'),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	if ($parentHost['proxy_hostid']) {
		$proxy = API::Proxy()->get([
			'output' => ['host', 'proxyid'],
			'proxyids' => $parentHost['proxy_hostid'],
			'limit' => 1
		]);
		$data['proxy'] = reset($proxy);
	}

	if (!hasRequest('form_refresh')) {
		if ($data['host_prototype']['hostid'] != 0) {

			// When opening existing host prototype, display all values from database.
			$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

			$groupids = zbx_objectValues($data['host_prototype']['groupLinks'], 'groupid');
			$data['groups'] = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);

			$n = 0;
			foreach ($groupids as $groupid) {
				if (!array_key_exists($groupid, $data['groups'])) {
					$postfix = (++$n > 1) ? ' ('.$n.')' : '';
					$data['groups'][$groupid] = [
						'groupid' => $groupid,
						'name' => _('Inaccessible group').$postfix,
						'inaccessible' => true
					];
				}
			}
		}
		else {
			// Set default values for new host prototype.
			$data['host_prototype']['status'] = HOST_STATUS_MONITORED;
		}
	}

	$data['templates'] = makeHostPrototypeTemplatesHtml($data['host_prototype']['hostid'],
		getHostPrototypeParentTemplates([$data['host_prototype']])
	);

	// Select writable templates
	$templateids = zbx_objectValues($data['host_prototype']['templates'], 'templateid');
	$data['host_prototype']['writable_templates'] = [];

	if ($templateids) {
		$data['host_prototype']['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$macros = $data['host_prototype']['macros'];

	if ($data['show_inherited_macros']) {
		$macros = mergeInheritedMacros($macros, getInheritedMacros(array_keys($templates), $data['parent_hostid']));
	}

	// Sort only after inherited macros are added. Otherwise the list will look chaotic.
	$data['macros'] = array_values(order_macros($macros, 'macro'));

	if (!$data['macros'] && !$data['readonly']) {
		$macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];

		if ($data['show_inherited_macros']) {
			$macro['inherited_type'] = ZBX_PROPERTY_OWN;
		}

		$data['macros'][] = $macro;
	}

	// This data is used in common.template.edit.js.php.
	$data['macros_tab'] = [
		'linked_templates' => array_map('strval', $templateids),
		'add_templates' => array_map('strval', array_keys($data['host_prototype']['add_templates']))
	];

	// Render view.
	echo (new CView('configuration.host.prototype.edit', $data))->getOutput();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'discovery_rule' => $discoveryRule,
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// get items
	$data['hostPrototypes'] = API::HostPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectTemplates' => ['templateid', 'name'],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	order_result($data['hostPrototypes'], $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['hostPrototypes'], $sortOrder,
		(new CUrl('host_prototypes.php'))->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	);

	$data['parent_templates'] = getHostPrototypeParentTemplates($data['hostPrototypes']);

	// Fetch templates linked to the prototypes.
	$templateids = [];
	foreach ($data['hostPrototypes'] as $hostPrototype) {
		$templateids = array_merge($templateids, zbx_objectValues($hostPrototype['templates'], 'templateid'));
	}
	$templateids = array_keys(array_flip($templateids));

	$linkedTemplates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'selectParentTemplates' => ['templateid', 'name'],
		'templateids' => $templateids
	]);
	$data['linkedTemplates'] = zbx_toHash($linkedTemplates, 'templateid');

	foreach ($data['linkedTemplates'] as $linked_template) {
		foreach ($linked_template['parentTemplates'] as $parent_template) {
			$templateids[] = $parent_template['templateid'];
		}
	}

	// Select writable template IDs.
	$data['writable_templates'] = [];

	if ($templateids) {
		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $templateids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// render view
	echo (new CView('configuration.host.prototype.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

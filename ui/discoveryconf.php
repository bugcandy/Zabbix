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
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';

$page['title'] = _('Configuration of discovery rules');
$page['file'] = 'discoveryconf.php';
$page['type'] = detect_page_type();
$page['scripts'] = ['class.cviewswitcher.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'druleid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})'],
	'proxy_hostid' =>	[T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({add}) || isset({update})'],
	'iprange' =>		[T_ZBX_STR, O_OPT, P_CRLF,	null,		'isset({add}) || isset({update})'],
	'delay' =>			[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null, 'isset({add}) || isset({update})',
		_('Update interval')
	],
	'status' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'uniqueness_criteria' => [T_ZBX_STR, O_OPT, null, null,	'isset({add}) || isset({update})', _('Device uniqueness criteria')],
	'host_source' =>	[T_ZBX_STR, O_OPT, null,	null,	null],
	'name_source' =>	[T_ZBX_STR, O_OPT, null,	null,	null],
	'g_druleid' =>		[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,		null],
	'dchecks' =>		[null, O_OPT, P_ONLY_TD_ARRAY,		null,		null],
	// actions
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"drule.massdelete","drule.massdisable","drule.massenable"'),
							null
						],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, P_SYS,	null,		null],
	'output' =>			[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'ajaxaction' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'ajaxdata' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	// filter
	'filter_set' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_status' =>	[T_ZBX_INT, O_OPT, null,	IN([-1, DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED]),		null],
	// sort and sortorder
	'sort' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null],
	'sortorder' =>		[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['status'] = isset($_REQUEST['status']) ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
$_REQUEST['dchecks'] = getRequest('dchecks', []);

/*
 * Permissions
 */
if (isset($_REQUEST['druleid'])) {
	$dbDRule = API::DRule()->get([
		'druleids' => getRequest('druleid'),
		'output' => ['name', 'proxy_hostid', 'iprange', 'delay', 'status'],
		'selectDChecks' => [
			'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol', 'snmpv3_privprotocol',
			'snmpv3_contextname', 'host_source', 'name_source'
		],
		'editable' => true
	]);
	if (empty($dbDRule)) {
		access_deny();
	}
}

/*
 * Action
 */
if (hasRequest('add') || hasRequest('update')) {
	$dChecks = getRequest('dchecks', []);
	$uniq = getRequest('uniqueness_criteria', 0);

	foreach ($dChecks as $dcnum => $check) {
		if (substr($check['dcheckid'], 0, 3) === 'new') {
			unset($dChecks[$dcnum]['dcheckid']);
		}

		$dChecks[$dcnum]['uniq'] = ($uniq == $dcnum) ? 1 : 0;
	}

	$discoveryRule = [
		'name' => getRequest('name'),
		'proxy_hostid' => getRequest('proxy_hostid'),
		'iprange' => getRequest('iprange'),
		'delay' => getRequest('delay'),
		'status' => getRequest('status'),
		'dchecks' => $dChecks
	];

	DBStart();

	if (hasRequest('update')) {
		$discoveryRule['druleid'] = getRequest('druleid');
		$result = API::DRule()->update($discoveryRule);

		$messageSuccess = _('Discovery rule updated');
		$messageFailed = _('Cannot update discovery rule');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = API::DRule()->create($discoveryRule);

		$messageSuccess = _('Discovery rule created');
		$messageFailed = _('Cannot create discovery rule');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		$druleid = reset($result['druleids']);
		add_audit($auditAction, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$discoveryRule['name']);
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['druleid'])) {
	$result = API::DRule()->delete([$_REQUEST['druleid']]);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['druleid']);
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['drule.massenable', 'drule.massdisable']) && hasRequest('g_druleid')) {
	$result = true;
	$enable = (getRequest('action') == 'drule.massenable');
	$status = $enable ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED;
	$auditAction = $enable ? AUDIT_ACTION_ENABLE : AUDIT_ACTION_DISABLE;
	$updated = 0;

	DBStart();

	foreach (getRequest('g_druleid') as $druleId) {
		$result &= DBexecute('UPDATE drules SET status='.zbx_dbstr($status).' WHERE druleid='.zbx_dbstr($druleId));

		if ($result) {
			$druleData = get_discovery_rule_by_druleid($druleId);
			add_audit($auditAction, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleId.'] '.$druleData['name']);
		}

		$updated++;
	}

	$messageSuccess = $enable
		? _n('Discovery rule enabled', 'Discovery rules enabled', $updated)
		: _n('Discovery rule disabled', 'Discovery rules disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable discovery rule', 'Cannot enable discovery rules', $updated)
		: _n('Cannot disable discovery rule', 'Cannot disable discovery rules', $updated);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'drule.massdelete' && hasRequest('g_druleid')) {
	$result = API::DRule()->delete(getRequest('g_druleid'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
}

if (hasRequest('action') && hasRequest('g_druleid') && !$result) {
	$drules = API::DRule()->get([
		'output' => [],
		'druleids' => getRequest('g_druleid'),
		'editable' => true
	]);
	uncheckTableRows(null, zbx_objectValues($drules, 'druleid'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'druleid' => getRequest('druleid'),
		'drule' => [],
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0)
	];

	// get drule
	if (isset($data['druleid']) && !isset($_REQUEST['form_refresh'])) {
		$data['drule'] = reset($dbDRule);
		$data['drule']['uniqueness_criteria'] = -1;
		$data['drule']['host_source'] = ZBX_DISCOVERY_DNS;
		$data['drule']['name_source'] = ZBX_DISCOVERY_UNSPEC;

		if (!empty($data['drule']['dchecks'])) {
			$dcheck = reset($data['drule']['dchecks']);
			$data['drule']['host_source'] = $dcheck['host_source'];
			$data['drule']['name_source'] = $dcheck['name_source'];

			foreach ($data['drule']['dchecks'] as $id => $dcheck) {
				if ($dcheck['uniq']) {
					$data['drule']['uniqueness_criteria'] = $dcheck['dcheckid'];
				}

				if ($dcheck['host_source'] == ZBX_DISCOVERY_VALUE) {
					$data['drule']['host_source'] = '_'.$dcheck['dcheckid'];
				}

				if ($dcheck['name_source'] == ZBX_DISCOVERY_VALUE) {
					$data['drule']['name_source'] = '_'.$dcheck['dcheckid'];
				}
			}
		}
	}
	else {
		$data['drule']['proxy_hostid'] = getRequest('proxy_hostid', 0);
		$data['drule']['name'] = getRequest('name', '');
		$data['drule']['iprange'] = getRequest('iprange', '192.168.0.1-254');
		$data['drule']['delay'] = getRequest('delay', DB::getDefault('drules', 'delay'));
		$data['drule']['status'] = getRequest('status', DRULE_STATUS_ACTIVE);
		$data['drule']['dchecks'] = getRequest('dchecks', []);
		$data['drule']['nextcheck'] = getRequest('nextcheck', 0);
		$data['drule']['uniqueness_criteria'] = getRequest('uniqueness_criteria', -1);
		$data['drule']['host_source'] = getRequest('host_source', ZBX_DISCOVERY_DNS);
		$data['drule']['name_source'] = getRequest('name_source', ZBX_DISCOVERY_UNSPEC);
	}

	if (!empty($data['drule']['dchecks'])) {
		$data['drule']['dchecks'] = array_map(function($value) {
			$data = [
				'type' => $value['type'],
				'dcheckid' => $value['dcheckid'],
				'ports' => $value['ports'],
				'uniq' => array_key_exists('uniq', $value) ? $value['uniq'] : null,
				'host_source' => $value['host_source'],
				'name_source' => $value['name_source']
			];

			$data['name'] = discovery_check2str(
				$value['type'],
				isset($value['key_']) ? $value['key_'] : '',
				isset($value['ports']) ? $value['ports'] : ''
			);

			switch($value['type']) {
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
					$data['snmp_community'] = $value['snmp_community'];
					// break; is not missing here
				case SVC_AGENT:
					$data['key_'] = $value['key_'];
					break;
				case SVC_SNMPv3:
					$data += [
						'key_' => $value['key_'],
						'snmpv3_contextname' => $value['snmpv3_contextname'],
						'snmpv3_securityname' => $value['snmpv3_securityname'],
						'snmpv3_securitylevel' => $value['snmpv3_securitylevel']
					];

					if ($value['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
							|| $value['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
						$data += [
							'snmpv3_authprotocol' => $value['snmpv3_authprotocol'],
							'snmpv3_authpassphrase' => $value['snmpv3_authpassphrase']
						];
					}

					if ($value['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
						$data += [
							'snmpv3_privprotocol' => $value['snmpv3_privprotocol'],
							'snmpv3_privpassphrase' => $value['snmpv3_privpassphrase']
						];
					}
					break;
			}

			return $data;
		}, $data['drule']['dchecks']);

		order_result($data['drule']['dchecks'], 'name');
	}

	// get proxies
	$data['proxies'] = API::Proxy()->get([
		'output' => API_OUTPUT_EXTEND
	]);
	order_result($data['proxies'], 'host');

	// render view
	echo (new CView('configuration.discovery.edit', $data))->getOutput();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.discoveryconf.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.discoveryconf.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.discoveryconf.filter_name');
		CProfile::delete('web.discoveryconf.filter_status');
	}

	$filter = [
		'name' => CProfile::get('web.discoveryconf.filter_name', ''),
		'status' => CProfile::get('web.discoveryconf.filter_status', -1)
	];

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'profileIdx' => 'web.discoveryconf.filter',
		'active_tab' => CProfile::get('web.discoveryconf.filter.active', 1)
	];

	// get drules
	$data['drules'] = API::DRule()->get([
		'output' => ['proxy_hostid', 'name', 'status', 'iprange', 'delay'],
		'selectDChecks' => ['type'],
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'filter' => [
			'status' => ($filter['status'] == -1) ? null : $filter['status']
		],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	if ($data['drules']) {
		foreach ($data['drules'] as $key => $drule) {
			$checks = [];

			foreach ($drule['dchecks'] as $check) {
				$checks[$check['type']] = discovery_check_type2str($check['type']);
			}

			order_result($checks);

			$data['drules'][$key]['checks'] = $checks;

			$data['drules'][$key]['proxy'] = ($drule['proxy_hostid'] != 0)
				? get_host_by_hostid($drule['proxy_hostid'])['host']
				: '';
		}

		order_result($data['drules'], $sortField, $sortOrder);
	}

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

	$data['paging'] = CPagerHelper::paginate($page_num, $data['drules'], $sortOrder, new CUrl('discoveryconf.php'));

	// render view
	echo (new CView('configuration.discovery.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';

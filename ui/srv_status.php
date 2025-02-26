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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';

$page['title'] = _('Services');
$page['file'] = 'srv_status.php';
$page['scripts'] = ['layout.mode.js', 'srv_status.js'];

define('ZBX_PAGE_DO_REFRESH', 1);

if (!getRequest('serviceid') || !getRequest('showgraph')) {
	$page['web_layout_mode'] = CViewHelper::loadLayoutMode();
}
require_once dirname(__FILE__).'/include/page_header.php';

$periods = [
	'today' => _('Today'),
	'week' => _('This week'),
	'month' => _('This month'),
	'year' => _('This year'),
	24 => _('Last 24 hours'),
	24 * 7 => _('Last 7 days'),
	24 * 30 => _('Last 30 days'),
	24 * DAY_IN_YEAR => _('Last 365 days')
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'serviceid' =>	[T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID,	null],
	'showgraph' =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('1'),		'isset({serviceid})'],
	'period' =>		[T_ZBX_STR, O_OPT, P_SYS,	IN('"'.implode('","', array_keys($periods)).'"'),	null]
];
check_fields($fields);

if (isset($_REQUEST['serviceid']) && isset($_REQUEST['showgraph'])) {
	$service = API::Service()->get([
		'output' => ['serviceid'],
		'serviceids' => getRequest('serviceid')
	]);
	$service = reset($service);

	if ($service) {
		$table = (new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
			->addClass(ZBX_STYLE_CENTER)
			->addItem(new CImg('chart5.php?serviceid='.$service['serviceid'].url_param('path')))
			->show();
	}
	else {
		access_deny();
	}
}
else {
	$period = getRequest('period', 7 * 24);
	$period_end = time();

	switch ($period) {
		case 'today':
			$period_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
			break;
		case 'week':
			$period_start = strtotime('last sunday');
			break;
		case 'month':
			$period_start = mktime(0, 0, 0, date('n'), 1, date('Y'));
			break;
		case 'year':
			$period_start = mktime(0, 0, 0, 1, 1, date('Y'));
			break;
		case 24:
		case 24 * 7:
		case 24 * 30:
		case 24 * DAY_IN_YEAR:
			$period_start = $period_end - ($period * 3600);
			break;
	}

	// fetch services
	$services = API::Service()->get([
		'output' => ['name', 'serviceid', 'showsla', 'goodsla', 'algorithm', 'sortorder'],
		'selectParent' => ['serviceid'],
		'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
		'selectTrigger' => ['description', 'triggerid', 'expression'],
		'preservekeys' => true
	]);

	sortServices($services);

	// expand trigger descriptions
	$triggers = zbx_objectValues(
		array_filter($services, function($service) { return (bool) $service['trigger']; }), 'trigger'
	);
	$triggers = CMacrosResolverHelper::resolveTriggerNames(zbx_toHash($triggers, 'triggerid'));

	foreach ($services as &$service) {
		if ($service['trigger']) {
			$service['trigger'] = $triggers[$service['trigger']['triggerid']];
		}
	}
	unset($service);

	// fetch sla
	$slaData = API::Service()->getSla([
		'intervals' => [[
			'from' => $period_start,
			'to' => $period_end
		]]
	]);
	// expand problem trigger descriptions
	foreach ($slaData as &$serviceSla) {
		foreach ($serviceSla['problems'] as &$problemTrigger) {
			$problemTrigger['description'] = $triggers[$problemTrigger['triggerid']]['description'];
		}
		unset($problemTrigger);
	}
	unset($serviceSla);

	$treeData = [];
	createServiceMonitoringTree($services, $slaData, $period, $treeData);
	$tree = new CServiceTree('service_status_tree',
		$treeData,
		[
			'caption' => _('Service'),
			'status' => _('Status'),
			'reason' => _('Reason'),
			'sla' => (new CColHeader(_('Problem time')))->setColSpan(2),
			'sla2' => null,
			'sla3' => _('SLA').' / '._('Acceptable SLA')
		]
	);

	if ($tree) {
		(new CWidget())
			->setTitle(_('Services'))
			->setWebLayoutMode($page['web_layout_mode'])
			->setControls(new CList([
				(new CForm('get'))
					->setName('srv_status')
					->cleanItems()
					->setAttribute('aria-label', _('Main filter'))
					->addItem((new CList())
						->addItem([
							new CLabel(_('Period'), 'label-period'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							(new CSelect('period'))
								->setFocusableElementId('label-period')
								->setValue($period)
								->addOptions(CSelect::createOptionsFromArray($periods))
						])
					),
				(new CTag('nav', true, get_icon('kioskmode', ['mode' => $page['web_layout_mode']])))
					->setAttribute('aria-label', _('Content controls'))
			]))
			->addItem($tree->getHTML())
			->show();
	}
	else {
		error(_('Cannot format Tree. Check logic structure in service links.'));
	}
}

require_once dirname(__FILE__).'/include/page_footer.php';

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


require_once dirname(__FILE__).'/../../blocks.inc.php';

class CScreenHostTriggers extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$params = [
			'groupids' => null,
			'hostids' => null,
			'maintenance' => null,
			'trigger_name' => '',
			'severity' => null,
			'limit' => $this->screenitem['elements']
		];

		// by default triggers are sorted by date desc, do we need to override this?
		switch ($this->screenitem['sort_triggers']) {
			case SCREEN_SORT_TRIGGERS_DATE_DESC:
				$params['sortfield'] = 'lastchange';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
				$params['sortfield'] = 'severity';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
				// a little black magic here - there is no such field 'hostname' in 'triggers',
				// but API has a special case for sorting by hostname
				$params['sortfield'] = 'hostname';
				$params['sortorder'] = ZBX_SORT_UP;
				break;
		}

		if ($this->screenitem['resourceid'] != 0) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => [$this->screenitem['resourceid']]
			]);

			$header = (new CDiv([
				new CTag('h4', true, _('Host issues')),
				(new CList())->addItem([_('Host'), ':', NBSP(), $hosts[0]['name']])
			]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

			$params['hostids'] = $this->screenitem['resourceid'];
		}
		else {
			$groupid = getRequest('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
			$hostid = getRequest('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

			CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
			CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

			// get groups
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'monitored_hosts' => true,
				'preservekeys' => true
			]);
			order_result($groups, 'name');

			foreach ($groups as &$group) {
				$group = $group['name'];
			}
			unset($group);

			// get hsots
			$options = [
				'output' => ['name'],
				'monitored_hosts' => true,
				'preservekeys' => true
			];
			if ($groupid != 0) {
				$options['groupids'] = [$groupid];
			}
			$hosts = API::Host()->get($options);
			order_result($hosts, 'name');

			foreach ($hosts as &$host) {
				$host = $host['name'];
			}
			unset($host);

			$groups = [0 => _('all')] + $groups;
			$hosts = [0 => _('all')] + $hosts;

			if (!array_key_exists($hostid, $hosts)) {
				$hostid = 0;
			}

			if ($groupid != 0) {
				$params['groupids'] = $groupid;
			}
			if ($hostid != 0) {
				$params['hostids'] = $hostid;
			}

			$groups_select = (new CSelect('tr_groupid'))
				->setId('tr-groupid')
				->setFocusableElementId('label-group')
				->setValue($groupid)
				->addOptions(CSelect::createOptionsFromArray($groups))
				->setDisabled($this->mode == SCREEN_MODE_EDIT);

			$hosts_select = (new CSelect('tr_hostid'))
				->setId('tr-hostid')
				->setFocusableElementId('label-host')
				->setValue($hostid)
				->addOptions(CSelect::createOptionsFromArray($hosts))
				->setDisabled($this->mode == SCREEN_MODE_EDIT);

			$header = (new CDiv([
				new CTag('h4', true, _('Host issues')),
				(new CForm('get', $this->pageFile))
					->cleanItems()
					->addItem(
						(new CList())
							->addItem([new CLabel(_('Group'), $groups_select->getFocusableElementId()), NBSP(),
								$groups_select
							])
							->addItem(NBSP())
							->addItem([new CLabel(_('Host'), $hosts_select->getFocusableElementId()), NBSP(),
								$hosts_select
							])
					)
			]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);
		}

		[$table, $info] = $this->getProblemsListTable($params);

		$footer = (new CList())
			->addItem($info)
			->addItem(_s('Updated: %1$s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		$script = new CScriptTag('monitoringScreen.refreshOnAcknowledgeCreate();'.
			'$("#tr-groupid, #tr-hostid").on("change", (e) => $(e.target).closest("form").submit())'
		);

		return $this->getOutput(new CUiWidget('hat_trstatus', [$header, $table, $footer, $script]));
	}

	/**
	 * Render table with host or host group problems.
	 *
	 * @param array   $filter               Array of filter options.
	 * @param int     $filter['limit']      Table rows count.
	 * @param array   $filter['groupids']   Host group ids.
	 * @param array   $filter['hostids']    Host ids.
	 * @param string  $filter['sortfield']  Sort field name.
	 * @param string  $filter['sortorder']  Sort order.
	 */
	protected function getProblemsListTable(array $filter) {
		$config = select_config();

		$filter = $filter + [
			'show' => TRIGGERS_OPTION_IN_PROBLEM,
			'show_timeline' => 0,
			'details' => 1,
			'show_opdata' => 0,
			'sort_field' => '',
			'sort_order' => ZBX_SORT_DOWN
		];

		$data = CScreenProblem::getData($filter, $config);

		$header = [
			'hostname' => _('Host'),
			'severity' => _('Issue'),
			'lastchange' => _('Last change')
		];

		if (array_key_exists('sortfield', $filter)) {
			$sort_field = $filter['sortfield'];
			$sort_order = ($sort_field !== 'lastchange') ? $filter['sortorder'] : ZBX_SORT_DOWN;

			$header[$sort_field] = [
				$header[$sort_field],
				(new CDiv())->addClass(($sort_order === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP)
			];

			$data = CScreenProblem::sortData($data, $config, $sort_field === 'hostname' ? 'host' : $sort_field,
				$sort_order
			);
		}

		$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
			min($filter['limit'], count($data['problems'])),
			(count($data['problems']) > $config['search_limit']) ? '+' : '',
			min($config['search_limit'], count($data['problems']))
		);
		$data['problems'] = array_slice($data['problems'], 0, $filter['limit'], true);
		$data = CScreenProblem::makeData($data, $filter);

		$hostids = [];
		foreach ($data['triggers'] as $trigger) {
			$hostids += $trigger['hosts'] ? array_fill_keys(zbx_objectValues($trigger['hosts'], 'hostid'), '') : [];
		}

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'hostids' => array_keys($hostids),
			'preservekeys' => true
		]);

		$table = (new CTableInfo())->setHeader($header + [_('Age'), _('Info'), _('Ack'), _('Actions')]);

		foreach ($data['problems'] as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			// Host name with hint box.
			$host = reset($trigger['hosts']);
			$host = $hosts[$host['hostid']];
			$host_name = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));

			// Info.
			$info_icons = [];
			if ($problem['r_eventid'] != 0) {
				if ($problem['correlationid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['correlationid'], $data['correlations'])
							? _s('Resolved by correlation rule "%1$s".',
								$data['correlations'][$problem['correlationid']]['name']
							)
							: _('Resolved by correlation rule.')
					);
				}
				elseif ($problem['userid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['userid'], $data['users'])
							? _s('Resolved by user "%1$s".', getUserFullname($data['users'][$problem['userid']]))
							: _('Resolved by inaccessible user.')
					);
				}
			}

			// Clock.
			$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_triggerids[]', $trigger['triggerid'])
					->setArgument('filter_set', '1')
			);

			// Create acknowledge link.
			$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
			$problem_update_link = (new CLink($is_acknowledged ? _('Yes') : _('No')))
				->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT)
				->onClick('acknowledgePopUp('.json_encode(['eventids' => [$problem['eventid']]]).', this);');

			$table->addRow([
				$host_name,
				(new CCol([
					(new CLinkAction($problem['name']))
						->setAjaxHint(CHintBoxHelper::getEventList($trigger['triggerid'], $problem['eventid']))
				]))->addClass(getSeverityStyle($problem['severity'])),
				$clock,
				zbx_date2age($problem['clock']),
				makeInformationList($info_icons),
				$problem_update_link,
				makeEventActionsIcons($problem['eventid'], $data['actions'], $data['users'], $config)
			]);
		}

		return [$table, $info];
	}
}

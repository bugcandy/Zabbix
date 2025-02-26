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

class CScreenHostgroupTriggers extends CScreenHostTriggers {

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

		if ($this->screenitem['resourceid'] > 0) {
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => [$this->screenitem['resourceid']]
			]);

			$header = (new CDiv([
				new CTag('h4', true, _('Host group issues')),
				(new CList())->addItem([_('Group'), ':', NBSP(), $groups[0]['name']])
			]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

			$params['groupids'] = $this->screenitem['resourceid'];
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

			// get hosts
			$options = [
				'output' => ['name'],
				'monitored_hosts' => true,
				'preservekeys' => true
			];
			if ($groupid != 0) {
				$options['groupids'] = [$groupid];
			}
			$hosts = API::Host()->get($options);
			order_result($hosts, 'host');

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
				new CTag('h4', true, _('Host group issues')),
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

		return $this->getOutput(new CUiWidget('hat_htstatus', [$header, $table, $footer, $script]));
	}
}

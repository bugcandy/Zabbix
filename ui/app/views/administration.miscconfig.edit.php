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
 * @var CView $this
 */

$widget = (new CWidget())
	->setTitle(_('Other configuration parameters'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$from_list = new CFormList();

$discovery_group = (new CSelect('discovery_groupid'))
	->setFocusableElementId('label-discovery-groupid')
	->setValue($data['discovery_groupid']);

foreach ($data['discovery_groups'] as $group) {
	$discovery_group->addOption(new CSelectOption($group['groupid'], $group['name']));
}

$alert_user_group = (new CSelect('alert_usrgrpid'))
	->setFocusableElementId('label-alert-usrgrpid')
	->setValue($data['alert_usrgrpid'])
	->addOption(new CSelectOption(0, _('None')));

foreach ($data['alert_usrgrps'] as $usrgrp) {
	$alert_user_group->addOption(new CSelectOption($usrgrp['usrgrpid'], $usrgrp['name']));
}

$from_list
	->addRow((new CLabel(_('Refresh unsupported items'), 'refresh_unsupported'))->setAsteriskMark(),
		(new CTextBox('refresh_unsupported', $data['refresh_unsupported']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(new CLabel(_('Group for discovered hosts'), $discovery_group->getFocusableElementId()), $discovery_group)
	->addRow(_('Default host inventory mode'),
		(new CRadioButtonList('default_inventory_mode', (int) $data['default_inventory_mode']))
			->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
			->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
			->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
			->setModern(true)
	)
	->addRow(new CLabel(_('User group for database down message'), $alert_user_group->getFocusableElementId()),
		$alert_user_group
	)
	->addRow(_('Log unmatched SNMP traps'),
		(new CCheckBox('snmptrap_logging'))
			->setUncheckedValue('0')
			->setChecked($data['snmptrap_logging'] == 1)
	);

$form = (new CForm())
	->setName('otherForm')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'miscconfig.update')
		->getUrl()
	)
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addItem(
		(new CTabView())
			->addTab('other', _('Other parameters'), $from_list)
			->setFooter(makeFormFooter(new CSubmit('update', _('Update'))))
	);

$widget->addItem($form)->show();

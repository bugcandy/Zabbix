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

require_once dirname(__FILE__).'/js/configuration.host.massupdate.js.php';

$hostWidget = (new CWidget())->setTitle(_('Hosts'));

// create form
$hostView = (new CForm())
	->setName('hostForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', 'host.massupdate')
	->addVar('tls_accept', $data['tls_accept'])
	->setId('hostForm')
	->disablePasswordAutofill();
foreach ($data['hosts'] as $hostid) {
	$hostView->addVar('hosts['.$hostid.']', $hostid);
}

// create form list
$hostFormList = new CFormList('hostFormList');

// update host groups
$groups_to_update = $data['groups']
	? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $data['groups'],
		'editable' => true
	]), ['groupid' => 'id'])
	: [];

$hostFormList->addRow(
	(new CVisibilityBox('visible[groups]', 'groups-div', _('Original')))
		->setLabel(_('Host groups'))
		->setChecked(array_key_exists('groups', $data['visible']))
		->setAttribute('autofocus', 'autofocus'),
	(new CDiv([
		(new CRadioButtonList('mass_update_groups', (int) $data['mass_update_groups']))
			->addValue(_('Add'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_ACTION_REMOVE)
			->setModern(true)
			->addStyle('margin-bottom: 5px;'),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'hostGroup',
			'add_new' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			'data' => $groups_to_update,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $hostView->getName(),
					'dstfld1' => 'groups_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	]))->setId('groups-div')
);

// append description to form list
$hostFormList->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))
		->setLabel(_('Description'))
		->setChecked(array_key_exists('description', $data['visible'])),
	(new CTextArea('description', $data['description']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setMaxlength(DB::getFieldLength('hosts', 'description'))
);

// append proxy to form list
$proxy_select = (new CSelect('proxy_hostid'))
	->setId('proxy_hostid')
	->setValue($data['proxy_hostid'])
	->addOption(new CSelectOption(0, _('(no proxy)')));

foreach ($data['proxies'] as $proxie) {
	$proxy_select->addOption(new CSelectOption($proxie['hostid'], $proxie['host']));
}
$hostFormList->addRow(
	(new CVisibilityBox('visible[proxy_hostid]', 'proxy_hostid', _('Original')))
		->setLabel(_('Monitored by proxy'))
		->setChecked(array_key_exists('proxy_hostid', $data['visible'])),
	$proxy_select
);

// append status to form list
$hostFormList->addRow(
	(new CVisibilityBox('visible[status]', 'status', _('Original')))
		->setLabel(_('Status'))
		->setChecked(array_key_exists('status', $data['visible'])),
	(new CSelect('status'))
		->setValue($data['status'])
		->setId('status')
		->addOptions(CSelect::createOptionsFromArray([
			HOST_STATUS_MONITORED => _('Enabled'),
			HOST_STATUS_NOT_MONITORED => _('Disabled')
		]))
);

$templatesFormList = new CFormList('templatesFormList');

// append templates table to form list
$newTemplateTable = (new CTable())
	->addRow(
		(new CRadioButtonList('mass_action_tpls', (int) $data['mass_action_tpls']))
			->addValue(_('Link'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Unlink'), ZBX_ACTION_REMOVE)
			->setModern(true)
	)
	->addRow([
		(new CMultiSelect([
			'name' => 'templates[]',
			'object_name' => 'templates',
			'data' => $data['templates'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $hostView->getName(),
					'dstfld1' => 'templates_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addRow([
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('mass_clear_tpls'))
				->setLabel(_('Clear when unlinking'))
				->setChecked($data['mass_clear_tpls'] == 1)
			)
	]);

$templatesFormList->addRow(
	(new CVisibilityBox('visible[templates]', 'templateDiv', _('Original')))
		->setLabel(_('Link templates'))
		->setChecked(array_key_exists('templates', $data['visible'])),
	(new CDiv($newTemplateTable))
		->setId('templateDiv')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$ipmiFormList = new CFormList('ipmiFormList');

// append ipmi to form list
$ipmiFormList->addRow(
	(new CVisibilityBox('visible[ipmi_authtype]', 'ipmi_authtype', _('Original')))
		->setLabel(_('Authentication algorithm'))
		->setChecked(array_key_exists('ipmi_authtype', $data['visible'])),
	(new CSelect('ipmi_authtype'))
		->setId('ipmi_authtype')
		->setValue($data['ipmi_authtype'])
		->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
);

$ipmiFormList->addRow(
	(new CVisibilityBox('visible[ipmi_privilege]', 'ipmi_privilege', _('Original')))
		->setLabel(_('Privilege level'))
		->setChecked(array_key_exists('ipmi_privilege', $data['visible'])),
	(new CSelect('ipmi_privilege'))
		->setId('ipmi_privilege')
		->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
		->setValue($data['ipmi_privilege'])
);

$ipmiFormList->addRow(
	(new CVisibilityBox('visible[ipmi_username]', 'ipmi_username', _('Original')))
		->setLabel(_('Username'))
		->setChecked(array_key_exists('ipmi_username', $data['visible'])),
	(new CTextBox('ipmi_username', $data['ipmi_username']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->disableAutocomplete()
);

$ipmiFormList->addRow(
	(new CVisibilityBox('visible[ipmi_password]', 'ipmi_password', _('Original')))
		->setLabel(_('Password'))
		->setChecked(array_key_exists('ipmi_password', $data['visible'])),
	(new CTextBox('ipmi_password', $data['ipmi_password']))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->disableAutocomplete()
);

$inventoryFormList = new CFormList('inventoryFormList');

// append inventories to form list
$inventoryFormList->addRow(
	(new CVisibilityBox('visible[inventory_mode]', 'inventory_mode_div', _('Original')))
		->setLabel(_('Inventory mode'))
		->setChecked(array_key_exists('inventory_mode', $data['visible'])),
	(new CDiv(
		(new CRadioButtonList('inventory_mode', (int) $data['inventory_mode']))
			->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
			->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
			->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
			->setModern(true)
	))->setId('inventory_mode_div')
);

$tags_form_list = new CFormList('tagsFormList');

// append tags table to form list
$tags_form_list->addRow(
	(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))
		->setLabel(_('Tags'))
		->setChecked(array_key_exists('tags', $data['visible'])),
	(new CDiv([
		(new CRadioButtonList('mass_update_tags', (int) $data['mass_update_tags']))
			->addValue(_('Add'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_ACTION_REMOVE)
			->setModern(true)
			->addStyle('margin-bottom: 10px;'),
		renderTagTable($data['tags'])
			->setHeader([_('Name'), _('Value'), _('Action')])
			->setId('tags-table')
	]))->setId('tags-div')
);

$hostInventoryTable = DB::getSchema('host_inventory');
foreach ($data['inventories'] as $field => $fieldInfo) {
	if (!array_key_exists($field, $data['host_inventory'])) {
		$data['host_inventory'][$field] = '';
	}

	if ($hostInventoryTable['fields'][$field]['type'] == DB::FIELD_TYPE_TEXT) {
		$fieldInput = (new CTextArea('host_inventory['.$field.']', $data['host_inventory'][$field]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH);
	}
	else {
		$fieldInput = (new CTextBox('host_inventory['.$field.']', $data['host_inventory'][$field]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('maxlength', $hostInventoryTable['fields'][$field]['length']);
	}

	$inventoryFormList->addRow(
		(new CVisibilityBox('visible['.$field.']', $fieldInput->getId(), _('Original')))
			->setLabel($fieldInfo['title'])
			->setChecked(array_key_exists($field, $data['visible'])),
		$fieldInput, null, 'formrow-inventory'
	);
}

// Encryption
$encryption_form_list = new CFormList('encryption');

$encryption_table = (new CTable())
	->addRow([_('Connections to host'),
		(new CRadioButtonList('tls_connect', (int) $data['tls_connect']))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
	])
	->addRow([_('Connections from host'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))->setLabel(_('No encryption')))
			->addItem((new CCheckBox('tls_in_psk'))->setLabel(_('PSK')))
			->addItem((new CCheckBox('tls_in_cert'))->setLabel(_('Certificate')))
	])
	->addRow([
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
		(new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false, 128))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired()
	])
	->addRow([
		(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
		(new CTextBox('tls_psk', $data['tls_psk'], false, 512))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired()
			->disableAutocomplete()
	])
	->addRow([_('Issuer'),
		(new CTextBox('tls_issuer', $data['tls_issuer'], false, 1024))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	])
	->addRow([_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', $data['tls_subject'], false, 1024))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	]);

$encryption_form_list->addRow(
	(new CVisibilityBox('visible[encryption]', 'encryption_div', _('Original')))
		->setLabel(_('Connections'))
		->setChecked(array_key_exists('encryption', $data['visible'])),
	(new CDiv($encryption_table))
		->setId('encryption_div')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append tabs to form
$hostTab = (new CTabView())
	->addTab('hostTab', _('Host'), $hostFormList)
	->addTab('templatesTab', _('Templates'), $templatesFormList)
	->addTab('ipmiTab', _('IPMI'), $ipmiFormList)
	->addTab('tagsTab', _('Tags'), $tags_form_list)
	->addTab('macros_tab', _('Macros'), new CPartial('massupdate.macros.tab', [
		'visible' => $data['visible'],
		'macros' => $data['macros'],
		'macros_checkbox' => $data['macros_checkbox'],
		'macros_visible' => $data['macros_visible']
	]))

	->addTab('inventoryTab', _('Inventory'), $inventoryFormList)
	->addTab('encryptionTab', _('Encryption'), $encryption_form_list);

// reset the tab when opening the form for the first time
if (!hasRequest('masssave') && !hasRequest('inventory_mode')) {
	$hostTab->setSelected(0);
}

// append buttons to form
$hostTab->setFooter(makeFormFooter(
	new CSubmit('masssave', _('Update')),
	[new CButtonCancel()]
));

$hostView->addItem($hostTab);

$hostWidget->addItem($hostView);

$hostWidget->show();

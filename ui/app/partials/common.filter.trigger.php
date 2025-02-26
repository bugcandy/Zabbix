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
 * @var CPartial $this
 */

$inventory_fields = [];
foreach (getHostInventories() as $inventory) {
	$inventory_fields[$inventory['db_field']] = $inventory['title'];
}

$this->includeJsFile('common.filter.trigger.js.php', ['inventory_fields' => $inventory_fields]);

$filterForm = (new CFilter((new CUrl('overview.php'))->setArgument('type', 0)))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab']);

$severityNames = [];
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$severityNames[] = getSeverityName($severity, $data['config']);
}

$column1 = (new CFormList())
	->addRow(_('Show'),
		(new CRadioButtonList('show_triggers', (int) $data['filter']['showTriggers']))
			->addValue(_('Recent problems'), TRIGGERS_OPTION_RECENT_PROBLEM)
			->addValue(_('Problems'), TRIGGERS_OPTION_IN_PROBLEM)
			->addValue(_('Any'), TRIGGERS_OPTION_ALL)
			->setModern(true)
	)
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'multiple' => true,
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'with_monitored_triggers' => true,
					'enrich_parent_groups' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => 'hosts',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_hostids_',
					'monitored_hosts' => true,
					'with_monitored_triggers' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);


$statusChangeDays = (new CNumericBox('status_change_days', $data['filter']['statusChangeDays'], 3, false, false, false))
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);

if (!$data['filter']['statusChange']) {
	$statusChangeDays->setAttribute('disabled', 'disabled');
}

$column1
	->addRow(_('Application'), [
		(new CTextBox('application', $data['filter']['application']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('application_name', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic", jQuery.extend('.
				json_encode([
					'srctbl' => 'applications',
					'srcfld1' => 'name',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'application',
					'real_hosts' => '1',
					'with_applications' => '1'
				]).', getFirstMultiselectValue("filter_hostids_", "filter_groupids_")), null, this);'
			)
	])
	->addRow(_('Name'),
		(new CTextBox('txt_select', $data['filter']['txtSelect']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Minimum severity'), 'label-show-severity'),
		(new CSelect('show_severity'))
			->setFocusableElementId('label-show-severity')
			->setValue($data['filter']['showSeverity'])
			->addOptions(CSelect::createOptionsFromArray($severityNames))
	)
	->addRow(_('Age less than'), [
		(new CCheckBox('status_change'))
			->setChecked($data['filter']['statusChange'] == 1)
			->onClick('javascript: jQuery("#status_change_days").prop("disabled", !this.checked)'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$statusChangeDays,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('days')
	]);

// inventory filter
$inventoryFilters = $data['filter']['inventory'];
if (!$inventoryFilters) {
	$inventoryFilters = [
		['field' => '', 'value' => '']
	];
}

$inventoryFilterTable = new CTable();
$inventoryFilterTable->setId('inventory-filter');
$i = 0;
foreach ($inventoryFilters as $field) {
	$inventoryFilterTable->addRow([
		(new CSelect('inventory['.$i.'][field]'))
			->setValue($field['field'])
			->addOptions(CSelect::createOptionsFromArray($inventory_fields)),
		(new CTextBox('inventory['.$i.'][value]', $field['value']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('inventory['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$inventoryFilterTable->addRow(
	(new CCol(
		(new CButton('inventory_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(2)
);

$column2 = (new CFormList())
	->addRow(_('Host inventory'), $inventoryFilterTable)
	->addRow(_('Show unacknowledged only'),
		(new CCheckBox('ack_status'))
			->setChecked($data['filter']['ackStatus'] == 1)
			->setUncheckedValue(0)
	)
	->addRow(_('Show suppressed problems'),
		(new CCheckBox('show_suppressed'))
			->setChecked($data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
	);

$filterForm->addFilterTab(_('Filter'), [$column1, $column2]);

$filterForm->show();

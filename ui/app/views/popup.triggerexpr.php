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

// Create form.
$expression_form = (new CForm())
	->cleanItems()
	->setName('expression')
	->addVar('action', 'popup.triggerexpr')
	->addVar('dstfrm', $data['dstfrm'])
	->addVar('dstfld1', $data['dstfld1'])
	->addItem((new CVar('hostid', $data['hostid']))->removeId())
	->addVar('groupid', $data['groupid'])
	->addVar('itemid', $data['itemid'])
	->addItem((new CInput('submit', 'submit'))
		->addStyle('display: none;')
		->removeId()
	);

if ($data['parent_discoveryid'] !== '') {
	$expression_form->addVar('parent_discoveryid', $data['parent_discoveryid']);
}

// Create form list.
$expression_form_list = new CFormList();

// Append item to form list.
$popup_options = [
	'srctbl' => 'items',
	'srcfld1' => 'itemid',
	'srcfld2' => 'name',
	'dstfrm' => $expression_form->getName(),
	'dstfld1' => 'itemid',
	'dstfld2' => 'item_description',
	'with_webitems' => '1',
	'writeonly' => '1'
];

if ($data['hostid']) {
	$popup_options['hostid'] = $data['hostid'];
}

if ($data['parent_discoveryid'] !== '') {
	$popup_options['normal_only'] = '1';
}

$item = [
	(new CTextBox('item_description', $data['item_description'], true))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('select', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.json_encode($popup_options).', null, this);')
];

if ($data['parent_discoveryid'] !== '') {
	$item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$item[] = (new CButton('select', _('Select prototype')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.
			json_encode([
				'srctbl' => 'item_prototypes',
				'srcfld1' => 'itemid',
				'srcfld2' => 'name',
				'dstfrm' => $expression_form->getName(),
				'dstfld1' => 'itemid',
				'dstfld2' => 'item_description',
				'parent_discoveryid' => $data['parent_discoveryid']
			]).', null, this);'
		)
		->removeId();
}

$expression_form_list->addRow((new CLabel(_('Item'), 'item_description'))->setAsteriskMark(), $item);

$function_select = (new CSelect('function'))
	->setFocusableElementId('label-function')
	->setId('function')
	->setValue($data['function']);

foreach ($data['functions'] as $id => $f) {
	$function_select->addOption(new CSelectOption($id, $f['description']));
}

$expression_form_list->addRow(new CLabel(_('Function'), $function_select->getFocusableElementId()), $function_select);

if (array_key_exists('params', $data['functions'][$data['selectedFunction']])) {
	$paramid = 0;

	foreach ($data['functions'][$data['selectedFunction']]['params'] as $param_name => $param_function) {
		if (array_key_exists($param_name, $data['params'])) {
			$param_value = $data['params'][$param_name];
		}
		else {
			$param_value = array_key_exists($paramid, $data['params']) ? $data['params'][$paramid] : null;
		}

		$label = $param_function['A'] ? (new CLabel($param_function['C']))->setAsteriskMark() : $param_function['C'];

		if ($param_function['T'] == T_ZBX_INT) {
			$param_type_element = null;

			if (in_array($param_name, ['last'])) {
				if (array_key_exists('M', $param_function)) {
					if (in_array($data['selectedFunction'], ['last', 'band', 'strlen'])) {
						$param_type_element = $param_function['M'][PARAM_TYPE_COUNTS];
						$label = $param_function['C'];
						$expression_form->addItem((new CVar('paramtype', PARAM_TYPE_COUNTS))->removeId());
					}
					else {
						$param_type_element = (new CSelect('paramtype'))
							->setValue($param_value === '' ? PARAM_TYPE_TIME : $data['paramtype'])
							->addOptions(CSelect::createOptionsFromArray($param_function['M']));
					}
				}
				else {
					$expression_form->addItem((new CVar('paramtype', PARAM_TYPE_TIME))->removeId());
					$param_type_element = _('Time');
				}
			}
			elseif (in_array($param_name, ['shift'])) {
				$param_type_element = _('Time');
			}

			$param_field = (new CTextBox('params['.$param_name.']', $param_value))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

			$expression_form_list->addRow($label, [
				$param_field,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$param_type_element
			]);
		}
		else {
			$expression_form_list->addRow($label,
				(new CTextBox('params['.$param_name.']', $param_value))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
			if ($paramid === 0) {
				$expression_form->addItem((new CVar('paramtype', PARAM_TYPE_TIME))->removeId());
			}
		}

		$paramid++;
	}
}
else {
	$expression_form->addVar('paramtype', PARAM_TYPE_TIME);
}

$expression_form_list->addRow(
	(new CLabel(_('Result'), 'value'))->setAsteriskMark(), [
		(new CSelect('operator'))
			->setValue($data['operator'])
			->setFocusableElementId('value')
			->addOptions(CSelect::createOptionsFromArray(array_combine($data['functions'][$data['function']]['operators'],
				$data['functions'][$data['function']]['operators']
			))),
		' ',
		(new CTextBox('value', $data['value']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	]
);

$expression_form->addItem($expression_form_list);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([$data['errors'], $expression_form]))->toString(),
	'buttons' => [
		[
			'title' => _('Insert'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validate_trigger_expression(overlay);'
		]
	],
	'script_inline' => $this->readJsFile('popup.triggerexpr.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

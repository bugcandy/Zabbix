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

$this->addJsFile('colorpicker.js');

$this->includeJsFile('administration.trigseverity.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Trigger severities'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$severity_tab = (new CFormList())
	->addRow((new CLabel(_('Not classified'), 'severity_name_0'))->setAsteriskMark(), [
		(new CTextBox('severity_name_0', $data['severity_name_0']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32)
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_0', $data['severity_color_0'])
	])
	->addRow((new CLabel(_('Information'), 'severity_name_1'))->setAsteriskMark(), [
		(new CTextBox('severity_name_1', $data['severity_name_1']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_1', $data['severity_color_1'])
	])
	->addRow((new CLabel(_('Warning'), 'severity_name_2'))->setAsteriskMark(), [
		(new CTextBox('severity_name_2', $data['severity_name_2']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_2', $data['severity_color_2'])
	])
	->addRow((new CLabel(_('Average'), 'severity_name_3'))->setAsteriskMark(), [
		(new CTextBox('severity_name_3', $data['severity_name_3']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_3', $data['severity_color_3'])
	])
	->addRow((new CLabel(_('High'), 'severity_name_4'))->setAsteriskMark(), [
		(new CTextBox('severity_name_4', $data['severity_name_4']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_4', $data['severity_color_4'])
	])
	->addRow((new CLabel(_('Disaster'), 'severity_name_5'))->setAsteriskMark(), [
		(new CTextBox('severity_name_5', $data['severity_name_5']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 32),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_5', $data['severity_color_5'])
	])
	->addRow(null)
	->addInfo(_('Custom severity names affect all locales and require manual translation!'));

$form = (new CForm())
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'trigseverity.update')
		->getUrl()
	)
	->addItem(
		(new CTabView())
			->addTab('severities', _('Trigger severities'), $severity_tab)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update')),
				[new CButton('resetDefaults', _('Reset defaults'))]
			))
	);

$widget->addItem($form)->show();

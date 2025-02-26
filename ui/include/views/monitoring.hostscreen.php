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

$this->includeJsFile('monitoring.hostscreen.js.php');
$web_layout_mode = CViewHelper::loadLayoutMode();

$screen_widget = (new CWidget())->setWebLayoutMode($web_layout_mode);

if (empty($data['screen']) || empty($data['host'])) {
	$screen_widget
		->setTitle(_('Screens'))
		->addItem(new CTableInfo());
}
else {
	$screen_widget->setTitle([
		$data['screen']['name'].' '._('on').' ',
		new CSpan($data['host']['name'])
	]);

	$url = (new CUrl('host_screen.php'))
		->setArgument('hostid', $data['hostid'])
		->setArgument('screenid', $data['screenid']);

	// host screen list
	if (!empty($data['screens'])) {
		$screen_select = (new CSelect('screenList'))
			->setId('screen-list')
			->setValue($url->toString());

		foreach ($data['screens'] as $screen) {
			$opt_value = $url
				->setArgument('screenid', $screen['screenid'])
				->toString();

			$screen_select->addOption(new CSelectOption($opt_value, $screen['name']));
		}

		$screen_widget->setControls((new CTag('nav', true,
			(new CForm('get'))
				->cleanItems()
				->setAttribute('aria-label', _('Main filter'))
				->addItem((new CList())
					->addItem($screen_select)
					->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
				)
			))
				->setAttribute('aria-label', _('Content controls'))
		);
	}

	// append screens to widget
	$screen_builder = new CScreenBuilder([
		'screen' => $data['screen'],
		'mode' => SCREEN_MODE_PREVIEW,
		'hostid' => $data['hostid'],
		'profileIdx' => $data['profileIdx'],
		'profileIdx2' => $data['profileIdx2'],
		'from' => $data['from'],
		'to' => $data['to']
	]);

	$screen_widget->addItem(
		(new CFilter(new CUrl()))
			->setProfile($data['profileIdx'], $data['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($screen_builder->timeline['from'], $screen_builder->timeline['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE)
	);

	$screen_widget->addItem((new CDiv($screen_builder->show()))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER));

	CScreenBuilder::insertScreenStandardJs($screen_builder->timeline);
}

$screen_widget->show();

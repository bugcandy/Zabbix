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


class CControllerFavouriteDelete extends CController {

	protected function checkInput() {
		$fields = [
			'object' =>		'fatal|required|in graphid,itemid,screenid,slideshowid,sysmapid',
			'objectid' =>	'fatal|required|id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => '']));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$profile = [
			'graphid' => 'web.favorite.graphids',
			'itemid' => 'web.favorite.graphids',
			'screenid' => 'web.favorite.screenids',
			'slideshowid' => 'web.favorite.screenids',
			'sysmapid' => 'web.favorite.sysmapids'
		];

		$widgetids = [
			'graphid' => WIDGET_FAV_GRAPHS,
			'itemid' => WIDGET_FAV_GRAPHS,
			'screenid' => WIDGET_FAV_SCREENS,
			'slideshowid' => WIDGET_FAV_SCREENS,
			'sysmapid' => WIDGET_FAV_MAPS
		];

		$object = $this->getInput('object');
		$objectid = $this->getInput('objectid');

		$data = [];

		DBstart();
		$result = CFavorite::remove($profile[$object], $objectid, $object);
		$result = DBend($result);

		if ($result) {
			$data['main_block'] =
				'if (jQuery(\'#addrm_fav\').length) {'."\n".
					'document.getElementById(\'addrm_fav\').title = \''._('Add to favourites').'\';'."\n".
					'document.getElementById(\'addrm_fav\').onclick = function() { add2favorites(\''.$object.'\', \''.$objectid.'\'); }'."\n".
					'switchElementClass(\'addrm_fav\', \'btn-remove-fav\', \'btn-add-fav\');'."\n".
				'}'."\n".
				'else {'."\n".
					'var $widgets = jQuery(".dashbrd-grid-container").dashboardGrid('."\n".
						'"getWidgetsBy", "type", "'.$widgetids[$object].'");'."\n".
					'jQuery.each($widgets, function(index, widget) {'."\n".
						'jQuery(".dashbrd-grid-container").dashboardGrid('."\n".
							'"refreshWidget", widget["widgetid"]);'."\n".
					'});'."\n".
				'}';
		}
		else {
			$data['main_block'] = '';
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}

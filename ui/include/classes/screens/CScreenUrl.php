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


class CScreenUrl extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		// prevent from resolving macros in configuration page
		if ($this->mode != SCREEN_MODE_PREVIEW && $this->mode != SCREEN_MODE_SLIDESHOW) {
			return $this->getOutput($this->prepareElement());
		}

		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid == 0) {
			return $this->getOutput((new CTableInfo())->setNoDataMessage(_('No host selected.')));
		}

		$resolveHostMacros = ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM || $this->isTemplatedScreen);

		$url = CMacrosResolverHelper::resolveWidgetURL([
			'config' => $resolveHostMacros ? 'widgetURL' : 'widgetURLUser',
			'url' => $this->screenitem['url'],
			'hostid' => $resolveHostMacros ? $this->hostid : 0
		]);

		$this->screenitem['url'] = $url ? $url : $this->screenitem['url'];

		return $this->getOutput($this->prepareElement());
	}

	/**
	 * @return CTag
	 */
	private function prepareElement() {
		if (CHtmlUrlValidator::validate($this->screenitem['url'], ['allow_user_macro' => false])) {
			$item = new CIFrame($this->screenitem['url'], $this->screenitem['width'], $this->screenitem['height'],
				'auto'
			);

			if (ZBX_IFRAME_SANDBOX !== false) {
				$item->setAttribute('sandbox', ZBX_IFRAME_SANDBOX);
			}

			return $item;
		}

		return makeMessageBox(ZBX_STYLE_MSG_BAD, [[
			'type' => 'error',
			'message' => _s('Provided URL "%1$s" is invalid.', $this->screenitem['url'])
		]]);
	}
}

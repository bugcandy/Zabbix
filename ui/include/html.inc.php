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


function italic($str) {
	if (is_array($str)) {
		foreach ($str as $key => $val) {
			if (is_string($val)) {
				$em = new CTag('em', true);
				$em->addItem($val);
				$str[$key] = $em;
			}
		}
	}
	elseif (is_string($str)) {
		$em = new CTag('em', true, '');
		$em->addItem($str);
		$str = $em;
	}
	return $str;
}

function bold($str) {
	if (is_array($str)) {
		foreach ($str as $key => $val) {
			if (is_string($val)) {
				$str[$key] = new CTag('b', true, $val);
			}
		}

		return $str;
	}

	return new CTag('b', true, $str);
}

function make_decoration($haystack, $needle, $class = null) {
	$result = $haystack;

	$tmpHaystack = mb_strtolower($haystack);
	$tmpNeedle = mb_strtolower($needle);
	$pos = mb_strpos($tmpHaystack, $tmpNeedle);

	if ($pos !== false) {
		$start = mb_substr($haystack, 0, $pos);
		$end = mb_substr($haystack, $pos + mb_strlen($needle));
		$found = mb_substr($haystack, $pos, mb_strlen($needle));

		if (is_null($class)) {
			$result = [$start, bold($found), $end];
		}
		else {
			$result = [$start, (new CSpan($found))->addClass($class), $end];
		}
	}

	return $result;
}

function prepareUrlParam($value, $name = null) {
	if (is_array($value)) {
		$result = '';

		foreach ($value as $key => $param) {
			$result .= prepareUrlParam($param, isset($name) ? $name.'['.$key.']' : $key);
		}
	}
	else {
		$result = '&'.$name.'='.urlencode($value);
	}

	return $result;
}

/**
 * Get ready for url params.
 *
 * @param mixed  $param				param name or array with data depend from $getFromRequest
 * @param bool   $getFromRequest	detect data source - input array or $_REQUEST variable
 * @param string $name				if $_REQUEST variable is used this variable not used
 *
 * @return string
 */
function url_param($param, $getFromRequest = true, $name = null) {
	if (is_array($param)) {
		if ($getFromRequest) {
			fatal_error(_('URL parameter cannot be array.'));
		}
	}
	else {
		if (is_null($name)) {
			if (!$getFromRequest) {
				fatal_error(_('URL parameter name is empty.'));
			}

			$name = $param;
		}
	}

	if ($getFromRequest) {
		$value =& $_REQUEST[$param];
	}
	else {
		$value =& $param;
	}

	return isset($value) ? prepareUrlParam($value, $name) : '';
}

function url_params(array $params) {
	$result = '';

	foreach ($params as $param) {
		$result .= url_param($param);
	}

	return $result;
}

function BR() {
	return new CTag('br');
}

function BULLET() {
	return new CHtmlEntity('&bullet;');
}

function COPYR() {
	return new CHtmlEntity('&copy;');
}

function HELLIP() {
	return new CHtmlEntity('&hellip;');
}

function LARR() {
	return new CHtmlEntity('&lArr;');
}

function NBSP() {
	return new CHtmlEntity('&nbsp;');
}

function NDASH() {
	return new CHtmlEntity('&ndash;');
}

function RARR() {
	return new CHtmlEntity('&rArr;');
}

function get_icon($type, $params = []) {
	switch ($type) {
		case 'favourite':
			if (CFavorite::exists($params['fav'], $params['elid'], $params['elname'])) {
				$icon = (new CRedirectButton(NBSP(), null))
					->addClass(ZBX_STYLE_BTN_REMOVE_FAV)
					->setTitle(_('Remove from favourites'))
					->onClick('rm4favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			else {
				$icon = (new CRedirectButton(NBSP(), null))
					->addClass(ZBX_STYLE_BTN_ADD_FAV)
					->setTitle(_('Add to favourites'))
					->onClick('add2favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			$icon->setId('addrm_fav');

			return $icon;

		case 'kioskmode':
			if ($params['mode'] == ZBX_LAYOUT_KIOSKMODE) {
				$icon = (new CButton(null, NBSP()))
					->setTitle(_('Normal view'))
					->setAttribute('data-layout-mode', ZBX_LAYOUT_NORMAL)
					->addClass(ZBX_LAYOUT_MODE)
					->addClass(ZBX_STYLE_BTN_DASHBRD_NORMAL)
					->addClass(ZBX_STYLE_BTN_MIN);
			}
			else {
				$icon = (new CButton(null, NBSP()))
					->setTitle(_('Kiosk mode'))
					->setAttribute('data-layout-mode', ZBX_LAYOUT_KIOSKMODE)
					->addClass(ZBX_LAYOUT_MODE)
					->addClass(ZBX_STYLE_BTN_KIOSK);
			}

			return $icon;

		case 'screenconf':
			return (new CRedirectButton(NBSP(), null))
				->addClass(ZBX_STYLE_BTN_CONF)
				->setTitle(_('Refresh interval'));

		case 'overviewhelp':
			return (new CRedirectButton(NBSP(), null))
				->addClass(ZBX_STYLE_BTN_INFO);
	}
}

/**
 * Create CDiv with host/template information and references to it's elements
 *
 * @param string $currentElement
 * @param int $hostid
 * @param int $lld_ruleid
 *
 * @return object
 */
function get_header_host_table($current_element, $hostid, $lld_ruleid = 0) {
	$options = [
		'output' => [
			'hostid', 'status', 'name', 'maintenance_status', 'flags', 'available', 'snmp_available',
			'jmx_available', 'ipmi_available', 'error', 'snmp_error', 'jmx_error', 'ipmi_error'
		],
		'selectHostDiscovery' => ['ts_delete'],
		'hostids' => [$hostid],
		'editable' => true
	];
	if ($lld_ruleid == 0) {
		$options['selectApplications'] = API_OUTPUT_COUNT;
		$options['selectItems'] = API_OUTPUT_COUNT;
		$options['selectTriggers'] = API_OUTPUT_COUNT;
		$options['selectGraphs'] = API_OUTPUT_COUNT;
		$options['selectDiscoveries'] = API_OUTPUT_COUNT;
		$options['selectHttpTests'] = API_OUTPUT_COUNT;
	}

	// get hosts
	$db_host = API::Host()->get($options);

	if (!$db_host) {
		$options = [
			'output' => ['templateid', 'name', 'flags'],
			'templateids' => [$hostid],
			'editable' => true
		];
		if ($lld_ruleid == 0) {
			$options['selectApplications'] = API_OUTPUT_COUNT;
			$options['selectItems'] = API_OUTPUT_COUNT;
			$options['selectTriggers'] = API_OUTPUT_COUNT;
			$options['selectGraphs'] = API_OUTPUT_COUNT;
			$options['selectScreens'] = API_OUTPUT_COUNT;
			$options['selectDiscoveries'] = API_OUTPUT_COUNT;
			$options['selectHttpTests'] = API_OUTPUT_COUNT;
		}

		// get templates
		$db_host = API::Template()->get($options);

		$is_template = true;
	}
	else {
		$is_template = false;
	}

	if (!$db_host) {
		return null;
	}

	$db_host = reset($db_host);

	// get lld-rules
	if ($lld_ruleid != 0) {
		$db_discovery_rule = API::DiscoveryRule()->get([
			'output' => ['name'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHostPrototypes' => API_OUTPUT_COUNT,
			'itemids' => [$lld_ruleid],
			'editable' => true
		]);
		$db_discovery_rule = reset($db_discovery_rule);
	}

	/*
	 * list and host (template) name
	 */
	$list = (new CList())
		->addClass(ZBX_STYLE_OBJECT_GROUP)
		->addClass(ZBX_STYLE_FILTER_BREADCRUMB);

	$breadcrumbs = (new CListItem(null))
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _x('Hierarchy', 'screen reader'));

	if ($is_template) {
		$template = new CSpan(
			new CLink($db_host['name'], 'templates.php?form=update&templateid='.$db_host['templateid'])
		);

		if ($current_element === '') {
			$template->addClass(ZBX_STYLE_SELECTED);
		}

		$breadcrumbs->addItem([
			new CSpan(new CLink(_('All templates'), new CUrl('templates.php'))),
			'/',
			$template
		]);

		$db_host['hostid'] = $db_host['templateid'];
		$list->addItem($breadcrumbs);
	}
	else {
		switch ($db_host['status']) {
			case HOST_STATUS_MONITORED:
				if ($db_host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$status = (new CSpan(_('In maintenance')))->addClass(ZBX_STYLE_ORANGE);
				}
				else {
					$status = (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN);
				}
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
				break;
			default:
				$status = _('Unknown');
				break;
		}

		$host = new CSpan(new CLink($db_host['name'],
			'hosts.php?form=update&hostid='.$db_host['hostid']
		));

		if ($current_element === '') {
			$host->addClass(ZBX_STYLE_SELECTED);
		}

		$breadcrumbs->addItem([
			new CSpan(new CLink(_('All hosts'), new CUrl('hosts.php'))),
			'/',
			$host
		]);
		$list->addItem($breadcrumbs);
		$list->addItem($status);
		$list->addItem(getHostAvailabilityTable($db_host));

		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $db_host['hostDiscovery']['ts_delete'] != 0) {
			$info_icons = [getHostLifetimeIndicator(time(), $db_host['hostDiscovery']['ts_delete'])];
			$list->addItem(makeInformationList($info_icons));
		}
	}

	$content_menu = (new CList())
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _('Content menu'));

	/*
	 * the count of rows
	 */
	if ($lld_ruleid == 0) {
		// applications
		$applications = new CSpan([
			new CLink(_('Applications'),
				(new CUrl('applications.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['applications'])
		]);
		if ($current_element == 'applications') {
			$applications->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($applications);

		// items
		$items = new CSpan([
			new CLink(_('Items'),
				(new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['items'])
		]);
		if ($current_element == 'items') {
			$items->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($items);

		// triggers
		$triggers = new CSpan([
			new CLink(_('Triggers'),
				(new CUrl('triggers.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['triggers'])
		]);
		if ($current_element == 'triggers') {
			$triggers->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($triggers);

		// graphs
		$graphs = new CSpan([
			new CLink(_('Graphs'), (new CUrl('graphs.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['graphs'])
		]);
		if ($current_element == 'graphs') {
			$graphs->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graphs);

		// screens
		if ($is_template) {
			$screens = new CSpan([
				new CLink(_('Screens'), 'screenconf.php?templateid='.$db_host['hostid']),
				CViewHelper::showNum($db_host['screens'])
			]);
			if ($current_element == 'screens') {
				$screens->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($screens);
		}

		// discovery rules
		$lld_rules = new CSpan([
			new CLink(_('Discovery rules'), (new CUrl('host_discovery.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['discoveries'])
		]);
		if ($current_element == 'discoveries') {
			$lld_rules->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($lld_rules);

		// web scenarios
		$http_tests = new CSpan([
			new CLink(_('Web scenarios'),
				(new CUrl('httpconf.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
			),
			CViewHelper::showNum($db_host['httpTests'])
		]);
		if ($current_element == 'web') {
			$http_tests->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($http_tests);
	}
	else {
		$discovery_rule = (new CSpan())->addItem(
			new CLink(
				$db_discovery_rule['name'],
				'host_discovery.php?form=update&itemid='.$db_discovery_rule['itemid']
			)
		);

		if ($current_element == 'discoveries') {
			$discovery_rule->addClass(ZBX_STYLE_SELECTED);
		}

		$list->addItem([
			(new CSpan())->addItem(
				new CLink(_('Discovery list'), (new CUrl('host_discovery.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
				)
			),
			'/',
			$discovery_rule
		]);

		// item prototypes
		$item_prototypes = new CSpan([
			new CLink(_('Item prototypes'), 'disc_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']),
			CViewHelper::showNum($db_discovery_rule['items'])
		]);
		if ($current_element == 'items') {
			$item_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($item_prototypes);

		// trigger prototypes
		$trigger_prototypes = new CSpan([
			new CLink(_('Trigger prototypes'),
				'trigger_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']
			),
			CViewHelper::showNum($db_discovery_rule['triggers'])
		]);
		if ($current_element == 'triggers') {
			$trigger_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($trigger_prototypes);

		// graph prototypes
		$graph_prototypes = new CSpan([
			new CLink(_('Graph prototypes'), 'graphs.php?parent_discoveryid='.$db_discovery_rule['itemid']),
			CViewHelper::showNum($db_discovery_rule['graphs'])
		]);
		if ($current_element == 'graphs') {
			$graph_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graph_prototypes);

		// host prototypes
		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			$host_prototypes = new CSpan([
				new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']),
				CViewHelper::showNum($db_discovery_rule['hostPrototypes'])
			]);
			if ($current_element == 'hosts') {
				$host_prototypes->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($host_prototypes);
		}
	}

	$list->addItem($content_menu);

	return $list;
}

/**
 * Create breadcrumbs header object with sysmap parents information.
 *
 * @param int    $sysmapid      Used as value for sysmaid in map link generation.
 * @param string $name          Used as label for map link generation.
 * @param int    $severity_min  Used as value for severity_min in map link generation.
 *
 * @return object
 */
function get_header_sysmap_table($sysmapid, $name, $severity_min) {
	$list = (new CList())
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _x('Hierarchy', 'screen reader'))
		->addClass(ZBX_STYLE_OBJECT_GROUP)
		->addClass(ZBX_STYLE_FILTER_BREADCRUMB)
		->addItem([
			(new CSpan())->addItem(new CLink(_('All maps'), new CUrl('sysmaps.php'))),
			'/',
			(new CSpan())
				->addClass(ZBX_STYLE_SELECTED)
				->addItem(
					new CLink($name, (new CUrl('zabbix.php'))
						->setArgument('action', 'map.view')
						->setArgument('sysmapid', $sysmapid)
						->setArgument('severity_min', $severity_min)
					)
				)
		]);

	// get map parent maps
	$parent_sysmaps = get_parent_sysmaps($sysmapid);
	if ($parent_sysmaps) {
		$parent_maps = (new CList())
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _('Upper level maps'))
			->addClass(ZBX_STYLE_FILTER_BREADCRUMB)
			->addClass(ZBX_STYLE_OBJECT_GROUP)
			->addItem((new CSpan())->addItem(_('Upper level maps').':'));

		foreach ($parent_sysmaps as $parent_sysmap) {
			$parent_maps->addItem((new CSpan())->addItem(
				new CLink($parent_sysmap['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'map.view')
					->setArgument('sysmapid', $parent_sysmap['sysmapid'])
					->setArgument('severity_min', $severity_min)
				))
			);
		}

		return new CHorList([$list, $parent_maps]);
	}

	return $list;
}

/**
 * Renders a form footer with the given buttons.
 *
 * @param CButtonInterface 		$main_button	main button that will be displayed on the left
 * @param CButtonInterface[] 	$other_buttons
 *
 * @return CDiv
 *
 * @throws InvalidArgumentException	if an element of $other_buttons contain something other than CButtonInterface
 */
function makeFormFooter(CButtonInterface $main_button = null, array $other_buttons = []) {
	foreach ($other_buttons as $other_button) {
		$other_button->addClass(ZBX_STYLE_BTN_ALT);
	}

	if ($main_button !== null) {
		array_unshift($other_buttons, $main_button);
	}

	return (new CList())
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addItem([
			(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
			(new CDiv($other_buttons))
				->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
				->addClass('tfoot-buttons')
		]);
}

/**
 * Returns zbx, snmp, jmx, ipmi availability status icons and the discovered host lifetime indicator.
 *
 * @param array $host		an array of host data
 *
 * @return CDiv
 */
function getHostAvailabilityTable($host) {
	$container = (new CDiv())->addClass(ZBX_STYLE_STATUS_CONTAINER);

	foreach (['zbx' => '', 'snmp' => 'snmp_', 'jmx' => 'jmx_', 'ipmi' => 'ipmi_'] as $type => $prefix) {
		switch ($host[$prefix.'available']) {
			case HOST_AVAILABLE_TRUE:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;
			case HOST_AVAILABLE_FALSE:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_RED);

				if ($host[$prefix.'error'] !== '') {
					$ai
						->addClass(ZBX_STYLE_CURSOR_POINTER)
						->setHint($host[$prefix.'error'], ZBX_STYLE_RED);
				}

				break;
			case HOST_AVAILABLE_UNKNOWN:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_GREY);
				break;
		}
		$container->addItem($ai);
	}

	return $container;
}

/**
 * Returns the discovered host group lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the host group
 *
 * @return CDiv
 */
function getHostGroupLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The host group is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The host group is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered host lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the host
 *
 * @return CDiv
 */
function getHostLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The host is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The host is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered application lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the application
 *
 * @return CDiv
 */
function getApplicationLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The application is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The application is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered graph lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the graph
 *
 * @return CDiv
 */
function getGraphLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The graph is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The graph is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered trigger lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the trigger
 *
 * @return CDiv
 */
function getTriggerLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The trigger is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The trigger is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered item lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the item
 *
 * @return CDiv
 */
function getItemLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The item is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The item is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($current_time, $ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

function makeServerStatusOutput() {
	return (new CTag('output', true))
		->setId('msg-global-footer')
		->addClass(ZBX_STYLE_MSG_GLOBAL_FOOTER)
		->addClass(ZBX_STYLE_MSG_WARNING);
}

/**
* Make logo of the specified type.
*
* @param int $type  LOGO_TYPE_NORMAL | LOGO_TYPE_SIDEBAR | LOGO_TYPE_SIDEBAR_COMPACT.
*
* @return CTag
*/
function makeLogo(int $type): ?CTag {
	static $zabbix_logo_classes = [
		LOGO_TYPE_NORMAL => ZBX_STYLE_ZABBIX_LOGO,
		LOGO_TYPE_SIDEBAR => ZBX_STYLE_ZABBIX_SIDEBAR_LOGO,
		LOGO_TYPE_SIDEBAR_COMPACT => ZBX_STYLE_ZABBIX_SIDEBAR_LOGO_COMPACT
	];

	$brand_logo = CBrandHelper::getLogo($type);

	if ($brand_logo !== null) {
		return (new CImg($brand_logo));
	}
	else {
		return (new CDiv())->addClass($zabbix_logo_classes[$type]);
	}
}

/**
 * Renders a page footer.
 *
 * @param bool $with_version
 *
 * @return CDiv
 */
function makePageFooter($with_version = true) {
	return (new CTag('footer', true, CBrandHelper::getFooterContent($with_version)))
		->setAttribute('role', 'contentinfo');
}

/**
 * Get drop-down submenu item list for the Administration->General section.
 *
 * @return array  Menu definition for CWidget::setTitleSubmenu.
 */
function getAdministrationGeneralSubmenu() {
	$gui_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'gui.edit')
		->getUrl();

	$autoreg_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'autoreg.edit')
		->getUrl();

	$housekeeping_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'housekeeping.edit')
		->getUrl();

	$image_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'image.list')
		->getUrl();

	$iconmap_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.list')
		->getUrl();

	$regex_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'regex.list')
		->getUrl();

	$macros_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'macros.edit')
		->getUrl();

	$valuemap_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'valuemap.list')
		->getUrl();

	$workingtime_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'workingtime.edit')
		->getUrl();

	$trigseverity_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'trigseverity.edit')
		->getUrl();

	$trigdisplay_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'trigdisplay.edit')
		->getUrl();

	$modules_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'module.list')
		->getUrl();

	$miscconfig_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'miscconfig.edit')
		->getUrl();

	return [
		'main_section' => [
			'items' => [
				$gui_url          => _('GUI'),
				$autoreg_url      => _('Autoregistration'),
				$housekeeping_url => _('Housekeeping'),
				$image_url        => _('Images'),
				$iconmap_url      => _('Icon mapping'),
				$regex_url        => _('Regular expressions'),
				$macros_url       => _('Macros'),
				$valuemap_url     => _('Value mapping'),
				$workingtime_url  => _('Working time'),
				$trigseverity_url => _('Trigger severities'),
				$trigdisplay_url  => _('Trigger displaying options'),
				$modules_url      => _('Modules'),
				$miscconfig_url   => _('Other')
			]
		]
	];
}

/**
 * Renders an icon list.
 *
 * @param array $info_icons  The list of information icons.
 *
 * @return CDiv|string
 */
function makeInformationList($info_icons) {
	return $info_icons ? (new CDiv($info_icons))->addClass(ZBX_STYLE_REL_CONTAINER) : '';
}

/**
 * Renders an information icon like green [i] with message.
 *
 * @param string $message
 *
 * @return CLink
 */
function makeInformationIcon($message) {
	return (new CLink())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_GREEN)
		->setHint($message, ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders an icon for host in maintenance.
 *
 * @param int    $type         Type of the maintenance.
 * @param string $name         Name of the maintenance.
 * @param string $description  Description of the maintenance.
 *
 * @return CLink
 */
function makeMaintenanceIcon($type, $name, $description) {
	$hint = $name.' ['.($type
		? _('Maintenance without data collection')
		: _('Maintenance with data collection')).']';

	if ($description !== '') {
		$hint .= "\n".$description;
	}

	return (new CLink())
		->addClass(ZBX_STYLE_ICON_MAINTENANCE)
		->setHint($hint);
}

/**
 * Renders an icon for suppressed problem.
 *
 * @param array  $icon_data
 * @param string $icon_data[]['suppress_until']    Time until the problem is suppressed.
 * @param string $icon_data[]['maintenance_name']  Name of the maintenance.
 *
 * @return CLink
 */
function makeSuppressedProblemIcon(array $icon_data) {
	$suppress_until = max(array_column($icon_data, 'suppress_until'));

	CArrayHelper::sort($icon_data, ['maintenance_name']);
	$maintenance_names = implode(', ', array_column($icon_data, 'maintenance_name'));

	return (new CLink())
		->addClass(ZBX_STYLE_ICON_INVISIBLE)
		->setHint(
			_s('Suppressed till: %1$s', ($suppress_until < strtotime('tomorrow'))
				? zbx_date2str(TIME_FORMAT, $suppress_until)
				: zbx_date2str(DATE_TIME_FORMAT, $suppress_until)
			).
			"\n".
			_s('Maintenance: %1$s', $maintenance_names)
		);
}

/**
 * Renders an action icon.
 *
 * @param array  $icon_data
 * @param string $icon_data[icon]    Icon style.
 * @param array  $icon_data[hint]    Hintbox content (optional).
 * @param bool   $icon_data[button]  Use button element (optional).
 * @param int    $icon_data[num]     Number displayed over the icon (optional).
 *
 * @return CTag  Returns CSpan or CButton depending on boolean $icon_data['button'] parameter
 */
function makeActionIcon(array $icon_data): CTag {

	if (array_key_exists('button', $icon_data) && $icon_data['button']) {
		$icon = (new CButton(null))->addClass($icon_data['icon']);
	}
	else {
		$icon = (new CSpan())->addClass($icon_data['icon']);
	}

	if (array_key_exists('num', $icon_data)) {
		if ($icon_data['num'] > 99) {
			$icon_data['num'] = '99+';
		}
		$icon->setAttribute('data-count', $icon_data['num']);
	}

	if (array_key_exists('hint', $icon_data)) {
		$icon
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint($icon_data['hint'], '', true, 'max-width: '.ZBX_ACTIONS_POPUP_MAX_WIDTH.'px;');
	}
	elseif (array_key_exists('title', $icon_data)) {
		$icon->setTitle($icon_data['title']);
	}

	if (array_key_exists('aria-label', $icon_data)) {
		$icon
			->addItem($icon_data['aria-label'])
			->addClass(ZBX_STYLE_INLINE_SR_ONLY);
	}

	return $icon;
}

/**
 * Renders an icon for a description.
 *
 * @param string $description
 *
 * @return CLink
 */
function makeDescriptionIcon($description) {
	return (new CLink())
		->addClass(ZBX_STYLE_ICON_DESCRIPTION)
		->setHint(zbx_str2links($description), ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders an error icon like red [i] with error message
 *
 * @param string $error
 *
 * @return CLink
 */
function makeErrorIcon($error) {
	return (new CLink())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_RED)
		->setHint($error, ZBX_STYLE_HINTBOX_WRAP." ".ZBX_STYLE_RED);
}

/**
 * Renders a warning icon like yellow [i] with error message
 *
 * @param string $error
 *
 * @return CLink
 */
function makeWarningIcon($error) {
	return (new CLink())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_YELLOW)
		->setHint($error, ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Returns css for trigger severity backgrounds.
 *
 * @param array $config
 * @param array $config[severity_color_0]
 * @param array $config[severity_color_1]
 * @param array $config[severity_color_2]
 * @param array $config[severity_color_3]
 * @param array $config[severity_color_4]
 * @param array $config[severity_color_5]
 *
 * @return string
 */
function getTriggerSeverityCss($config) {
	$css = '';

	$severities = [
		ZBX_STYLE_NA_BG => $config['severity_color_0'],
		ZBX_STYLE_INFO_BG => $config['severity_color_1'],
		ZBX_STYLE_WARNING_BG => $config['severity_color_2'],
		ZBX_STYLE_AVERAGE_BG => $config['severity_color_3'],
		ZBX_STYLE_HIGH_BG => $config['severity_color_4'],
		ZBX_STYLE_DISASTER_BG => $config['severity_color_5']
	];

	foreach ($severities as $class => $color) {
		$css .= '.'.$class.', .'.$class.' input[type="radio"]:checked + label, .'.$class.':before, .flh-'.$class.
			', .status-'.$class.', .status-'.$class.':before { background-color: #'.$color.' }'."\n";
	}

	return $css;
}

/**
 * Returns css for trigger status colors, if those are customized.
 *
 * @param array $config
 * @param array $config[custom_color]
 * @param array $config[problem_unack_color]
 * @param array $config[problem_ack_color]
 * @param array $config[ok_unack_color]
 * @param array $config[ok_ack_color]
 *
 * @return string
 */
function getTriggerStatusCss($config) {
	$css = '';

	if ($config['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED) {
		$event_statuses = [
			ZBX_STYLE_PROBLEM_UNACK_FG => $config['problem_unack_color'],
			ZBX_STYLE_PROBLEM_ACK_FG => $config['problem_ack_color'],
			ZBX_STYLE_OK_UNACK_FG => $config['ok_unack_color'],
			ZBX_STYLE_OK_ACK_FG => $config['ok_ack_color']
		];

		foreach ($event_statuses as $class => $color) {
			$css .= '.' . $class . ' {color: #' . $color . ';}' . "\n";
		}
	}

	return $css;
}

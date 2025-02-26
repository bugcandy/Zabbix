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
	->setTitle(_('Discovery rules'))
	->addItem(get_header_host_table('discoveries', $data['hostid'],
		array_key_exists('itemid', $data) ? $data['itemid'] : 0
	));

$form = (new CForm())
	->addVar('form_refresh', $data['form_refresh'] + 1)
	->setName('itemForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid']);

if (!empty($data['itemid'])) {
	$form->addVar('itemid', $data['itemid']);
}

$form_list = new CFormList('itemFormList');
if (!empty($data['templates'])) {
	$form_list->addRow(_('Parent discovery rules'), $data['templates']);
}

$form_list
	// Append name field to form list.
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	// Append type select to form list.
	->addRow(
		new CLabel(_('Type'), 'label-type'),
		(new CSelect('type'))
			->setValue($data['type'])
			->setId('type')
			->setFocusableElementId('label-type')
			->addOptions(CSelect::createOptionsFromArray($data['types']))
			->setReadonly($data['limited'])
	)
	// Append key to form list.
	->addRow(
		(new CLabel(_('Key'), 'key'))->setAsteriskMark(),
		(new CTextBox('key', $data['key'], $data['limited'], DB::getFieldLength('item_discovery', 'key_')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	// Append ITEM_TYPE_HTTPAGENT URL field to form list.
	->addRow(
		(new CLabel(_('URL'), 'url'))->setAsteriskMark(),
		[
			(new CTextBox('url', $data['url'], $data['limited'], DB::getFieldLength('items', 'url')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('httpcheck_parseurl', _('Parse')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->setEnabled(!$data['limited'])
				->setAttribute('data-action', 'parse_url')
		],
		'url_row'
	);

// Append ITEM_TYPE_HTTPAGENT Query fields to form list.
$query_fields_data = [];

if (is_array($data['query_fields']) && $data['query_fields']) {
	foreach ($data['query_fields'] as $pair) {
		$query_fields_data[] = ['name' => key($pair), 'value' => reset($pair)];
	}
}
elseif (!$data['limited']) {
	$query_fields_data[] = ['name' => '', 'value' => ''];
}

$query_fields = (new CTag('script', true))->setAttribute('type', 'text/json');
$query_fields->items = [json_encode($query_fields_data)];

$form_list
	->addRow(
		new CLabel(_('Query fields'), 'query_fields_pairs'),
		(new CDiv([
			(new CTable())
				->setAttribute('style', 'width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
				->setFooter(new CRow(
					(new CCol(
						(new CButton(null, _('Add')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setEnabled(!$data['limited'])
							->setAttribute('data-row-action', 'add_row')
					))->setColSpan(5)
				)),
			(new CTag('script', true))
				->setAttribute('type', 'text/x-jquery-tmpl')
				->addItem(new CRow([
					(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextBox('query_fields[name][#{index}]', '#{name}', $data['limited']))
						->setAttribute('placeholder', _('name'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
					RARR(),
					(new CTextBox('query_fields[value][#{index}]', '#{value}', $data['limited']))
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
					(new CButton(null, _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled(!$data['limited'])
						->setAttribute('data-row-action', 'remove_row')
				])),
			$query_fields
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('query_fields_pairs')
			->setAttribute('data-sortable-pairs-table',  $data['limited'] ? '0' : '1')
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
		'query_fields_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Request type to form list.
	->addRow(
		new CLabel(_('Request type'), 'label-request-method'),
		(new CSelect('request_method'))
			->addOptions(CSelect::createOptionsFromArray([
				HTTPCHECK_REQUEST_GET => 'GET',
				HTTPCHECK_REQUEST_POST => 'POST',
				HTTPCHECK_REQUEST_PUT => 'PUT',
				HTTPCHECK_REQUEST_HEAD => 'HEAD'
			]))
			->setReadonly($data['limited'])
			->setFocusableElementId('label-request-method')
			->setId('request_method')
			->setValue($data['request_method']),
		'request_method_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Timeout field to form list.
	->addRow(
		(new CLabel(_('Timeout'), 'timeout'))->setAsteriskMark(),
		(new CTextBox('timeout', $data['timeout'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'timeout_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Request body type to form list.
	->addRow(
		new CLabel(_('Request body type'), 'post_type'),
		(new CRadioButtonList('post_type', (int) $data['post_type']))
			->addValue(_('Raw data'), ZBX_POSTTYPE_RAW)
			->addValue(_('JSON data'), ZBX_POSTTYPE_JSON)
			->addValue(_('XML data'), ZBX_POSTTYPE_XML)
			->setEnabled(!$data['limited'])
			->setModern(true),
		'post_type_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Request body to form list.
	->addRow(
		new CLabel(_('Request body'), 'posts'),
		(new CTextArea('posts', $data['posts'], ['readonly' =>  $data['limited']]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'posts_row'
	);

// Append ITEM_TYPE_HTTPAGENT Headers fields to form list.
$headers_data = [];

if (is_array($data['headers']) && $data['headers']) {
	foreach ($data['headers'] as $pair) {
		$headers_data[] = ['name' => key($pair), 'value' => reset($pair)];
	}
}
elseif (!$data['limited']) {
	$headers_data[] = ['name' => '', 'value' => ''];
}
$headers = (new CTag('script', true))->setAttribute('type', 'text/json');
$headers->items = [json_encode($headers_data)];

$form_list
	->addRow(
		new CLabel(_('Headers'), 'headers_pairs'),
		(new CDiv([
			(new CTable())
				->setAttribute('style', 'width: 100%;')
				->setHeader(['', _('Name'), '', _('Value'), ''])
				->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
				->setFooter(new CRow(
					(new CCol(
						(new CButton(null, _('Add')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setEnabled(!$data['limited'])
							->setAttribute('data-row-action', 'add_row')
					))->setColSpan(5)
				)),
			(new CTag('script', true))
				->setAttribute('type', 'text/x-jquery-tmpl')
				->addItem(new CRow([
					(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CTextBox('headers[name][#{index}]', '#{name}', $data['limited']))
						->setAttribute('placeholder', _('name'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
					RARR(),
					(new CTextBox('headers[value][#{index}]', '#{value}', $data['limited'], 2000))
						->setAttribute('placeholder', _('value'))
						->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
					(new CButton(null, _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled(!$data['limited'])
						->setAttribute('data-row-action', 'remove_row')
				])),
			$headers
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setId('headers_pairs')
			->setAttribute('data-sortable-pairs-table', $data['limited'] ? '0' : '1')
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH . 'px;'),
		'headers_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Required status codes to form list.
	->addRow(
		new CLabel(_('Required status codes'), 'status_codes'),
		(new CTextBox('status_codes', $data['status_codes'], $data['limited']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'status_codes_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Follow redirects to form list.
	->addRow(
		new CLabel(_('Follow redirects'), 'follow_redirects'),
		(new CCheckBox('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON))
			->setEnabled(!$data['limited'])
			->setChecked($data['follow_redirects'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON),
		'follow_redirects_row'
	)
	// Append ITEM_TYPE_HTTPAGENT Retrieve mode to form list.
	->addRow(
		new CLabel(_('Retrieve mode'), 'retrieve_mode'),
		(new CRadioButtonList('retrieve_mode', (int) $data['retrieve_mode']))
			->addValue(_('Body'), HTTPTEST_STEP_RETRIEVE_MODE_CONTENT)
			->addValue(_('Headers'), HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)
			->addValue(_('Body and headers'), HTTPTEST_STEP_RETRIEVE_MODE_BOTH)
			->setEnabled(!($data['limited'] || $data['request_method'] == HTTPCHECK_REQUEST_HEAD))
			->setModern(true),
		'retrieve_mode_row'
	)
	// Append ITEM_TYPE_HTTPAGENT HTTP proxy to form list.
	->addRow(
		new CLabel(_('HTTP proxy'), 'http_proxy'),
		(new CTextBox('http_proxy', $data['http_proxy'], $data['limited'], DB::getFieldLength('items', 'http_proxy')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', '[protocol://][user[:password]@]proxy.example.com[:port]')
			->disableAutocomplete(),
		'http_proxy_row'
	)
	// Append ITEM_TYPE_HTTPAGENT HTTP authentication to form list.
	->addRow(
		new CLabel(_('HTTP authentication'), 'label-http-authtype'),
		(new CSelect('http_authtype'))
			->setValue($data['http_authtype'])
			->setId('http_authtype')
			->setFocusableElementId('label-http-authtype')
			->addOptions(CSelect::createOptionsFromArray([
				HTTPTEST_AUTH_NONE => _('None'),
				HTTPTEST_AUTH_BASIC => _('Basic'),
				HTTPTEST_AUTH_NTLM => _('NTLM'),
				HTTPTEST_AUTH_KERBEROS => _('Kerberos')
			]))
			->setReadonly($data['limited']),
		'http_authtype_row'
	)
	// Append ITEM_TYPE_HTTPAGENT User name to form list.
	->addRow(
		new CLabel(_('User name'), 'http_username'),
		(new CTextBox('http_username', $data['http_username'], $data['limited'],
			DB::getFieldLength('items', 'username')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableAutocomplete(),
		'http_username_row'
		)
	// Append ITEM_TYPE_HTTPAGENT Password to form list.
	->addRow(
		new CLabel(_('Password'), 'http_password'),
		(new CTextBox('http_password', $data['http_password'], $data['limited'],
				DB::getFieldLength('items', 'password')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableAutocomplete(),
		'http_password_row'
	)
	// Append ITEM_TYPE_HTTPAGENT SSL verify peer to form list.
	->addRow(
		new CLabel(_('SSL verify peer'), 'verify_peer'),
		(new CCheckBox('verify_peer', HTTPTEST_VERIFY_PEER_ON))
			->setEnabled(!$data['limited'])
			->setChecked($data['verify_peer'] == HTTPTEST_VERIFY_PEER_ON),
		'verify_peer_row'
	)
	// Append ITEM_TYPE_HTTPAGENT SSL verify host to form list.
	->addRow(
		new CLabel(_('SSL verify host'), 'verify_host'),
		(new CCheckBox('verify_host', HTTPTEST_VERIFY_HOST_ON))
			->setEnabled(!$data['limited'])
			->setChecked($data['verify_host'] == HTTPTEST_VERIFY_HOST_ON),
		'verify_host_row'
	)
	// Append ITEM_TYPE_HTTPAGENT SSL certificate file to form list.
	->addRow(
		new CLabel(_('SSL certificate file'), 'ssl_cert_file'),
		(new CTextBox('ssl_cert_file', $data['ssl_cert_file'], $data['limited'],
			DB::getFieldLength('items', 'ssl_cert_file')
		))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'ssl_cert_file_row'
	)
	// Append ITEM_TYPE_HTTPAGENT SSL key file to form list.
	->addRow(
		new CLabel(_('SSL key file'), 'ssl_key_file'),
		(new CTextBox('ssl_key_file', $data['ssl_key_file'], $data['limited'], DB::getFieldLength('items', 'ssl_key_file')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'ssl_key_file_row'
	)
	// Append ITEM_TYPE_HTTPAGENT SSL key password to form list.
	->addRow(
		new CLabel(_('SSL key password'), 'ssl_key_password'),
		(new CTextBox('ssl_key_password', $data['ssl_key_password'], $data['limited'],
			DB::getFieldLength('items', 'ssl_key_password')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->disableAutocomplete(),
		'ssl_key_password_row'
	)
	// Append master item select to form list.
	->addRow(
		(new CLabel(_('Master item'), 'master_itemid_ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'master_itemid',
			'object_name' => 'items',
			'multiple' => false,
			'disabled' => $data['limited'],
			'data' => ($data['master_itemid'] > 0)
				? [
					[
						'id' => $data['master_itemid'],
						'prefix' => $data['host']['name'].NAME_DELIMITER,
						'name' => $data['master_itemname']
					]
				]
				: [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'master_itemid',
					'hostid' => $data['hostid'],
					'webitems' => true,
					'normal_only' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'row_master_item'
	);

// Append interfaces to form list.
if (!empty($data['interfaces'])) {
	$select_interface = getInterfaceSelect($data['interfaces'])
		->setId('interface-select')
		->setValue($data['interfaceid'])
		->addClass(ZBX_STYLE_ZSELECT_HOST_INTERFACE)
		->setFocusableElementId('interfaceid')
		->setAriaRequired();

	$form_list->addRow((new CLabel(_('Host interface'), 'interfaceid'))->setAsteriskMark(),
		[
			$select_interface,
			(new CSpan(_('No interface found')))
				->addClass(ZBX_STYLE_RED)
				->setId('interface_not_defined')
				->setAttribute('style', 'display: none;')
		], 'interface_row'
	);
	$form->addVar('selectedInterfaceId', $data['interfaceid']);
}
$form_list
	->addRow(
		(new CLabel(_('SNMP OID'), 'snmp_oid'))->setAsteriskMark(),
		(new CTextBox('snmp_oid', $data['snmp_oid'], $data['limited'], 512))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', '[IF-MIB::]ifInOctets.1')
			->setAriaRequired(),
		'row_snmp_oid'
	);

$form_list
	->addRow(_('IPMI sensor'),
		(new CTextBox('ipmi_sensor', $data['ipmi_sensor'], $data['limited'], 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'row_ipmi_sensor'
	)
	// Append authentication method to form list.
	->addRow(new CLabel(_('Authentication method'), 'label-authtype'),
		(new CSelect('authtype'))
			->setId('authtype')
			->setFocusableElementId('label-authtype')
			->setValue($data['authtype'])
			->addOption(new CSelectOption(ITEM_AUTHTYPE_PASSWORD, _('Password')))
			->addOption(new CSelectOption(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'))),
		'row_authtype'
	)
	->addRow((new CLabel(_('JMX endpoint'), 'jmx_endpoint'))->setAsteriskMark(),
		(new CTextBox('jmx_endpoint', $data['jmx_endpoint'], false, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'row_jmx_endpoint'
	)
	->addRow(_('User name'),
		(new CTextBox('username', $data['username'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete(),
		'row_username'
	)
	->addRow(
		(new CLabel(_('Public key file'), 'publickey'))->setAsteriskMark(),
		(new CTextBox('publickey', $data['publickey'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'row_publickey'
	)
	->addRow(
		(new CLabel(_('Private key file'), 'privatekey'))->setAsteriskMark(),
		(new CTextBox('privatekey', $data['privatekey'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'row_privatekey'
	)
	->addRow(_('Password'),
		(new CTextBox('password', $data['password'], false, 64))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->disableAutocomplete(),
		'row_password'
	)
	->addRow(
		(new CLabel(_('Executed script'), 'params_es'))->setAsteriskMark(),
		(new CTextArea('params_es', $data['params']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'label_executed_script'
	)
	->addRow(
		(new CLabel(_('SQL query'), 'params_ap'))->setAsteriskMark(),
		(new CTextArea('params_ap', $data['params']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		'label_params'
	)
	->addRow((new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
		(new CTextBox('delay', $data['delay']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'row_delay'
	);

// Append delay_flex to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay']))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period']))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', ''))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', ''))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', ''))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule']))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$delayFlexTable->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

$delayFlexTable->addRow([(new CButton('interval_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')
]);

$form_list
	->addRow(_('Custom intervals'),
		(new CDiv($delayFlexTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
		'row_flex_intervals'
	)
	->addRow((new CLabel(_('Keep lost resources period'), 'lifetime'))->setAsteriskMark(),
		(new CTextBox('lifetime', $data['lifetime']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		new CLabel(_('Enable trapping'), 'allow_traps'),
		(new CCheckBox('allow_traps', HTTPCHECK_ALLOW_TRAPS_ON))
			->setChecked($data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON),
		'allow_traps_row'
	)
	->addRow(_('Allowed hosts'),
		(new CTextBox('trapper_hosts', $data['trapper_hosts']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'row_trapper_hosts'
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Enabled'),
		(new CCheckBox('status', ITEM_STATUS_ACTIVE))->setChecked($data['status'] == ITEM_STATUS_ACTIVE)
	);

/*
 * Condition tab
 */
$conditionFormList = new CFormList();

// type of calculation
$conditionFormList->addRow(new CLabel(_('Type of calculation'), 'label-evaltype'),
	[
		(new CDiv(
			(new CSelect('evaltype'))
				->setFocusableElementId('label-evaltype')
				->setId('evaltype')
				->setValue($data['evaltype'])
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
					CONDITION_EVAL_TYPE_AND => _('And'),
					CONDITION_EVAL_TYPE_OR => _('Or'),
					CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
				]))
				->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
		))->addClass(ZBX_STYLE_CELL),
		(new CDiv([
			(new CSpan(''))
				->setId('expression'),
			(new CTextBox('formula', $data['formula']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setId('formula')
				->setAttribute('placeholder', 'A or (B and C) ...')
		]))
			->addClass(ZBX_STYLE_CELL)
			->addClass(ZBX_STYLE_CELL_EXPRESSION)
	],
	'conditionRow'
);

// macros
$conditionTable = (new CTable())
	->setId('conditions')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), _('Action')]);

$conditions = $data['conditions'];
if (!$conditions) {
	$conditions = [[
		'macro' => '',
		'operator' => CONDITION_OPERATOR_REGEXP,
		'value' => '',
		'formulaid' => num2letter(0)
	]];
}
else {
	$conditions = CConditionHelper::sortConditionsByFormulaId($conditions);
}

// fields
foreach ($conditions as $i => $condition) {
	// formula id
	$formulaId = [
		new CSpan($condition['formulaid']),
		new CVar('conditions['.$i.'][formulaid]', $condition['formulaid'])
	];

	// macro
	$macro = (new CTextBox('conditions['.$i.'][macro]', $condition['macro'], false, 64))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->addClass('macro')
		->setAttribute('placeholder', '{#MACRO}')
		->setAttribute('data-formulaid', $condition['formulaid']);

	// value
	$value = (new CTextBox('conditions['.$i.'][value]', $condition['value'], false, 255))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('regular expression'));

	// delete button
	$deleteButtonCell = [
		(new CButton('conditions_'.$i.'_remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];

	$row = [$formulaId, $macro,
		(new CSelect('conditions['.$i.'][operator]'))
			->addOption(new CSelectOption(CONDITION_OPERATOR_REGEXP, _('matches')))
			->addOption(new CSelectOption(CONDITION_OPERATOR_NOT_REGEXP, _('does not match')))
			->setValue($condition['operator'])
			->addClass('operator'),
		$value,
		(new CCol($deleteButtonCell))->addClass(ZBX_STYLE_NOWRAP)
	];
	$conditionTable->addRow($row, 'form_row');
}

$conditionTable->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$conditionFormList->addRow(_('Filters'),
	(new CDiv($conditionTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * LLD Macro tab.
 */
$lld_macro_paths_form_list = new CFormList();

$lld_macro_paths_table = (new CTable())
	->setId('lld_macro_paths')
	->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
	->addStyle('width: 100%;')
	->setHeader([_('LLD macro'), _('JSONPath'), '']);

$lld_macro_paths = $data['lld_macro_paths'];

if (!$lld_macro_paths) {
	$lld_macro_paths = [[
		'lld_macro' => '',
		'path' => ''
	]];
}
elseif ($data['form_refresh'] == 0) {
	CArrayHelper::sort($lld_macro_paths, ['lld_macro']);
}

if (array_key_exists('item', $data)) {
	$templated = ($data['item']['templateid'] != 0);
}
else {
	$templated = false;
}

foreach ($lld_macro_paths as $i => $lld_macro_path) {
	$lld_macro = (new CTextAreaFlexible('lld_macro_paths['.$i.'][lld_macro]', $lld_macro_path['lld_macro'], [
		'readonly' => $templated,
		'maxlength' => DB::getFieldLength('lld_macro_path', 'lld_macro')
	]))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->setAttribute('placeholder', '{#MACRO}');

	$path = (new CTextAreaFlexible('lld_macro_paths['.$i.'][path]', $lld_macro_path['path'], [
		'readonly' => $templated,
		'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
	]))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('$.path.to.node'));

	$remove = [
		(new CButton('lld_macro_paths['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled(!$templated)
	];

	$lld_macro_paths_table->addRow([
		(new CCol($lld_macro))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($path))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($remove))->addClass(ZBX_STYLE_NOWRAP)
	],'form_row');
}

$lld_macro_paths_table->setFooter((new CCol(
	(new CButton('lld_macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$templated)
))->setColSpan(3));

$lld_macro_paths_form_list->addRow(_('LLD macros'),
	(new CDiv($lld_macro_paths_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// Overrides tab.
$overrides_form_list = new CFormList();
$overrides_list = (new CTable())
	->addClass('lld-overrides-table')
	->setHeader([
		(new CColHeader())->setWidth('15'),
		(new CColHeader())->setWidth('15'),
		(new CColHeader(_('Name')))->setWidth('350'),
		(new CColHeader(_('Stop processing')))->setWidth('100'),
		(new CColHeader(_('Action')))->setWidth('50')
	])
	->addRow(
		(new CCol(
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$templated)
					->removeId()
			))
		))
	);

$overrides_form_list->addRow(_('Overrides'),
	(new CDiv($overrides_list))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);

// Append tabs to form.
$tab = (new CTabView())
	->addTab('itemTab', $data['caption'], $form_list)
	->addTab('preprocTab', _('Preprocessing'),
		(new CFormList('item_preproc_list'))
			->addRow(_('Preprocessing steps'),
				getItemPreprocessing($data['preprocessing'], $data['limited'], $data['preprocessing_types'])
			)
	)
	->addTab('lldMacroTab', _('LLD macros'), $lld_macro_paths_form_list)
	->addTab('macroTab', _('Filters'), $conditionFormList)
	->addTab('overridesTab', _('Overrides'), $overrides_form_list);

if ($data['form_refresh'] == 0) {
	$tab->setSelected(0);
}

// Append buttons to form.
if (!empty($data['itemid'])) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] != HOST_STATUS_TEMPLATE) {
		$buttons[] = (new CSubmit('check_now', _('Execute now')))
			->setEnabled(in_array($data['item']['type'], checkNowAllowedTypes())
					&& $data['item']['status'] == ITEM_STATUS_ACTIVE
					&& $data['host']['status'] == HOST_STATUS_MONITORED
			);
	}

	$buttons[] = (new CSimpleButton(_('Test')))->setId('test_item');

	$buttons[] = (new CButtonDelete(_('Delete discovery rule?'), url_params(['form', 'itemid', 'hostid'])))
		->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel();

	$tab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$tab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[(new CSimpleButton(_('Test')))->setId('test_item'), new CButtonCancel()]
	));
}

$form->addItem($tab);
$widget->addItem($form);

require_once dirname(__FILE__).'/js/configuration.host.discovery.edit.js.php';

$widget->show();

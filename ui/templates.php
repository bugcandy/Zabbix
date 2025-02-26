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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';

$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['title'] = _('Configuration of templates');
$page['file'] = 'templates.php';
$page['scripts'] = ['multiselect.js', 'textareaflexible.js', 'inputsecret.js', 'macrovalue.js'];

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = [
	'groups'			=> [null,       O_OPT, P_ONLY_ARRAY,		NOT_EMPTY,	'isset({add}) || isset({update})'],
	'mass_update_groups' => [T_ZBX_INT, O_OPT, null,	IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
								null
							],
	'clear_templates'	=> [T_ZBX_INT, O_OPT, P_SYS|P_ONLY_ARRAY,	DB_ID,	null],
	'templates'			=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,	null],
	'linked_templates'	=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,	null],
	'add_templates'		=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,	null],
	'templateid'		=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form}) && {form} == "update"'],
	'template_name'		=> [T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({add}) || isset({update})', _('Template name')],
	'visiblename'		=> [T_ZBX_STR, O_OPT, null,		null,	'isset({add}) || isset({update})'],
	'groupids'			=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,	null],
	'tags'				=> [T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,		null,	null],
	'mass_update_tags'	=> [T_ZBX_INT, O_OPT, null,		IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
								null
							],
	'description'		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'macros'			=> [null,      O_OPT, P_SYS|P_ONLY_TD_ARRAY,		null,	null],
	'mass_update_macros' => [T_ZBX_INT, O_OPT, null,
								IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE, ZBX_ACTION_REMOVE_ALL]),
								null
							],
	'macros_add'		=> [T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'macros_update'		=> [T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'macros_remove'		=> [T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'macros_remove_all' => [T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	'visible'			=> [T_ZBX_STR, O_OPT, P_ONLY_ARRAY,			null,	null],
	'mass_action_tpls'	=> [T_ZBX_INT, O_OPT, null, IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]), null ],
	'mass_clear_tpls'	=> [T_ZBX_STR, O_OPT, null,			null,	null],
	'show_inherited_macros' => [T_ZBX_INT, O_OPT, null,	IN([0,1]), null],
	// actions
	'action'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"template.export","template.massupdate","template.massupdateform",'.
									'"template.massdelete","template.massdeleteclear"'
								),
								null
							],
	'unlink'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT|P_ONLY_ARRAY,	null,	null],
	'unlink_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT|P_ONLY_ARRAY,	null,	null],
	'add'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'masssave'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'clone'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'full_clone'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'cancel'			=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form'				=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh'		=> [T_ZBX_INT, O_OPT, P_SYS,		null,	null],
	// filter
	'filter_set'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name'		=> [T_ZBX_STR, O_OPT, null,		null,		null],
	'filter_templates' =>  [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,		null],
	'filter_groups'		=> [T_ZBX_INT, O_OPT, P_ONLY_ARRAY,		DB_ID,		null],
	'filter_evaltype'	=> [T_ZBX_INT, O_OPT, null,
								IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]),
								null
							],
	'filter_tags'		=> [T_ZBX_STR, O_OPT, P_ONLY_TD_ARRAY,		null,		null],
	// sort and sortorder
	'sort'				=> [T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),									null],
	'sortorder'			=> [T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('templateid')) {
	$templates = API::Template()->get([
		'output' => [],
		'templateids' => getRequest('templateid'),
		'editable' => true
	]);

	if (!$templates) {
		access_deny();
	}
}

$tags = getRequest('tags', []);
foreach ($tags as $key => $tag) {
	// remove empty new tag lines
	if ($tag['tag'] === '' && $tag['value'] === '') {
		unset($tags[$key]);
		continue;
	}

	// remove inherited tags
	if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
		unset($tags[$key]);
	}
	else {
		unset($tags[$key]['type']);
	}
}

// Remove inherited macros data (actions: 'add', 'update' and 'form').
$macros = cleanInheritedMacros(getRequest('macros', []));

// Remove empty new macro lines.
$macros = array_filter($macros, function($macro) {
	$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

	return (bool) array_filter(array_intersect_key($macro, $keys));
});

/*
 * Actions
 */
if (hasRequest('unlink') || hasRequest('unlink_and_clear')) {
	$unlinkTemplates = [];

	if (hasRequest('unlink')) {
		$unlinkTemplates = array_keys(getRequest('unlink'));
	}
	else {
		$unlinkTemplates = array_keys(getRequest('unlink_and_clear'));
		$_REQUEST['clear_templates'] = array_merge($unlinkTemplates, getRequest('clear_templates', []));
	}

	foreach ($unlinkTemplates as $id) {
		unset($_REQUEST['templates'][array_search($id, $_REQUEST['templates'])]);
	}
}
elseif (hasRequest('templateid') && (hasRequest('clone') || hasRequest('full_clone'))) {
	$_REQUEST['form'] = hasRequest('clone') ? 'clone' : 'full_clone';

	$groups = getRequest('groups', []);
	$groupids = [];

	// Remove inaccessible groups from request, but leave "new".
	foreach ($groups as $group) {
		if (!is_array($group)) {
			$groupids[] = $group;
		}
	}

	if ($groupids) {
		$groups_allowed = API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groups as $idx => $group) {
			if (!is_array($group) && !array_key_exists($group, $groups_allowed)) {
				unset($groups[$idx]);
			}
		}

		$_REQUEST['groups'] = $groups;
	}

	if ($macros && in_array(ZBX_MACRO_TYPE_SECRET, array_column($macros, 'type'))) {
		// Reset macro type and value.
		$macros = array_map(function($value) {
			return ($value['type'] == ZBX_MACRO_TYPE_SECRET)
				? ['value' => '', 'type' => ZBX_MACRO_TYPE_TEXT] + $value
				: $value;
		}, $macros);

		warning(_('The cloned template contains user defined macros with type "Secret text". The value and type of these macros were reset.'));
	}

	if (hasRequest('clone')) {
		unset($_REQUEST['templateid']);
	}
}
elseif (hasRequest('action') && getRequest('action') === 'template.massupdate' && hasRequest('masssave')) {
	$templateids = getRequest('templates', []);
	$visible = getRequest('visible', []);

	try {
		DBstart();

		$options = [
			'output' => ['templateid'],
			'templateids' => $templateids
		];

		if (array_key_exists('groups', $visible)) {
			$options['selectGroups'] = ['groupid'];
		}

		if (array_key_exists('linked_templates', $visible)
				&& !(getRequest('mass_action_tpls') == ZBX_ACTION_REPLACE && !hasRequest('mass_clear_tpls'))) {
			$options['selectParentTemplates'] = ['templateid'];
		}

		if (array_key_exists('tags', $visible)) {
			$mass_update_tags = getRequest('mass_update_tags', ZBX_ACTION_ADD);

			if ($mass_update_tags == ZBX_ACTION_ADD || $mass_update_tags == ZBX_ACTION_REMOVE) {
				$options['selectTags'] = ['tag', 'value'];
			}

			$unique_tags = [];

			foreach ($tags as $tag) {
				$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
			}

			$tags = array_values($unique_tags);
		}

		if (array_key_exists('macros', $visible)) {
			$mass_update_macros = getRequest('mass_update_macros', ZBX_ACTION_ADD);

			if ($mass_update_macros == ZBX_ACTION_ADD || $mass_update_macros == ZBX_ACTION_REPLACE
					|| $mass_update_macros == ZBX_ACTION_REMOVE) {
				$options['selectMacros'] = ['hostmacroid', 'macro'];
			}
		}

		$templates = API::Template()->get($options);

		if (array_key_exists('groups', $visible)) {
			$new_groupids = [];
			$remove_groupids = [];
			$mass_update_groups = getRequest('mass_update_groups', ZBX_ACTION_ADD);

			if ($mass_update_groups == ZBX_ACTION_ADD || $mass_update_groups == ZBX_ACTION_REPLACE) {
				if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
					$ins_groups = [];

					foreach (getRequest('groups', []) as $new_group) {
						if (is_array($new_group) && array_key_exists('new', $new_group)) {
							$ins_groups[] = ['name' => $new_group['new']];
						}
						else {
							$new_groupids[] = $new_group;
						}
					}

					if ($ins_groups) {
						if (!$result = API::HostGroup()->create($ins_groups)) {
							throw new Exception();
						}

						$new_groupids = array_merge($new_groupids, $result['groupids']);
					}
				}
				else {
					$new_groupids = getRequest('groups', []);
				}
			}
			elseif ($mass_update_groups == ZBX_ACTION_REMOVE) {
				$remove_groupids = getRequest('groups', []);
			}
		}

		$new_values = [];

		if (array_key_exists('description', $visible)) {
			$new_values['description'] = getRequest('description');
		}

		$template_macros_add = [];
		$template_macros_update = [];
		$template_macros_remove = [];
		foreach ($templates as &$template) {
			if (array_key_exists('groups', $visible)) {
				if ($new_groupids && $mass_update_groups == ZBX_ACTION_ADD) {
					$current_groupids = zbx_objectValues($template['groups'], 'groupid');
					$template['groups'] = zbx_toObject(array_unique(array_merge($current_groupids, $new_groupids)),
						'groupid'
					);
				}
				elseif ($new_groupids && $mass_update_groups == ZBX_ACTION_REPLACE) {
					$template['groups'] = zbx_toObject($new_groupids, 'groupid');
				}
				elseif ($remove_groupids) {
					$current_groupids = zbx_objectValues($template['groups'], 'groupid');
					$template['groups'] = zbx_toObject(array_diff($current_groupids, $remove_groupids), 'groupid');
				}
			}

			if (array_key_exists('linked_templates', $visible)) {
				$parent_templateids = array_key_exists('parentTemplates', $template)
					? zbx_objectValues($template['parentTemplates'], 'templateid')
					: [];

				switch (getRequest('mass_action_tpls')) {
					case ZBX_ACTION_ADD:
						$template['templates'] = array_unique(
							array_merge($parent_templateids, getRequest('linked_templates', []))
						);
						break;

					case ZBX_ACTION_REPLACE:
						$template['templates'] = getRequest('linked_templates', []);
						if (getRequest('mass_clear_tpls')) {
							$template['templates_clear'] = array_unique(
								array_diff($parent_templateids, getRequest('linked_templates', []))
							);
						}
						break;

					case ZBX_ACTION_REMOVE:
						$template['templates'] = array_unique(
							array_diff($parent_templateids, getRequest('linked_templates', []))
						);
						if (getRequest('mass_clear_tpls')) {
							$template['templates_clear'] = array_unique(getRequest('linked_templates', []));
						}
						break;
				}
			}

			if (array_key_exists('tags', $visible)) {
				if ($tags && $mass_update_tags == ZBX_ACTION_ADD) {
					$unique_tags = [];

					foreach (array_merge($template['tags'], $tags) as $tag) {
						$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
					}

					$template['tags'] = array_values($unique_tags);
				}
				elseif ($mass_update_tags == ZBX_ACTION_REPLACE) {
					$template['tags'] = $tags;
				}
				elseif ($tags && $mass_update_tags == ZBX_ACTION_REMOVE) {
					$diff_tags = [];

					foreach ($template['tags'] as $a) {
						foreach ($tags as $b) {
							if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
								continue 2;
							}
						}

						$diff_tags[] = $a;
					}

					$template['tags'] = $diff_tags;
				}
			}

			if (array_key_exists('macros', $visible)) {
				switch ($mass_update_macros) {
					case ZBX_ACTION_ADD:
						if ($macros) {
							$update_existing = getRequest('macros_add', 0);

							foreach ($macros as $macro) {
								foreach ($template['macros'] as $template_macro) {
									if ($macro['macro'] === $template_macro['macro']) {
										if ($update_existing) {
											$macro['hostmacroid'] = $template_macro['hostmacroid'];
											$template_macros_update[] = $macro;
										}

										continue 2;
									}
								}

								$macro['hostid'] = $template['templateid'];
								$template_macros_add[] = $macro;
							}
						}
						break;

					case ZBX_ACTION_REPLACE: // In Macros its update.
						if ($macros) {
							$add_missing = getRequest('macros_update', 0);

							foreach ($macros as $macro) {
								foreach ($template['macros'] as $template_macro) {
									if ($macro['macro'] === $template_macro['macro']) {
										$macro['hostmacroid'] = $template_macro['hostmacroid'];
										$template_macros_update[] = $macro;

										continue 2;
									}
								}

								if ($add_missing) {
									$macro['hostid'] = $template['templateid'];
									$template_macros_add[] = $macro;
								}
							}
						}
						break;

					case ZBX_ACTION_REMOVE:
						if ($macros) {
							$except_selected = getRequest('macros_remove', 0);

							$macro_names = array_column($macros, 'macro');

							foreach ($template['macros'] as $template_macro) {
								if ((!$except_selected && in_array($template_macro['macro'], $macro_names))
										|| ($except_selected && !in_array($template_macro['macro'], $macro_names))) {
									$template_macros_remove[] = $template_macro['hostmacroid'];
								}
							}
						}
						break;

					case ZBX_ACTION_REMOVE_ALL:
						if (!getRequest('macros_remove_all', 0)) {
							throw new Exception();
						}

						$template['macros'] = [];
						break;
				}

				if ($mass_update_macros != ZBX_ACTION_REMOVE_ALL) {
					unset($template['macros']);
				}
			}

			unset($template['parentTemplates']);

			$template = $new_values + $template;
		}
		unset($template);

		if (!API::Template()->update($templates)) {
			throw new Exception();
		}

		/**
		 * Macros must be updated separately, since calling API::UserMacro->replaceMacros() inside
		 * API::Template->update() results in loss of secret macro values.
		 */
		if ($template_macros_remove) {
			if (!API::UserMacro()->delete($template_macros_remove)) {
				throw new Exception();
			}
		}

		if ($template_macros_add) {
			if (!API::UserMacro()->create($template_macros_add)) {
				throw new Exception();
			}
		}

		if ($template_macros_update) {
			if (!API::UserMacro()->update($template_macros_update)) {
				throw new Exception();
			}
		}

		DBend(true);

		uncheckTableRows();
		show_message(_('Templates updated'));

		unset($_REQUEST['masssave'], $_REQUEST['form'], $_REQUEST['templates']);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update templates'));
	}
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		DBstart();

		$templateId = getRequest('templateid', 0);
		$cloneTemplateId = 0;

		if (getRequest('form') === 'full_clone') {
			$cloneTemplateId = $templateId;
			$templateId = 0;
		}

		if ($templateId == 0) {
			$messageSuccess = _('Template added');
			$messageFailed = _('Cannot add template');
		}
		else {
			$messageSuccess = _('Template updated');
			$messageFailed = _('Cannot update template');
		}

		// Add new group.
		$groups = getRequest('groups', []);
		$new_groups = [];

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::HostGroup()->create($new_groups);

			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		// Linked templates.
		$templates = [];

		foreach (array_merge(getRequest('templates', []), getRequest('add_templates', [])) as $templateid) {
			$templates[] = ['templateid' => $templateid];
		}

		$templatesClear = getRequest('clear_templates', []);
		$templatesClear = zbx_toObject($templatesClear, 'templateid');
		$templateName = getRequest('template_name', '');

		// create / update template
		$template = [
			'host' => $templateName,
			'name' => getRequest('visiblename', ''),
			'groups' => zbx_toObject($groups, 'groupid'),
			'templates' => $templates,
			'macros' => $macros,
			'tags' => $tags,
			'description' => getRequest('description', '')
		];

		if ($templateId == 0) {
			$result = API::Template()->create($template);

			if ($result) {
				$templateId = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}
		else {
			$template['templateid'] = $templateId;
			$template['templates_clear'] = $templatesClear;

			$result = API::Template()->update($template);

			if ($result) {
				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TEMPLATE, $templateId, $templateName, 'hosts', null, null);
			}
			else {
				throw new Exception();
			}
		}

		// full clone
		if ($cloneTemplateId != 0 && getRequest('form') === 'full_clone') {
			if (!copyApplications($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			/*
			 * First copy web scenarios with web items, so that later regular items can use web item as their master
			 * item.
			 */
			if (!copyHttpTests($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			if (!copyItems($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			// copy triggers
			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbTriggers) {
				$result &= copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'),
						$templateId, $cloneTemplateId);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy graphs
			$dbGraphs = API::Graph()->get([
				'output' => ['graphid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			foreach ($dbGraphs as $dbGraph) {
				copyGraphToHost($dbGraph['graphid'], $templateId);
			}

			// copy discovery rules
			$dbDiscoveryRules = API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbDiscoveryRules) {
				$result &= API::DiscoveryRule()->copy([
					'discoveryids' => zbx_objectValues($dbDiscoveryRules, 'itemid'),
					'hostids' => [$templateId]
				]);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy template screens
			$dbTemplateScreens = API::TemplateScreen()->get([
				'noInheritance' => true,
				'output' => ['screenid'],
				'templateids' => $cloneTemplateId,
				'preservekeys' => true
			]);

			if ($dbTemplateScreens) {
				$result &= API::TemplateScreen()->copy([
					'screenIds' => zbx_objectValues($dbTemplateScreens, 'screenid'),
					'templateIds' => $templateId
				]);

				if (!$result) {
					throw new Exception();
				}
			}
		}

		unset($_REQUEST['form'], $_REQUEST['templateid']);
		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message($messageFailed);
	}
}
elseif (hasRequest('templateid') && hasRequest('delete')) {
	DBstart();

	$result = API::Template()->massUpdate([
		'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
		'hosts' => []
	]);
	if ($result) {
		$result = API::Template()->delete([getRequest('templateid')]);
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (hasRequest('templateid') && hasRequest('delete_and_clear')) {
	DBstart();

	$result = API::Template()->delete([getRequest('templateid')]);

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (hasRequest('templates') && hasRequest('action') && str_in_array(getRequest('action'), ['template.massdelete', 'template.massdeleteclear'])) {
	$templates = getRequest('templates');

	DBstart();

	$result = true;

	if (getRequest('action') === 'template.massdelete') {
		$result = API::Template()->massUpdate([
			'templates' => zbx_toObject($templates, 'templateid'),
			'hosts' => []
		]);
	}

	if ($result) {
		$result = API::Template()->delete($templates);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	else {
		$templateids = API::Template()->get([
			'output' => [],
			'templateids' => $templates,
			'editable' => true
		]);
		uncheckTableRows(null, zbx_objectValues($templateids, 'templateid'));
	}
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}

/*
 * Display
 */
if (hasRequest('templates') && (getRequest('action') === 'template.massupdateform' || hasRequest('masssave'))) {
	$data = [
		'templates' => getRequest('templates', []),
		'visible' => getRequest('visible', []),
		'mass_action_tpls' => getRequest('mass_action_tpls'),
		'mass_clear_tpls' => getRequest('mass_clear_tpls'),
		'groups' => getRequest('groups', []),
		'mass_update_groups' => getRequest('mass_update_groups', ZBX_ACTION_ADD),
		'tags' => $tags,
		'mass_update_tags' => getRequest('mass_update_tags', ZBX_ACTION_ADD),
		'macros' => $macros,
		'macros_checkbox' => [
			ZBX_ACTION_ADD => getRequest('macros_add', '0'),
			ZBX_ACTION_REPLACE => getRequest('macros_update', '0'),
			ZBX_ACTION_REMOVE => getRequest('macros_remove', '0'),
			ZBX_ACTION_REMOVE_ALL => getRequest('macros_remove_all', '0')
		],
		'macros_visible' => getRequest('mass_update_macros', ZBX_ACTION_ADD),
		'description' => getRequest('description'),
		'linked_templates' => getRequest('linked_templates', [])
	];

	// sort templates
	natsort($data['linked_templates']);

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	// get templates data
	$data['linked_templates'] = $data['linked_templates']
		? CArrayHelper::renameObjectsKeys(API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $data['linked_templates']
		]), ['templateid' => 'id'])
		: [];

	$data['groups'] = $data['groups']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $data['groups'],
			'editable' => true
		]), ['groupid' => 'id'])
		: [];

	if (!$data['macros']) {
		$data['macros'] = [['macro' => '', 'type' => ZBX_MACRO_TYPE_TEXT, 'value' => '', 'description' => '']];
	}

	$view = new CView('configuration.template.massupdate', $data);
}
elseif (hasRequest('form')) {
	$data = [
		'form_refresh' => getRequest('form_refresh', 0),
		'form' => getRequest('form'),
		'templateid' => getRequest('templateid', 0),
		'linked_templates' => [],
		'add_templates' => [],
		'original_templates' => [],
		'tags' => $tags,
		'show_inherited_macros' => getRequest('show_inherited_macros', 0),
		'readonly' => false,
		'macros' => $macros
	];

	if ($data['templateid'] != 0) {
		$dbTemplates = API::Template()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => ['templateid', 'name'],
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'templateids' => $data['templateid']
		]);
		$data['dbTemplate'] = reset($dbTemplates);

		foreach ($data['dbTemplate']['parentTemplates'] as $parentTemplate) {
			$data['original_templates'][$parentTemplate['templateid']] = $parentTemplate['templateid'];
		}

		if (!hasRequest('form_refresh')) {
			$data['tags'] = $data['dbTemplate']['tags'];
			$data['macros'] = $data['dbTemplate']['macros'];
		}
	}

	// description
	$data['description'] = ($data['templateid'] != 0 && !hasRequest('form_refresh'))
		? $data['dbTemplate']['description']
		: getRequest('description', '');

	// tags
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	else {
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}

	// Add already linked and new templates.
	$templates = [];
	$request_linked_templates = getRequest('templates', hasRequest('form_refresh') ? [] : $data['original_templates']);
	$request_add_templates = getRequest('add_templates', []);

	if ($request_linked_templates || $request_add_templates) {
		$templates = API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => array_merge($request_linked_templates, $request_add_templates),
			'preservekeys' => true
		]);

		$data['linked_templates'] = array_intersect_key($templates, array_flip($request_linked_templates));
		CArrayHelper::sort($data['linked_templates'], ['name']);

		$data['add_templates'] = array_intersect_key($templates, array_flip($request_add_templates));

		foreach ($data['add_templates'] as &$template) {
			$template = CArrayHelper::renameKeys($template, ['templateid' => 'id']);
		}
		unset($template);
	}

	$data['writable_templates'] = API::Template()->get([
		'output' => ['templateid'],
		'templateids' => array_keys($data['linked_templates']),
		'editable' => true,
		'preservekeys' => true
	]);

	// Add inherited macros to template macros.
	if ($data['show_inherited_macros']) {
		$data['macros'] = mergeInheritedMacros($data['macros'], getInheritedMacros(array_keys($templates)));
	}

	// Sort only after inherited macros are added. Otherwise the list will look chaotic.
	$data['macros'] = array_values(order_macros($data['macros'], 'macro'));

	// The empty inputs will not be shown if there are inherited macros, for example.
	if (!$data['macros']) {
		$macro = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];

		if ($data['show_inherited_macros']) {
			$macro['inherited_type'] = ZBX_PROPERTY_OWN;
		}

		$data['macros'][] = $macro;
	}

	if (!hasRequest('form_refresh')) {
		if ($data['templateid'] != 0) {
			$groups = zbx_objectValues($data['dbTemplate']['groups'], 'groupid');
		}
		else {
			$groups = getRequest('groupids', []);
		}
	}
	else {
		$groups = getRequest('groups', []);
	}

	$groupids = [];

	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			continue;
		}

		$groupids[] = $group;
	}

	// Groups with R and RW permissions.
	$groups_all = $groupids
		? API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	// Groups with RW permissions.
	$groups_rw = $groupids && (CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
		? API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['groups_ms'] = [];

	// Prepare data for multiselect.
	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			$data['groups_ms'][] = [
				'id' => $group['new'],
				'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		elseif (array_key_exists($group, $groups_all)) {
			$data['groups_ms'][] = [
				'id' => $group,
				'name' => $groups_all[$group]['name'],
				'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) && !array_key_exists($group, $groups_rw)
			];
		}
	}
	CArrayHelper::sort($data['groups_ms'], ['name']);

	// This data is used in common.template.edit.js.php.
	$data['macros_tab'] = [
		'linked_templates' => array_map('strval', array_keys($data['linked_templates'])),
		'add_templates' => array_map('strval', array_keys($data['add_templates']))
	];

	$view = new CView('configuration.template.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.templates.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter_templates', getRequest('filter_templates', []), PROFILE_TYPE_ID);
		CProfile::updateArray('web.templates.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
		CProfile::update('web.templates.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);

		$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
		foreach (getRequest('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags['tags'][] = $filter_tag['tag'];
			$filter_tags['values'][] = $filter_tag['value'];
			$filter_tags['operators'][] = $filter_tag['operator'];
		}
		CProfile::updateArray('web.templates.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.templates.filter_name');
		CProfile::deleteIdx('web.templates.filter_templates');
		CProfile::deleteIdx('web.templates.filter_groups');
		CProfile::delete('web.templates.filter.evaltype');
		CProfile::deleteIdx('web.templates.filter.tags.tag');
		CProfile::deleteIdx('web.templates.filter.tags.value');
		CProfile::deleteIdx('web.templates.filter.tags.operator');
	}

	$filter = [
		'name' => CProfile::get('web.templates.filter_name', ''),
		'templates' => CProfile::getArray('web.templates.filter_templates', null),
		'groups' => CProfile::getArray('web.templates.filter_groups', null),
		'evaltype' => CProfile::get('web.templates.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
		'tags' => []
	];

	foreach (CProfile::getArray('web.templates.filter.tags.tag', []) as $i => $tag) {
		$filter['tags'][] = [
			'tag' => $tag,
			'value' => CProfile::get('web.templates.filter.tags.value', null, $i),
			'operator' => CProfile::get('web.templates.filter.tags.operator', null, $i)
		];
	}

	$config = select_config();

	$filter['templates'] = $filter['templates']
		? CArrayHelper::renameObjectsKeys(API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $filter['templates'],
			'preservekeys' => true
		]), ['templateid' => 'id'])
		: [];

	// Get host groups.
	$filter['groups'] = $filter['groups']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter['groups'],
			'editable' => true,
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;
	if ($filter_groupids) {
		$filter_groupids = getSubGroups($filter_groupids);
	}

	// Select templates.
	$templates = API::Template()->get([
		'output' => ['templateid', $sortField],
		'evaltype' => $filter['evaltype'],
		'tags' => $filter['tags'],
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'parentTemplateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
		'groupids' => $filter_groupids,
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	order_result($templates, $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$paging = CPagerHelper::paginate($page_num, $templates, $sortOrder, new CUrl('templates.php'));

	$templates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'selectHosts' => ['hostid'],
		'selectTemplates' => ['templateid', 'name'],
		'selectParentTemplates' => ['templateid', 'name'],
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'selectTags' => ['tag', 'value'],
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => true,
		'preservekeys' => true
	]);

	order_result($templates, $sortField, $sortOrder);

	// Select editable templates:
	$linked_templateids = [];
	$editable_templates = [];
	$linked_hostids = [];
	$editable_hosts = [];
	foreach ($templates as &$template) {
		order_result($template['templates'], 'name');
		order_result($template['parentTemplates'], 'name');

		$linked_templateids += array_flip(array_column($template['parentTemplates'], 'templateid'));
		$linked_templateids += array_flip(array_column($template['templates'], 'templateid'));

		$template['hosts'] = array_flip(array_column($template['hosts'], 'hostid'));
		$linked_hostids += $template['hosts'];
	}
	unset($template);

	if ($linked_templateids) {
		$editable_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys($linked_templateids),
			'editable' => true,
			'preservekeys' => true
		]);
	}
	if ($linked_hostids) {
		$editable_hosts = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => array_keys($linked_hostids),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$data = [
		'templates' => $templates,
		'paging' => $paging,
		'page' => $page_num,
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'config' => [
			'max_in_table' => $config['max_in_table']
		],
		'editable_templates' => $editable_templates,
		'editable_hosts' => $editable_hosts,
		'profileIdx' => 'web.templates.filter',
		'active_tab' => CProfile::get('web.templates.filter.active', 1),
		'tags' => makeTags($templates, true, 'templateid', ZBX_TAG_COUNT_DEFAULT, $filter['tags'])
	];

	$view = new CView('configuration.template.list', $data);
}

echo $view->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';

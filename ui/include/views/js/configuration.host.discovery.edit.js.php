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

$counter = null;
if (hasRequest('conditions')) {
	$conditions = getRequest('conditions');
	krsort($conditions);
	$counter = key($conditions) + 1;
}

$interface_ids_by_types = [];
foreach ($data['interfaces'] as $interface) {
	$interface_ids_by_types[$interface['type']][] = $interface['interfaceid'];
}

include dirname(__FILE__).'/common.item.edit.js.php';
include dirname(__FILE__).'/item.preprocessing.js.php';
include dirname(__FILE__).'/editabletable.js.php';
include dirname(__FILE__).'/itemtest.js.php';
include dirname(__FILE__).'/configuration.host.discovery.edit.overr.js.php';
?>
<script type="text/x-jquery-tmpl" id="condition-row">
	<?=
		(new CRow([[
				new CSpan('#{formulaId}'),
				new CVar('conditions[#{rowNum}][formulaid]', '#{formulaId}')
			],
			(new CTextBox('conditions[#{rowNum}][macro]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->addClass(ZBX_STYLE_UPPERCASE)
				->addClass('macro')
				->setAttribute('placeholder', '{#MACRO}')
				->setAttribute('data-formulaid', '#{formulaId}'),
			(new CSelect('conditions[#{rowNum}][operator]'))
				->addOption(new CSelectOption(CONDITION_OPERATOR_REGEXP, _('matches')))
				->addOption(new CSelectOption(CONDITION_OPERATOR_NOT_REGEXP, _('does not match')))
				->setValue(CONDITION_OPERATOR_REGEXP)
				->addClass('operator'),
			(new CTextBox('conditions[#{rowNum}][value]', '', false, 255))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setAttribute('placeholder', _('regular expression')),
			(new CCol(
				(new CButton('conditions_#{rowNum}_remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lld_macro_path-row">
	<?= (new CRow([
			(new CCol(
				(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][lld_macro]', '', [
					'add_post_js' => false,
					'maxlength' => DB::getFieldLength('lld_macro_path', 'lld_macro')
				]))
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->addClass(ZBX_STYLE_UPPERCASE)
					->setAttribute('placeholder', '{#MACRO}')
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('lld_macro_paths[#{rowNum}][path]', '', [
					'add_post_js' => false,
					'maxlength' => DB::getFieldLength('lld_macro_path', 'path')
				]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('$.path.to.node'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CButton('lld_macro_paths[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/javascript">
	(function($) {
		$(function() {
			function updateExpression() {
				var conditions = [];

				$('#conditions .macro').each(function(index, macroInput) {
					macroInput = $(macroInput);
					macroInput.val(macroInput.val().toUpperCase());

					conditions.push({
						id: macroInput.data('formulaid'),
						type: macroInput.val()
					});
				});

				$('#expression').html(getConditionFormula(conditions, +$('#evaltype').val()));
			}

			$('#conditions')
				.dynamicRows({
					template: '#condition-row',
					counter: <?= json_encode($counter) ?>,
					dataCallback: function(data) {
						data.formulaId = num2letter(data.rowNum);

						return data;
					}
				})
				.bind('tableupdate.dynamicRows', function(event, options) {
					$('#conditionRow').toggle($(options.row, $(this)).length > 1);

					if ($('#evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
						updateExpression();
					}
				})
				.on('change', '.macro', function() {
					if ($('#evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
						updateExpression();
					}
				})
				.ready(function() {
					$('#conditionRow').toggle($('.form_row', $('#conditions')).length > 1);
				});

			$('#evaltype').change(function() {
				var show_formula = ($(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

				$('#expression').toggle(!show_formula);
				$('#formula').toggle(show_formula);
				if (!show_formula) {
					updateExpression();
				}
			});

			$('#evaltype').trigger('change');

			$('#type').change(function() {
				var type = parseInt($('#type').val()),
					asterisk = '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>';

				if (type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>) {
					$('label[for=username]').addClass(asterisk);
					$('input[name=username]').attr('aria-required', 'true');
				}
				else {
					$('label[for=username]').removeClass(asterisk);
					$('input[name=username]').removeAttr('aria-required');
				}
			}).trigger('change');

			$('#lld_macro_paths')
				.dynamicRows({template: '#lld_macro_path-row'})
				.on('click', 'button.element-table-add', function() {
					$('#lld_macro_paths .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
				});
		});
	})(jQuery);
</script>

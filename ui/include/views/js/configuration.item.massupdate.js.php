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

include dirname(__FILE__).'/item.preprocessing.js.php';
include dirname(__FILE__).'/editabletable.js.php';
include dirname(__FILE__).'/itemtest.js.php';
?>
<script type="text/x-jquery-tmpl" id="custom_intervals_row">
	<tr class="form_row">
		<td>
			<ul class="<?= CRadioButtonList::ZBX_STYLE_CLASS ?>" id="delay_flex_#{rowNum}_type">
				<li>
					<input type="radio" id="delay_flex_#{rowNum}_type_0" name="delay_flex[#{rowNum}][type]" value="0" checked="checked">
					<label for="delay_flex_#{rowNum}_type_0"><?= _('Flexible') ?></label>
				</li><li>
					<input type="radio" id="delay_flex_#{rowNum}_type_1" name="delay_flex[#{rowNum}][type]" value="1">
					<label for="delay_flex_#{rowNum}_type_1"><?= _('Scheduling') ?></label>
				</li>
			</ul>
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="255" placeholder="<?= ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT ?>">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="<?= ZBX_ITEM_SCHEDULING_DEFAULT ?>" style="display: none;">
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_period" name="delay_flex[#{rowNum}][period]" maxlength="255" placeholder="<?= ZBX_DEFAULT_INTERVAL ?>">
		</td>
		<td>
			<button type="button" id="delay_flex_#{rowNum}_remove" name="delay_flex[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/javascript">
	jQuery(function($) {
		var item_interface_types = <?= json_encode(itemTypeInterface()) ?>,
			interface_ids_by_types = <?= json_encode($interface_ids_by_types) ?>;

		$('#visible_type, #visible_interfaceid').click(function() {
			// if no item type is selected, reset the interfaces to default
			if (!$('#visible_type').is(':checked')) {
				organizeInterfaces(interface_ids_by_types, item_interface_types, <?= json_encode($data['initial_item_type']) ?>);
			}
			else {
				$('#type').trigger('change');
			}
		});

		$('#type')
			.change(function() {
				// update the interface select with each item type change
				organizeInterfaces(interface_ids_by_types, item_interface_types, parseInt(jQuery(this).val()));
			})
			.trigger('change');

		$('#history_mode')
			.change(function() {
				if ($('[name="history_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					$('#history').prop('disabled', true).hide();
				}
				else {
					$('#history').prop('disabled', false).show();
				}
			})
			.trigger('change');

		$('#trends_mode')
			.change(function() {
				if ($('[name="trends_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					$('#trends').prop('disabled', true).hide();
				}
				else {
					$('#trends').prop('disabled', false).show();
				}
			})
			.trigger('change');

		$('#custom_intervals').on('click', 'input[type="radio"]', function() {
			var rowNum = $(this).attr('id').split('_')[2];

			if ($(this).val() == <?= ITEM_DELAY_FLEXIBLE; ?>) {
				$('#delay_flex_' + rowNum + '_schedule').hide();
				$('#delay_flex_' + rowNum + '_delay').show();
				$('#delay_flex_' + rowNum + '_period').show();
			}
			else {
				$('#delay_flex_' + rowNum + '_delay').hide();
				$('#delay_flex_' + rowNum + '_period').hide();
				$('#delay_flex_' + rowNum + '_schedule').show();
			}
		});

		$('#custom_intervals').dynamicRows({
			template: '#custom_intervals_row'
		});

		$('input[name=massupdate_app_action]').on('change', function() {
			$('#applications_').multiSelect('modify', {
				'addNew': ($(this).val() == <?= ZBX_ACTION_ADD ?> || $(this).val() == <?= ZBX_ACTION_REPLACE ?>)
			});
		});

		<?php if (array_key_exists('parent_discoveryid', $data)): ?>
			$('input[name=massupdate_app_prot_action]').on('change', function() {
				$('#application_prototypes_').multiSelect('modify', {
					'addNew': ($(this).val() == <?= ZBX_ACTION_ADD ?> || $(this).val() == <?= ZBX_ACTION_REPLACE ?>)
				});
			});
		<?php endif ?>
	});
</script>

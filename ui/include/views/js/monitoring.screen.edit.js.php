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
?>

<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}'))
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ_WRITE))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass(CRadioButtonList::ZBX_STYLE_CLASS)
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserGroupShares("#{usrgrpid}");')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_group_shares_#{usrgrpid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}'))
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ))
							->setId('user_#{id}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ_WRITE))
							->setId('user_#{id}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass(CRadioButtonList::ZBX_STYLE_CLASS)
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserShares("#{id}");')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_shares_#{id}')
			->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#clone, #full_clone').click(function() {
			var form = $(this).attr('id');

			$('#form').val(form);

			if (form === 'clone') {
				$('#screenid').remove();
			}

			$('#delete, #clone, #full_clone, #inaccessible_user').remove();

			$('#update')
				.text(<?= json_encode(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			$('#tab_screen_tab').trigger('click');
			$('#multiselect_userid_wrapper').show();

			$('#userid').multiSelect('addData', [{
				'id': $('#current_user_userid').val(),
				'name': $('#current_user_fullname').val()
			}]);

			$('#name').focus();
		});
	});

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var i,
			value,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}

			value = list.values[i];
			if (typeof value.permission === 'undefined') {
				if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
					value.permission = <?= PERM_READ ?>;
				}
				else {
					value.permission = <?= PERM_READ_WRITE ?>;
				}
			}

			switch (list.object) {
				case 'usrgrpid':
					if (jQuery('#user_group_shares_' + value.usrgrpid).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_group_row_tpl').html());

					container = jQuery('#user_group_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;

				case 'userid':
					if (jQuery('#user_shares_' + value.id).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_row_tpl').html());

					container = jQuery('#user_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_' + value.id + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;
			}
		}
	}

	function removeUserGroupShares(usrgrpid) {
		jQuery('#user_group_shares_' + usrgrpid).remove();
	}

	function removeUserShares(userid) {
		jQuery('#user_shares_' + userid).remove();
	}
</script>

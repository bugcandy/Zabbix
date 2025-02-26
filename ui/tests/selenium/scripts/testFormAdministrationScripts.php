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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup scripts
 *
 * @onBefore prepareScriptData
 */
class testFormAdministrationScripts extends CWebTest {
	/**
	 * Id of scripts.
	 *
	 * @var array
	 */
	protected static $ids;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	/**
	 * Function used to create scripts.
	 */
	public function prepareScriptData() {
		$response = CDataHelper::call('script.create', [
			[
				'name' => 'Script for Clone',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => 'test',
				'description' => 'clone description'
			],
			[
				'name' => 'Script for Update',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => 'test',
				'description' => 'update description'
			],
			[
				'name' => 'Script for Delete',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => 'test',
				'description' => 'delete description'
			]
		]);
		$this->assertArrayHasKey('scriptids', $response);
		self::$ids = CDataHelper::getIds('name');
	}

	/**
	 * Test data for Scripts form.
	 */
	public function getScriptsData() {
		return [
			// Script.
			[
				[
					'fields' => [
						'Name' => 'Max script',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server (proxy)',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Disabled',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'Script',
						'Commands' => 'Script empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'Script empty command',
						'Type' => 'Script',
						'Commands' => ''
					]
				]
			],
			// IPMI.
			[
				[
					'fields' => [
						'Name' => 'Max IPMI',
						'Type' => 'IPMI',
						'Command' => 'IPMI command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Discovered hosts',
						'Required host permissions' => 'Write',
						'Enable confirmation' => true,
						'Confirmation text' => 'Execute script?'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'IPMI',
						'Command' => 'IPMI empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'IPMI empty command',
						'Type' => 'IPMI',
						'Command' => ''
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getScriptsData
	 * @backupOnce scripts
	 */
	public function testFormAdministrationScripts_Create($data) {
		$this->checkScripts($data, false, 'zabbix.php?action=script.edit');
	}

	/**
	 * @dataProvider getScriptsData
	 */
	public function testFormAdministrationScripts_Update($data) {
		$this->checkScripts($data, true, 'zabbix.php?action=script.edit&scriptid='.self::$ids['Script for Delete']);
	}

	/**
	 * Function for checking script configuration form.
	 *
	 * @param array     $data     data provider
	 * @param boolean   $update   is it update case, or not
	 * @param string    $link     link to script form
	 */
	private function checkScripts($data, $update, $link) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = 'SELECT * FROM scripts ORDER BY scriptid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open($link);
		$form = $this->query('id:scriptForm')->asForm()->waitUntilVisible()->one();
		$form->fill($data['fields']);

		// Check testing confirmation while configuring.
		if (array_key_exists('Enable confirmation', $data['fields'])) {
			$this->checkConfirmation($data, $form);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$title = ($update) ? 'Cannot update script' : 'Cannot add script';
			$this->assertMessage(TEST_BAD, $title, $data['details']);
			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$title = ($update) ? 'Script updated' : 'Script added';
			$this->assertMessage(TEST_GOOD, $title);
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr($data['fields']['Name'])));
			// Check the results in form.
			$id = CDBHelper::getValue('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr($data['fields']['Name']));
			$this->page->open('zabbix.php?action=script.edit&scriptid='.$id);

			$form->invalidate();
			$form->checkValue($data['fields']);

			// Check testing confirmation in saved form.
			if (array_key_exists('Enable confirmation', $data['fields'])) {
				$this->checkConfirmation($data, $form);
			}
		}
	}

	/**
	 * Function for checking execution confirmation popup.
	 *
	 * @param array     $data    data provider
	 * @param element   $form    script configuration form
	 */
	private function checkConfirmation($data, $form) {
		if (CTestArrayHelper::get($data['fields'], 'Enable confirmation') === false) {
			$this->assertFalse($form->query('id:confirmation')->one()->isEnabled());
			$this->assertFalse($form->query('id:testConfirmation')->one()->isEnabled());
		}

		if (CTestArrayHelper::get($data['fields'], 'Confirmation text')) {
			$this->query('button:Test confirmation')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->one();
			$this->assertEquals('Execution confirmation', $dialog->getTitle());
			$this->assertEquals($data['fields']['Confirmation text'],
					$dialog->query('xpath:.//div[@class="overlay-dialogue-body"]/span')->waitUntilReady()->one()->getText());
			$this->assertFalse($dialog->query('button:Execute')->one()->isEnabled());
			$dialog->query('button:Cancel')->one()->click();
		}
	}

	/**
	 * Function for checking script form update cancelling.
	 */
	public function testFormAdministrationScripts_CancelUpdate() {
		$sql = 'SELECT * FROM scripts ORDER BY scriptid';
		$old_hash = CDBHelper::getHash($sql);
		$this->page->login()->open('zabbix.php?action=script.edit&scriptid='.self::$ids['Script for Delete']);
		$form = $this->query('id:scriptForm')->asForm()->waitUntilVisible()->one();
		$form->fill([
			'Name' => 'Cancelled cript',
			'Type' => 'Script',
			'Execute on' => 'Zabbix server',
			'Commands' => 'Script command',
			'Description' => 'Cancelled description',
			'User group' => 'Disabled',
			'Host group' => 'Selected',
			'xpath://div[@id="groupid"]/..' => 'Hypervisors',
			'Required host permissions' => 'Write',
			'Enable confirmation' => true
		]);
		$form->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Scripts');
		$this->assertTrue($this->query('button:Create script')->waitUntilVisible()->one()->isReady());
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function for checking script form update without any changes.
	 */
	public function testFormAdministrationScripts_SimpleUpdate() {
		$sql = 'SELECT * FROM scripts ORDER BY scriptid';
		$old_hash = CDBHelper::getHash($sql);
		$this->page->login()->open('zabbix.php?action=script.edit&scriptid='.self::$ids['Script for Delete']);
		$this->query('id:scriptForm')->asForm()->waitUntilVisible()->one()->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Script updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function for checking script cloning with only changed name.
	 */
	public function testFormAdministrationScripts_Clone() {
		$name = 'Cloned Script for Clone';
		$this->page->login()->open('zabbix.php?action=script.edit&scriptid='.self::$ids['Script for Clone']);
		$form = $this->query('id:scriptForm')->asForm()->waitUntilVisible()->one();
		$values = $form->getFields()->asValues();
		$values['Name'] = $name;
		$this->query('button:Clone')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$form->invalidate();
		$form->fill(['Name' => $name]);
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'Script added');
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr($name)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr('Script for Clone')));

		$id = CDBHelper::getValue('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr($name));
		$this->page->open('zabbix.php?action=script.edit&scriptid='.$id);
		$cloned_values = $form->getFields()->asValues();
		$this->assertEquals($values, $cloned_values);
	}

	/**
	 * Function for testing script delete from configuration form.
	 */
	public function testFormAdministrationScripts_Delete() {
		$this->page->login()->open('zabbix.php?action=script.edit&scriptid='.self::$ids['Script for Delete']);
		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Script deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.
				zbx_dbstr(self::$ids['Script for Delete']))
		);
	}
}

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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup hosts
 */
class testHost extends CAPITest {

	public static function host_delete() {
		return [
			[
				'hostids' => [
					'61001'
				],
				'expected_error' => 'Cannot delete host because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61001',
					'61003'
				],
				'expected_error' => 'Cannot delete selected hosts because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61003'
				],
				'expected_error' => null
			],
			[
				'hostids' => [
					'61004',
					'61005'
				],
				'expected_error' => 'Cannot delete selected hosts because maintenance "maintenance_two_hosts" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61004'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider host_delete
	*/
	public function testHost_Delete($hostids, $expected_error) {
		$result = $this->call('host.delete', $hostids, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from hosts where hostid='.zbx_dbstr($id)));
			}
		}
	}

	public static function host_select_tags() {
		return [
			'Get test host tag as extend' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => 'extend'
				],
				'expected_result' => [
					'tags' => [
						['tag' => 'b', 'value' => 'b']
					]
				]
			],
			'Get test host tag excluding value' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => ['tag']
				],
				'expected_result' => [
					'tags' => [
						['tag' => 'b']
					]
				]
			],
			'Get test host tag excluding name' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => ['value']
				],
				'expected_result' => [
					'tags' => [
						['value' => 'b']
					]
				]
			]
		];
	}

	/**
	* @dataProvider host_select_tags
	*/
	public function testHost_SelectTags($params, $expected_result) {
		$result = $this->call('host.get', $params);

		foreach($result['result'] as $host) {
			foreach($expected_result as $field => $expected_value){
				$this->assertArrayHasKey($field, $host, 'Field should be present.');
				$this->assertEquals($host[$field], $expected_value, 'Returned value should match.');
			}
		}
	}
}

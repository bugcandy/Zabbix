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


class WebScenarios {
	/**
	 * Create data for web scenarios related tests.
	 *
	 * @return array
	 */
	public static function load() {
		$host = 'Host for Web scenario testing';
		$template = 'Template for web scenario testing';

		$template_responce = CDataHelper::createTemplates([
			[
				'host' => $template,
				'groups' => [
					'groupid' => 1
				]
			]
		]);

		$templateid = $template_responce['templateids'][$template];

		$host_responce = CDataHelper::createHosts([
			[
				'host' => $host,
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				],
				'groups' => [
					'groupid' => 5
				],
				'templates' => [
					'templateid' => $templateid
				]
			]
		]);

		$hostid = $host_responce['hostids'][$host];

		// Create applications for the host.
		$applications_responce = CDataHelper::call('application.create', [
			[
				'name' => 'App 1',
				'hostid' => $hostid
			],
			[
				'name' => 'Заббикс',
				'hostid' => $hostid
			]
		]);

		CDataHelper::call('httptest.create', [
			[
				'name' => 'Scenario for Update',
				'hostid' => $hostid,
				'agent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
				'steps' => [
					[
						'name' => 'step 1 of scenario 1',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				]
			],
			[
				'name' => 'Scenario for Delete',
				'hostid' => $hostid,
				'delay' => 180,
				'agent' => 'Links (2.8; Linux 3.13.0-36-generic x86_64; GNU C 4.8.2; text)',
				'steps' => [
					[
						'name' => '!@#$%^&*()_+ōš六書',
						'url' => 'http://zabbix.com',
						'no' => 1
					],
					[
						'name' => 'Второй этап вэб сценария',
						'url' => 'http://zabbix.com',
						'no' => 2
					]
				]
			],
			[
				'name' => 'Scenario for Clone',
				'hostid' => $hostid,
				'delay' => 10,
				'agent' => 'My_custom_agent_string !@#$%^&*()_+=-',
				'applicationid' => $applications_responce['applicationids'][1],
				'authentication' => 2,
				'http_user' => 'Admin',
				'http_password' => 'zabbix!@#$%^&*()_+',
				'http_proxy' => 'http://Admin:zabbix@proxy.zabbix.com:666',
				'retries' => 3,
				'ssl_cert_file' => 'cert_!@#$%^&*()_+.pem',
				'ssl_key_file' => 'key_!@#$%^&*()_+.pem',
				'ssl_key_password' => '!@#$%^&*()_+',
				'status' => 1,
				'verify_host' => 1,
				'verify_peer' => 1,
				'headers' => [
					[
						'name' => '!@#$%^&*()_-=+',
						'value' => '+=_-)(*&^%$#@'
					]
				],
				'variables' => [
					[
						'name' => '{!@#$%^&*()_-=+}',
						'value' => '+=_-)(*&^%$#@'
					]
				],
				'steps' => [
					[
						'name' => 'Первый этап вэб сценария',
						'url' => 'http://zabbix.com',
						'no' => 1,
						'follow_redirects' => 0,
						'headers' => [
							[
								'name' => 'step_header1',
								'value' => 'step_value1'
							],
							[
								'name' => 'step_!@#$%^&*()_-=+',
								'value' => '+=_-)(*&^%$#@_pets'
							]
						],
						'posts' => [
							[
								'name' => 'post1',
								'value' => 'post_value1'
							],
							[
								'name' => 'post_!@#$%^&*()_-=+',
								'value' => '+=_-)(*&^%$#@_tsop'
							]
						],
						'required' => 'Zabbix',
						'retrieve_mode' => 2,
						'status_codes' => '200,301,404',
						'timeout' => '2m',
						'variables' => [
							[
								'name' => '{step_!@#$%^&*()_-=+}',
								'value' => '+=_-)(*&^%$#@_pets'
							],
							[
								'name' => '{step_variable1}',
								'value' => 'step_value1'
							]
						],
						'query_fields' => [
							[
								'name' => '{step_query1}',
								'value' => 'query_value1'
							],
							[
								'name' => 'query_!@#$%^&*()_-=+',
								'value' => '+=_-)(*&^%$#@_yreuq'
							]
						]
					],
					[
						'name' => 'step 2 of clone scenario',
						'url' => 'http://zabbix.com',
						'no' => 2
					]
				]
			],
			[
				'name' => 'Template_Web_scenario',
				'hostid' => $templateid,
				'agent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
				'steps' => [
					[
						'name' => 'step 1 of scenario 1',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				]
			]
		]);

		return [
			'templateid' => $templateid,
			'template_name' => $template,
			'hostid' => $hostid,
			'httptestids' => CDataHelper::getIds('name')
		];
	}
}

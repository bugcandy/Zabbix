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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testDashboardViewMode extends CLegacyWebTest {

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 */
	public function testDashboardViewMode_CheckLayoutForDifferentUsers() {
		$users = ['super-admin', 'admin', 'user', 'guest'];
		foreach ($users as $user) {
			switch ($user) {
				case 'super-admin' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55b', 1);
					break;
				case 'admin' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55c', 4);
					break;
				case 'user';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55d', 5);
					break;
				case 'guest';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55e', 2);
					break;
			}
			$this->zbxTestOpen('zabbix.php?action=dashboard.view&dashboardid=1');
			$this->zbxTestCheckTitle('Dashboard');
			$this->zbxTestCheckHeader('Global view');
			if ($user !== 'super-admin') {
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//tr[@class='nothing-to-show']/td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//tr[@class='nothing-to-show']/td", 'No data found.');
			}
			else {
				$this->zbxTestCheckNoRealHostnames();
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//h4", 'Favourite graphs');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//h4", 'Favourite maps');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//h4", 'Problems');
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[6]//h4[text()='Problems by severity']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[5]//h4[text()='Local']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[4]//h4[text()='Host availability']");
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[3]//h4", 'System information');

			// Logout.
			$this->zbxTestLogout();
			$this->zbxTestWaitForPageToLoad();
			$this->webDriver->manage()->deleteAllCookies();
		}
	}

	public function testDashboardViewMode_KioskMode() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&dashboardid=1', false);
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");

		$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		$this->query('class:btn-min')->one()->forceClick();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-kiosk')]"));
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
	}

	public function testDashboardViewMode_KioskModeUrlParameter() {
		// Set layout mode to kiosk view.
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&kiosk=1', false);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		// Set layout mode to default layout.
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&kiosk=0');
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (1550, 9, 2)');
	}
}

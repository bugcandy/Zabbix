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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockhelper.h"
#include "zbxmockutil.h"

#include "common.h"
#include "module.h"
#include "sysinfo.h"

static int	read_yaml_ret(void)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("ret", &handle)))
		fail_msg("Cannot get return code: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read return code: %s", zbx_mock_error_string(error));

	if (0 == strcasecmp(str, "succeed"))
		return SYSINFO_RET_OK;

	if (0 != strcasecmp(str, "fail"))
		fail_msg("Incorrect return code '%s'", str);

	return SYSINFO_RET_FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*itemkey = "system.cpu.intr";
	AGENT_RESULT	result;
	AGENT_REQUEST	request;
	int		ret;

	ZBX_UNUSED(state);

	init_result(&result);
	init_request(&request);

	if (SUCCEED != parse_item_key(itemkey, &request))
		fail_msg("Invalid item key format '%s'", itemkey);

	if (read_yaml_ret() != (ret = SYSTEM_CPU_INTR(&request, &result)))
		fail_msg("unexpected return code '%s'", zbx_sysinfo_ret_string(ret));

	if (SYSINFO_RET_OK == ret)
	{
		zbx_uint64_t	interr;

		if (NULL == GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");

		if ((interr = zbx_mock_get_parameter_uint64("out.interrupts_since_boot")) != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, interr, result.ui64);
	}
	else if (NULL == GET_MSG_RESULT(&result))
		fail_msg("result does not contain failure message");

	free_request(&request);
	free_result(&result);
}

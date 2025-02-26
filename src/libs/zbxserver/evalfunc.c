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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"
#include "valuecache.h"
#include "evalfunc.h"
#include "zbxregexp.h"

typedef enum
{
	ZBX_PARAM_OPTIONAL,
	ZBX_PARAM_MANDATORY
}
zbx_param_type_t;

typedef enum
{
	ZBX_VALUE_SECONDS,
	ZBX_VALUE_NVALUES
}
zbx_value_type_t;

static const char	*zbx_type_string(zbx_value_type_t type)
{
	switch (type)
	{
		case ZBX_VALUE_SECONDS:
			return "sec";
		case ZBX_VALUE_NVALUES:
			return "num";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return "unknown";
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_function_parameter_int                                       *
 *                                                                            *
 * Purpose: get the value of sec|#num trigger function parameter              *
 *                                                                            *
 * Parameters: parameters     - [IN] trigger function parameters              *
 *             Nparam         - [IN] specifies which parameter to extract     *
 *             parameter_type - [IN] specifies whether parameter is mandatory *
 *                              or optional                                   *
 *             value          - [OUT] parameter value (preserved as is if the *
 *                              parameter is optional and empty)              *
 *             type           - [OUT] parameter value type (number of seconds *
 *                              or number of values)                          *
 *                                                                            *
 * Return value: SUCCEED - parameter is valid                                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	get_function_parameter_int(const char *parameters, int Nparam, zbx_param_type_t parameter_type,
		int *value, zbx_value_type_t *type)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if ('\0' == *parameter)
	{
		switch (parameter_type)
		{
			case ZBX_PARAM_OPTIONAL:
				ret = SUCCEED;
				break;
			case ZBX_PARAM_MANDATORY:
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
	else if ('#' == *parameter)
	{
		*type = ZBX_VALUE_NVALUES;
		if (SUCCEED == is_uint31(parameter + 1, value) && 0 < *value)
			ret = SUCCEED;
	}
	else if ('-' == *parameter)
	{
		if (SUCCEED == is_time_suffix(parameter + 1, value, ZBX_LENGTH_UNLIMITED))
		{
			*value = -(*value);
			*type = ZBX_VALUE_SECONDS;
			ret = SUCCEED;
		}
	}
	else if (SUCCEED == is_time_suffix(parameter, value, ZBX_LENGTH_UNLIMITED))
	{
		*type = ZBX_VALUE_SECONDS;
		ret = SUCCEED;
	}

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() type:%s value:%d", __func__, zbx_type_string(*type), *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_function_parameter_uint64(const char *parameters, int Nparam, zbx_uint64_t *value)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == (ret = is_uint64(parameter, value)))
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:" ZBX_FS_UI64, __func__, *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_function_parameter_float(const char *parameters, int Nparam, unsigned char flags, double *value)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == (ret = is_double_suffix(parameter, flags)))
	{
		*value = str2double(parameter);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:" ZBX_FS_DBL, __func__, *value);
	}

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_function_parameter_str(const char *parameters, int Nparam, char **value)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (*value = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() value:'%s'", __func__, *value);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGEVENTID                                              *
 *                                                                            *
 * Purpose: evaluate function 'logeventid' for the item                       *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - regex string for event id matching                 *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGEVENTID(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char			*arg1 = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	regexps;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&regexps);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 < num_param(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 1, &arg1))
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if ('@' == *arg1)
	{
		DCget_expressions_by_name(&regexps, arg1 + 1);

		if (0 == regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist", arg1 + 1);
			goto out;
		}
	}

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, ts, &vc_value))
	{
		char	logeventid[16];
		int	regexp_ret;

		zbx_snprintf(logeventid, sizeof(logeventid), "%d", vc_value.value.log->logeventid);

		if (FAIL == (regexp_ret = regexp_match_ex(&regexps, logeventid, arg1, ZBX_CASE_SENSITIVE)))
		{
			*error = zbx_dsprintf(*error, "invalid regular expression \"%s\"", arg1);
		}
		else
		{
			if (ZBX_REGEXP_MATCH == regexp_ret)
				*value = zbx_strdup(*value, "1");
			else if (ZBX_REGEXP_NO_MATCH == regexp_ret)
				*value = zbx_strdup(*value, "0");

			ret = SUCCEED;
		}

		zbx_history_record_clear(&vc_value, item->value_type);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGEVENTID is empty");
		*error = zbx_strdup(*error, "cannot get values from value cache");
	}
out:
	zbx_free(arg1);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGSOURCE                                               *
 *                                                                            *
 * Purpose: evaluate function 'logsource' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - ignored                                            *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSOURCE(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char			*arg1 = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	regexps;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&regexps);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 < num_param(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 1, &arg1))
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if ('@' == *arg1)
	{
		DCget_expressions_by_name(&regexps, arg1 + 1);

		if (0 == regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist", arg1 + 1);
			goto out;
		}
	}

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, ts, &vc_value))
	{
		switch (regexp_match_ex(&regexps, ZBX_NULL2EMPTY_STR(vc_value.value.log->source), arg1, ZBX_CASE_SENSITIVE))
		{
			case ZBX_REGEXP_MATCH:
				*value = zbx_strdup(*value, "1");
				ret = SUCCEED;
				break;
			case ZBX_REGEXP_NO_MATCH:
				*value = zbx_strdup(*value, "0");
				ret = SUCCEED;
				break;
			case FAIL:
				*error = zbx_dsprintf(*error, "invalid regular expression");
		}

		zbx_history_record_clear(&vc_value, item->value_type);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSOURCE is empty");
		*error = zbx_strdup(*error, "cannot get values from value cache");
	}
out:
	zbx_free(arg1);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGSEVERITY                                             *
 *                                                                            *
 * Purpose: evaluate function 'logseverity' for the item                      *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSEVERITY(char **value, DC_EVALUATE_ITEM *item, const zbx_timespec_t *ts, char **error)
{
	int			ret = FAIL;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, ts, &vc_value))
	{
		size_t	value_alloc = 0, value_offset = 0;

		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", vc_value.value.log->severity);
		zbx_history_record_clear(&vc_value, item->value_type);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSEVERITY is empty");
		*error = zbx_strdup(*error, "cannot get value from value cache");
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#define OP_UNKNOWN	-1
#define OP_EQ		0
#define OP_NE		1
#define OP_GT		2
#define OP_GE		3
#define OP_LT		4
#define OP_LE		5
#define OP_LIKE		6
#define OP_REGEXP	7
#define OP_IREGEXP	8
#define OP_BAND		9
#define OP_MAX		10

static void	count_one_ui64(int *count, int op, zbx_uint64_t value, zbx_uint64_t pattern, zbx_uint64_t mask)
{
	switch (op)
	{
		case OP_EQ:
			if (value == pattern)
				(*count)++;
			break;
		case OP_NE:
			if (value != pattern)
				(*count)++;
			break;
		case OP_GT:
			if (value > pattern)
				(*count)++;
			break;
		case OP_GE:
			if (value >= pattern)
				(*count)++;
			break;
		case OP_LT:
			if (value < pattern)
				(*count)++;
			break;
		case OP_LE:
			if (value <= pattern)
				(*count)++;
			break;
		case OP_BAND:
			if ((value & mask) == pattern)
				(*count)++;
	}
}

static void	count_one_dbl(int *count, int op, double value, double pattern)
{
	switch (op)
	{
		case OP_EQ:
			if (SUCCEED == zbx_double_compare(value, pattern))
				(*count)++;
			break;
		case OP_NE:
			if (FAIL == zbx_double_compare(value, pattern))
				(*count)++;
			break;
		case OP_GT:
			if (value - pattern > ZBX_DOUBLE_EPSILON)
				(*count)++;
			break;
		case OP_GE:
			if (value - pattern >= -ZBX_DOUBLE_EPSILON)
				(*count)++;
			break;
		case OP_LT:
			if (pattern - value > ZBX_DOUBLE_EPSILON)
				(*count)++;
			break;
		case OP_LE:
			if (pattern - value >= -ZBX_DOUBLE_EPSILON)
				(*count)++;
	}
}

static void	count_one_str(int *count, int op, const char *value, const char *pattern, zbx_vector_ptr_t *regexps)
{
	int	res;

	switch (op)
	{
		case OP_EQ:
			if (0 == strcmp(value, pattern))
				(*count)++;
			break;
		case OP_NE:
			if (0 != strcmp(value, pattern))
				(*count)++;
			break;
		case OP_LIKE:
			if (NULL != strstr(value, pattern))
				(*count)++;
			break;
		case OP_REGEXP:
			if (ZBX_REGEXP_MATCH == (res = regexp_match_ex(regexps, value, pattern, ZBX_CASE_SENSITIVE)))
				(*count)++;
			else if (FAIL == res)
				*count = FAIL;
			break;
		case OP_IREGEXP:
			if (ZBX_REGEXP_MATCH == (res = regexp_match_ex(regexps, value, pattern, ZBX_IGNORE_CASE)))
				(*count)++;
			else if (FAIL == res)
				*count = FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_COUNT                                                   *
 *                                                                            *
 * Purpose: evaluate function 'count' for the item                            *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - up to four comma-separated fields:                *
 *                            (1) number of seconds/values                    *
 *                            (2) value to compare with (optional)            *
 *                                Becomes mandatory for numeric items if 3rd  *
 *                                parameter is specified and is not "regexp"  *
 *                                or "iregexp". With "band" can take one of   *
 *                                2 forms:                                    *
 *                                  - value_to_compare_with/mask              *
 *                                  - mask                                    *
 *                            (3) comparison operator (optional)              *
 *                            (4) time shift (optional)                       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_COUNT(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				arg1, op = OP_UNKNOWN, numeric_search, nparams, count = 0, i, ret = FAIL;
	int				seconds = 0, nvalues = 0;
	char				*arg2 = NULL, *arg2_2 = NULL, *arg3 = NULL, buf[ZBX_MAX_UINT64_LEN];
	double				arg2_dbl;
	zbx_uint64_t			arg2_ui64, arg2_2_ui64;
	zbx_value_type_t		arg1_type;
	zbx_vector_ptr_t		regexps;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;
	size_t				value_alloc = 0, value_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&regexps);
	zbx_history_record_vector_create(&values);

	numeric_search = (ITEM_VALUE_TYPE_UINT64 == item->value_type || ITEM_VALUE_TYPE_FLOAT == item->value_type);

	if (4 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 <= nparams && SUCCEED != get_function_parameter_str(parameters, 2, &arg2))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (3 <= nparams && SUCCEED != get_function_parameter_str(parameters, 3, &arg3))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (4 <= nparams)
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 4, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	if (NULL == arg3 || '\0' == *arg3)
		op = (0 != numeric_search ? OP_EQ : OP_LIKE);
	else if (0 == strcmp(arg3, "eq"))
		op = OP_EQ;
	else if (0 == strcmp(arg3, "ne"))
		op = OP_NE;
	else if (0 == strcmp(arg3, "gt"))
		op = OP_GT;
	else if (0 == strcmp(arg3, "ge"))
		op = OP_GE;
	else if (0 == strcmp(arg3, "lt"))
		op = OP_LT;
	else if (0 == strcmp(arg3, "le"))
		op = OP_LE;
	else if (0 == strcmp(arg3, "like"))
		op = OP_LIKE;
	else if (0 == strcmp(arg3, "regexp"))
		op = OP_REGEXP;
	else if (0 == strcmp(arg3, "iregexp"))
		op = OP_IREGEXP;
	else if (0 == strcmp(arg3, "band"))
		op = OP_BAND;

	if (OP_UNKNOWN == op)
	{
		*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for function COUNT", arg3);
		goto out;
	}

	numeric_search = (0 != numeric_search && OP_REGEXP != op && OP_IREGEXP != op);

	if (0 != numeric_search)
	{
		if (NULL != arg3 && '\0' != *arg3 && '\0' == *arg2)
		{
			*error = zbx_strdup(*error, "pattern must be provided along with operator for numeric values");
			goto out;
		}

		if (OP_LIKE == op)
		{
			*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting numeric values",
					arg3);
			goto out;
		}

		if (OP_BAND == op && ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting float values",
					arg3);
			goto out;
		}

		if (OP_BAND == op && NULL != (arg2_2 = strchr(arg2, '/')))
		{
			*arg2_2 = '\0';	/* end of the 1st part of the 2nd parameter (number to compare with) */
			arg2_2++;	/* start of the 2nd part of the 2nd parameter (mask) */
		}

		if (NULL != arg2 && '\0' != *arg2)
		{
			if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
			{
				if (OP_BAND != op)
				{
					if (SUCCEED != str2uint64whole(arg2, &arg2_ui64))
					{
						*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric unsigned"
								" value", arg2);
						goto out;
					}
				}
				else
				{
					if (SUCCEED != is_uint64(arg2, &arg2_ui64))
					{
						*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric unsigned"
								" value", arg2);
						goto out;
					}

					if (NULL != arg2_2)
					{
						if (SUCCEED != is_uint64(arg2_2, &arg2_2_ui64))
						{
							*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric"
									" unsigned value", arg2_2);
							goto out;
						}
					}
					else
						arg2_2_ui64 = arg2_ui64;
				}
			}
			else
			{
				if (SUCCEED != is_double_suffix(arg2, ZBX_FLAG_DOUBLE_SUFFIX))
				{
					*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric float value",
							arg2);
					goto out;
				}

				arg2_dbl = str2double(arg2);
			}
		}
	}
	else if (OP_LIKE != op && OP_REGEXP != op && OP_IREGEXP != op && OP_EQ != op && OP_NE != op)
	{
		*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting textual values", arg3);
		goto out;
	}

	if ((OP_REGEXP == op || OP_IREGEXP == op) && '@' == *arg2)
	{
		DCget_expressions_by_name(&regexps, arg2 + 1);

		if (0 == regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist", arg2 + 1);
			goto out;
		}
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	/* skip counting values one by one if both pattern and operator are empty or "" is searched in text values */
	if ((NULL != arg2 && '\0' != *arg2) || (NULL != arg3 && '\0' != *arg3 &&
			OP_LIKE != op && OP_REGEXP != op && OP_IREGEXP != op))
	{
		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_UINT64:
				if (0 != numeric_search)
				{
					for (i = 0; i < values.values_num; i++)
					{
						count_one_ui64(&count, op, values.values[i].value.ui64, arg2_ui64,
								arg2_2_ui64);
					}
				}
				else
				{
					for (i = 0; i < values.values_num && FAIL != count; i++)
					{
						zbx_snprintf(buf, sizeof(buf), ZBX_FS_UI64,
								values.values[i].value.ui64);
						count_one_str(&count, op, buf, arg2, &regexps);
					}
				}
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				if (0 != numeric_search)
				{
					for (i = 0; i < values.values_num; i++)
						count_one_dbl(&count, op, values.values[i].value.dbl, arg2_dbl);
				}
				else
				{
					for (i = 0; i < values.values_num && FAIL != count; i++)
					{
						zbx_snprintf(buf, sizeof(buf), ZBX_FS_DBL_EXT(4),
								values.values[i].value.dbl);
						count_one_str(&count, op, buf, arg2, &regexps);
					}
				}
				break;
			case ITEM_VALUE_TYPE_LOG:
				for (i = 0; i < values.values_num && FAIL != count; i++)
					count_one_str(&count, op, values.values[i].value.log->value, arg2, &regexps);
				break;
			default:
				for (i = 0; i < values.values_num && FAIL != count; i++)
					count_one_str(&count, op, values.values[i].value.str, arg2, &regexps);
		}

		if (FAIL == count)
		{
			*error = zbx_strdup(*error, "invalid regular expression");
			goto out;
		}
	}
	else
		count = values.values_num;

	zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", count);

	ret = SUCCEED;
out:
	zbx_free(arg2);
	zbx_free(arg3);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef OP_UNKNOWN
#undef OP_EQ
#undef OP_NE
#undef OP_GT
#undef OP_GE
#undef OP_LT
#undef OP_LE
#undef OP_LIKE
#undef OP_REGEXP
#undef OP_IREGEXP
#undef OP_BAND
#undef OP_MAX

/******************************************************************************
 *                                                                            *
 * Function: evaluate_SUM                                                     *
 *                                                                            *
 * Purpose: evaluate function 'sum' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_SUM(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				nparams, arg1, i, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	history_value_t			result;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 == nparams)
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
	{
		result.dbl = 0;

		for (i = 0; i < values.values_num; i++)
			result.dbl += values.values[i].value.dbl;
	}
	else
	{
		result.ui64 = 0;

		for (i = 0; i < values.values_num; i++)
			result.ui64 += values.values[i].value.ui64;
	}

	*value = zbx_history_value2str_dyn(&result, item->value_type);
	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_AVG                                                     *
 *                                                                            *
 * Purpose: evaluate function 'avg' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_AVG(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				nparams, arg1, ret = FAIL, i, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 == nparams)
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		double	avg = 0;
		size_t	value_alloc = 0, value_offset = 0;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
				avg += values.values[i].value.dbl / (i + 1) - avg / (i + 1);
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
				avg += values.values[i].value.ui64;

			avg = avg / values.values_num;
		}
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64, avg);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for AVG is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LAST                                                    *
 *                                                                            *
 * Purpose: evaluate functions 'last' and 'prev' for the item                 *
 *                                                                            *
 * Parameters: value - dynamic buffer                                         *
 *             item - item (performance metric)                               *
 *             parameters - Nth last value and time shift (optional)          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LAST(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				arg1 = 1, ret = FAIL;
	zbx_value_type_t		arg1_type = ZBX_VALUE_NVALUES;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_OPTIONAL, &arg1, &arg1_type))
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (ZBX_VALUE_NVALUES != arg1_type)
		arg1 = 1;	/* non-# first parameter is ignored to support older syntax "last(0)" */

	if (2 == num_param(parameters))
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	if (SUCCEED == zbx_vc_get_values(item->itemid, item->value_type, &values, 0, arg1, &ts_end))
	{
		if (arg1 <= values.values_num)
		{
			char	*tmp;

			tmp = zbx_history_value2str_dyn(&values.values[arg1 - 1].value, item->value_type);

			if (ITEM_VALUE_TYPE_STR == item->value_type ||
					ITEM_VALUE_TYPE_TEXT == item->value_type ||
					ITEM_VALUE_TYPE_LOG == item->value_type)
			{
				size_t	len;
				char	*ptr;

				len = zbx_get_escape_string_len(tmp, "\"\\");
				ptr = *value = zbx_malloc(NULL, len + 3);
				*ptr++ = '"';
				zbx_escape_string(ptr, len + 1, tmp, "\"\\");
				ptr += len;
				*ptr++ = '"';
				*ptr = '\0';
				zbx_free(tmp);
			}
			else
				*value = tmp;

			ret = SUCCEED;
		}
		else
		{
			*error = zbx_strdup(*error, "not enough data");
			goto out;
		}
	}
	else
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* flags for evaluate_MIN_or_MAX() */
#define EVALUATE_MIN	0
#define EVALUATE_MAX	1

#define LOOP_FIND_MIN_OR_MAX(type, mode_op)							\
	for (i = 1; i < values.values_num; i++)							\
	{											\
		if (values.values[i].value.type mode_op values.values[index].value.type)	\
			index = i;								\
	}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'min' or 'max' for the item                     *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_MIN_or_MAX(char **value, const DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error, int min_or_max)
{
	int				nparams, arg1, i, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 == nparams)
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		int	index = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			if (EVALUATE_MIN == min_or_max)
			{
				LOOP_FIND_MIN_OR_MAX(ui64, <);
			}
			else
			{
				LOOP_FIND_MIN_OR_MAX(ui64, >);
			}
		}
		else
		{
			if (EVALUATE_MIN == min_or_max)
			{
				LOOP_FIND_MIN_OR_MAX(dbl, <);
			}
			else
			{
				LOOP_FIND_MIN_OR_MAX(dbl, >);
			}
		}

		*value = zbx_history_value2str_dyn(&values.values[index].value, item->value_type);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for MIN or MAX is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	__history_record_float_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d1->value.dbl, d2->value.dbl);

	return 0;
}

static int	__history_record_uint64_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d1->value.ui64, d2->value.ui64);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_PERCENTILE                                              *
 *                                                                            *
 * Purpose: evaluate function 'percentile' for the item                       *
 *                                                                            *
 * Parameters: item       - [IN] item (performance metric)                    *
 *             parameters - [IN] seconds/values, time shift (optional),       *
 *                               percentage                                   *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL    - failed to evaluate function                        *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_PERCENTILE(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				nparams, arg1, time_shift = 0, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type, time_shift_type = ZBX_VALUE_SECONDS;
	double				percentage;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (3 != (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift, &time_shift_type) ||
			ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	if (SUCCEED != get_function_parameter_float(parameters, 3, ZBX_FLAG_DOUBLE_PLAIN, &percentage) ||
			0.0 > percentage || 100.0 < percentage)
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		int	index;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
			zbx_vector_history_record_sort(&values, (zbx_compare_func_t)__history_record_float_compare);
		else
			zbx_vector_history_record_sort(&values, (zbx_compare_func_t)__history_record_uint64_compare);

		if (0 == percentage)
			index = 1;
		else
			index = (int)ceil(values.values_num * (percentage / 100));

		*value = zbx_history_value2str_dyn(&values.values[index - 1].value, item->value_type);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for PERCENTILE is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_DELTA                                                   *
 *                                                                            *
 * Purpose: evaluate function 'delta' for the item                            *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_DELTA(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				nparams, arg1, ret = FAIL, i, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 == nparams)
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		history_value_t		result;
		int			index_min = 0, index_max = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.ui64 > values.values[index_max].value.ui64)
					index_max = i;

				if (values.values[i].value.ui64 < values.values[index_min].value.ui64)
					index_min = i;
			}

			result.ui64 = values.values[index_max].value.ui64 - values.values[index_min].value.ui64;
		}
		else
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.dbl > values.values[index_max].value.dbl)
					index_max = i;

				if (values.values[i].value.dbl < values.values[index_min].value.dbl)
					index_min = i;
			}

			result.dbl = values.values[index_max].value.dbl - values.values[index_min].value.dbl;
		}

		*value = zbx_history_value2str_dyn(&result, item->value_type);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for DELTA is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_NODATA                                                  *
 *                                                                            *
 * Purpose: evaluate function 'nodata' for the item                           *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_NODATA(char **value, DC_EVALUATE_ITEM *item, const char *parameters, char **error)
{
	int				arg1, num, period, lazy = 1, ret = FAIL;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts;
	char				*arg2 = NULL;
	zbx_proxy_suppress_t		nodata_win;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (2 < (num = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) ||
			ZBX_VALUE_SECONDS != arg1_type || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (1 < num && (SUCCEED != get_function_parameter_str(parameters, 2, &arg2) ||
			('\0' != *arg2 && 0 != (lazy = strcmp("strict", arg2)))))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	zbx_timespec(&ts);
	nodata_win.flags = ZBX_PROXY_SUPPRESS_DISABLE;

	if (0 != item->proxy_hostid && 0 != lazy)
	{
		int			lastaccess;

		if (SUCCEED != DCget_proxy_nodata_win(item->proxy_hostid, &nodata_win, &lastaccess))
		{
			*error = zbx_strdup(*error, "cannot retrieve proxy last access");
			goto out;
		}

		period = arg1 + (ts.sec - lastaccess);
	}
	else
		period = arg1;

	if (SUCCEED == zbx_vc_get_values(item->itemid, item->value_type, &values, period, 1, &ts) &&
			1 == values.values_num)
	{
		*value = zbx_strdup(*value, "0");
	}
	else
	{
		int	seconds;

		if (SUCCEED != DCget_data_expected_from(item->itemid, &seconds))
		{
			*error = zbx_strdup(*error, "item does not exist, is disabled or belongs to a disabled host");
			goto out;
		}

		if (seconds + arg1 > ts.sec)
		{
			*error = zbx_strdup(*error,
					"item does not have enough data after server start or item creation");
			goto out;
		}

		if (0 != (nodata_win.flags & ZBX_PROXY_SUPPRESS_ACTIVE))
		{
			*error = zbx_strdup(*error, "historical data transfer from proxy is still in progress");
			goto out;
		}

		*value = zbx_strdup(*value, "1");

		if (0 != item->proxy_hostid && 0 != lazy)
		{
			zabbix_log(LOG_LEVEL_TRACE, "Nodata in %s() flag:%d values_num:%d start_time:%d period:%d",
					__func__, nodata_win.flags, nodata_win.values_num, ts.sec - period, period);
		}
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);
	zbx_free(arg2);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_ABSCHANGE                                               *
 *                                                                            *
 * Purpose: evaluate function 'abschange' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_ABSCHANGE(char **value, DC_EVALUATE_ITEM *item, const zbx_timespec_t *ts, char **error)
{
	int				ret = FAIL;
	size_t				value_alloc = 0, value_offset = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, 0, 2, ts) ||
			2 > values.values_num)
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64,
					fabs(values.values[0].value.dbl - values.values[1].value.dbl));
			break;
		case ITEM_VALUE_TYPE_UINT64:
			/* to avoid overflow */
			if (values.values[0].value.ui64 >= values.values[1].value.ui64)
			{
				zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_UI64,
						values.values[0].value.ui64 - values.values[1].value.ui64);
			}
			else
			{
				zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_UI64,
						values.values[1].value.ui64 - values.values[0].value.ui64);
			}
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		default:
			*error = zbx_strdup(*error, "invalid value type");
			goto out;
	}
	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_CHANGE                                                  *
 *                                                                            *
 * Purpose: evaluate function 'change' for the item                           *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_CHANGE(char **value, DC_EVALUATE_ITEM *item, const zbx_timespec_t *ts, char **error)
{
	int				ret = FAIL;
	size_t				value_alloc = 0, value_offset = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, 0, 2, ts) ||
			2 > values.values_num)
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64,
					values.values[0].value.dbl - values.values[1].value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			/* to avoid overflow */
			if (values.values[0].value.ui64 >= values.values[1].value.ui64)
				zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_UI64,
						values.values[0].value.ui64 - values.values[1].value.ui64);
			else
				zbx_snprintf_alloc(value, &value_alloc, &value_offset, "-" ZBX_FS_UI64,
						values.values[1].value.ui64 - values.values[0].value.ui64);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		default:
			*error = zbx_strdup(*error, "invalid value type");
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_DIFF                                                    *
 *                                                                            *
 * Purpose: evaluate function 'diff' for the item                             *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_DIFF(char **value, DC_EVALUATE_ITEM *item, const zbx_timespec_t *ts, char **error)
{
	int				ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, 0, 2, ts) || 2 > values.values_num)
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED == zbx_double_compare(values.values[0].value.dbl, values.values[1].value.dbl))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (values.values[0].value.ui64 == values.values[1].value.ui64)
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				*value = zbx_strdup(*value, "0");
			else
				*value = zbx_strdup(*value, "1");
			break;
		default:
			*error = zbx_strdup(*error, "invalid value type");
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_STR                                                     *
 *                                                                            *
 * Purpose: evaluate function 'str' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - <string>[,seconds]                                *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result stored in 'value'   *
 *               FAIL - failed to match the regular expression                *
 *               NOTSUPPORTED - invalid regular expression                    *
 *                                                                            *
 ******************************************************************************/

#define ZBX_FUNC_STR		1
#define ZBX_FUNC_REGEXP		2
#define ZBX_FUNC_IREGEXP	3

static int	evaluate_STR_one(int func, zbx_vector_ptr_t *regexps, const char *value, const char *arg1)
{
	switch (func)
	{
		case ZBX_FUNC_STR:
			if (NULL != strstr(value, arg1))
				return SUCCEED;
			break;
		case ZBX_FUNC_REGEXP:
			switch (regexp_match_ex(regexps, value, arg1, ZBX_CASE_SENSITIVE))
			{
				case ZBX_REGEXP_MATCH:
					return SUCCEED;
				case FAIL:
					return NOTSUPPORTED;
			}
			break;
		case ZBX_FUNC_IREGEXP:
			switch (regexp_match_ex(regexps, value, arg1, ZBX_IGNORE_CASE))
			{
				case ZBX_REGEXP_MATCH:
					return SUCCEED;
				case FAIL:
					return NOTSUPPORTED;
			}
			break;
	}

	return FAIL;
}

static int	evaluate_STR(char **value, DC_EVALUATE_ITEM *item, const char *function, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char				*arg1 = NULL;
	int				arg2 = 1, func, found = 0, i, ret = FAIL, seconds = 0, nvalues = 0, nparams;
	int				str_one_ret;
	zbx_value_type_t		arg2_type = ZBX_VALUE_NVALUES;
	zbx_vector_ptr_t		regexps;
	zbx_vector_history_record_t	values;
	size_t				value_alloc = 0, value_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&regexps);
	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (0 == strcmp(function, "str"))
		func = ZBX_FUNC_STR;
	else if (0 == strcmp(function, "regexp"))
		func = ZBX_FUNC_REGEXP;
	else if (0 == strcmp(function, "iregexp"))
		func = ZBX_FUNC_IREGEXP;
	else
		goto out;

	if (2 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 1, &arg1))
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (2 == nparams)
	{
		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &arg2, &arg2_type) ||
				0 >= arg2)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}
	}

	if ((ZBX_FUNC_REGEXP == func || ZBX_FUNC_IREGEXP == func) && '@' == *arg1)
	{
		DCget_expressions_by_name(&regexps, arg1 + 1);

		if (0 == regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist", arg1 + 1);
			goto out;
		}
	}

	switch (arg2_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg2;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg2;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, ts))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 != values.values_num)
	{
		/* at this point the value type can be only str, text or log */
		if (ITEM_VALUE_TYPE_LOG == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
			{
				if (SUCCEED == (str_one_ret = evaluate_STR_one(func, &regexps,
						values.values[i].value.log->value, arg1)))
				{
					found = 1;
					break;
				}

				if (NOTSUPPORTED == str_one_ret)
				{
					*error = zbx_dsprintf(*error, "invalid regular expression \"%s\"", arg1);
					goto out;
				}
			}
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
			{
				if (SUCCEED == (str_one_ret = evaluate_STR_one(func, &regexps,
						values.values[i].value.str, arg1)))
				{
					found = 1;
					break;
				}

				if (NOTSUPPORTED == str_one_ret)
				{
					*error = zbx_dsprintf(*error, "invalid regular expression \"%s\"", arg1);
					goto out;
				}
			}
		}
	}

	zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", found);
	ret = SUCCEED;
out:
	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(arg1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef ZBX_FUNC_STR
#undef ZBX_FUNC_REGEXP
#undef ZBX_FUNC_IREGEXP

/******************************************************************************
 *                                                                            *
 * Function: evaluate_STRLEN                                                  *
 *                                                                            *
 * Purpose: evaluate function 'strlen' for the item                           *
 *                                                                            *
 * Parameters: value - dynamic buffer                                         *
 *             item - item (performance metric)                               *
 *             parameters - Nth last value and time shift (optional)          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_STRLEN(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	int				arg1 = 1, ret = FAIL;
	zbx_value_type_t		arg1_type = ZBX_VALUE_NVALUES;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_OPTIONAL, &arg1, &arg1_type))
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (ZBX_VALUE_NVALUES != arg1_type)
		arg1 = 1;	/* non-# first parameter is ignored to support older syntax "strlen(0)" */

	if (2 == num_param(parameters))
	{
		int			time_shift = 0;
		zbx_value_type_t	time_shift_type = ZBX_VALUE_SECONDS;

		if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift,
				&time_shift_type) || ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
		{
			*error = zbx_strdup(*error, "invalid second parameter");
			goto out;
		}

		ts_end.sec -= time_shift;
	}

	if (SUCCEED == zbx_vc_get_values(item->itemid, item->value_type, &values, 0, arg1, &ts_end))
	{
		if (arg1 <= values.values_num)
		{
			size_t	sz, value_alloc = 0, value_offset = 0;
			char	*hist_val;

			hist_val = zbx_history_value2str_dyn(&values.values[arg1 - 1].value, item->value_type);
			sz = zbx_strlen_utf8(hist_val);
			zbx_free(hist_val);

			zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_SIZE_T, sz);
			ret = SUCCEED;
		}
		else
		{
			*error = zbx_strdup(*error, "not enough data");
			goto out;
		}
	}
	else
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
	}

	zbx_history_record_vector_destroy(&values, item->value_type);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_FUZZYTIME                                               *
 *                                                                            *
 * Purpose: evaluate function 'fuzzytime' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FUZZYTIME(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int			arg1, ret = FAIL;
	zbx_value_type_t	arg1_type;
	zbx_history_record_t	vc_value;
	zbx_uint64_t		fuzlow, fuzhig;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 < num_param(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (ZBX_VALUE_SECONDS != arg1_type || ts->sec <= arg1)
	{
		*error = zbx_strdup(*error, "invalid argument type or value");
		goto out;
	}

	if (SUCCEED != zbx_vc_get_value(item->itemid, item->value_type, ts, &vc_value))
	{
		*error = zbx_strdup(*error, "cannot get value from value cache");
		goto out;
	}

	fuzlow = (int)(ts->sec - arg1);
	fuzhig = (int)(ts->sec + arg1);

	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
	{
		if (vc_value.value.ui64 >= fuzlow && vc_value.value.ui64 <= fuzhig)
			*value = zbx_strdup(*value, "1");
		else
			*value = zbx_strdup(*value, "0");
	}
	else
	{
		if (vc_value.value.dbl >= fuzlow && vc_value.value.dbl <= fuzhig)
			*value = zbx_strdup(*value, "1");
		else
			*value = zbx_strdup(*value, "0");
	}

	zbx_history_record_clear(&vc_value, item->value_type);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_BAND                                                    *
 *                                                                            *
 * Purpose: evaluate logical bitwise function 'and' for the item              *
 *                                                                            *
 * Parameters: value - dynamic buffer                                         *
 *             item - item (performance metric)                               *
 *             parameters - up to 3 comma-separated fields:                   *
 *                            (1) same as the 1st parameter for function      *
 *                                evaluate_LAST() (see documentation of       *
 *                                trigger function last()),                   *
 *                            (2) mask to bitwise AND with (mandatory),       *
 *                            (3) same as the 2nd parameter for function      *
 *                                evaluate_LAST() (see documentation of       *
 *                                trigger function last()).                   *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_BAND(char **value, DC_EVALUATE_ITEM *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	char		*last_parameters = NULL;
	int		nparams, ret = FAIL;
	zbx_uint64_t	last_uint64, mask;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto clean;
	}

	if (3 < (nparams = num_param(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto clean;
	}

	if (SUCCEED != get_function_parameter_uint64(parameters, 2, &mask))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto clean;
	}

	/* prepare the 1st and the 3rd parameter for passing to evaluate_LAST() */
	last_parameters = zbx_strdup(NULL, parameters);
	remove_param(last_parameters, 2);

	if (SUCCEED == evaluate_LAST(value, item, last_parameters, ts, error))
	{
		ZBX_STR2UINT64(last_uint64, *value);
		/* 'and' bit operation cannot be larger than the source value, */
		/* so the result can just be copied in value buffer            */
		zbx_snprintf(*value, strlen(*value) + 1, ZBX_FS_UI64, last_uint64 & (zbx_uint64_t)mask);
		ret = SUCCEED;
	}

	zbx_free(last_parameters);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_FORECAST                                                *
 *                                                                            *
 * Purpose: evaluate function 'forecast' for the item                         *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FORECAST(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char				*fit_str = NULL, *mode_str = NULL;
	double				*t = NULL, *x = NULL;
	int				nparams, time, arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift = 0;
	zbx_value_type_t		time_type, time_shift_type = ZBX_VALUE_SECONDS, arg1_type;
	unsigned int			k = 0;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			zero_time;
	zbx_fit_t			fit;
	zbx_mode_t			mode;
	zbx_timespec_t			ts_end = *ts;
	size_t				value_alloc = 0, value_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (3 > (nparams = num_param(parameters)) || nparams > 5)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift, &time_shift_type) ||
			ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 3, ZBX_PARAM_MANDATORY, &time, &time_type) ||
			ZBX_VALUE_SECONDS != time_type)
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (4 <= nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 4, &fit_str) ||
				SUCCEED != zbx_fit_code(fit_str, &fit, &k, error))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}
	}
	else
	{
		fit = FIT_LINEAR;
	}

	if (5 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 5, &mode_str) ||
				SUCCEED != zbx_mode_code(mode_str, &mode, error))
		{
			*error = zbx_strdup(*error, "invalid fifth parameter");
			goto out;
		}
	}
	else
	{
		mode = MODE_VALUE;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		t = (double *)zbx_malloc(t, values.values_num * sizeof(double));
		x = (double *)zbx_malloc(x, values.values_num * sizeof(double));

		zero_time.sec = values.values[values.values_num - 1].timestamp.sec;
		zero_time.ns = values.values[values.values_num - 1].timestamp.ns;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.dbl;
			}
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.ui64;
			}
		}

		zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64, zbx_forecast(t, x,
				values.values_num, ts->sec - zero_time.sec - 1.0e-9 * (zero_time.ns + 1), time, fit, k,
				mode));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "no data available");
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64, ZBX_MATH_ERROR);
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(fit_str);
	zbx_free(mode_str);

	zbx_free(t);
	zbx_free(x);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_TIMELEFT                                                *
 *                                                                            *
 * Purpose: evaluate function 'timeleft' for the item                         *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_TIMELEFT(char **value, DC_EVALUATE_ITEM *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char				*fit_str = NULL;
	double				*t = NULL, *x = NULL, threshold;
	int				nparams, arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift = 0;
	zbx_value_type_t		arg1_type, time_shift_type = ZBX_VALUE_SECONDS;
	unsigned			k = 0;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			zero_time;
	zbx_fit_t			fit;
	zbx_timespec_t			ts_end = *ts;
	size_t				value_alloc = 0, value_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (3 > (nparams = num_param(parameters)) || nparams > 4)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 1, ZBX_PARAM_MANDATORY, &arg1, &arg1_type) || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid first parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_int(parameters, 2, ZBX_PARAM_OPTIONAL, &time_shift, &time_shift_type) ||
			ZBX_VALUE_SECONDS != time_shift_type || 0 > time_shift)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_float( parameters, 3, ZBX_FLAG_DOUBLE_SUFFIX, &threshold))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (4 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 4, &fit_str) ||
				SUCCEED != zbx_fit_code(fit_str, &fit, &k, error))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}
	}
	else
	{
		fit = FIT_LINEAR;
	}

	if ((FIT_EXPONENTIAL == fit || FIT_POWER == fit) && 0.0 >= threshold)
	{
		*error = zbx_strdup(*error, "exponential and power functions are always positive");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		t = (double *)zbx_malloc(t, values.values_num * sizeof(double));
		x = (double *)zbx_malloc(x, values.values_num * sizeof(double));

		zero_time.sec = values.values[values.values_num - 1].timestamp.sec;
		zero_time.ns = values.values[values.values_num - 1].timestamp.ns;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.dbl;
			}
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.ui64;
			}
		}

		zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64, zbx_timeleft(t, x,
				values.values_num, ts->sec - zero_time.sec - 1.0e-9 * (zero_time.ns + 1), threshold,
				fit, k));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "no data available");
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, ZBX_FS_DBL64, ZBX_MATH_ERROR);
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(fit_str);

	zbx_free(t);
	zbx_free(x);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_function                                                *
 *                                                                            *
 * Purpose: evaluate function                                                 *
 *                                                                            *
 * Parameters: item      - item to calculate function for                     *
 *             function  - function (for example, 'max')                      *
 *             parameter - parameter of the function                          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains its value   *
 *               FAIL - evaluation failed                                     *
 *                                                                            *
 ******************************************************************************/
int	evaluate_function(char **value, DC_EVALUATE_ITEM *item, const char *function, const char *parameter,
		const zbx_timespec_t *ts, char **error)
{
	int		ret;
	struct tm	*tm = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s:%s.%s(%s)'", __func__,
			item->host, item->key_orig, function, parameter);

	if (0 == strcmp(function, "last"))
	{
		ret = evaluate_LAST(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "prev"))
	{
		ret = evaluate_LAST(value, item, "#2", ts, error);
	}
	else if (0 == strcmp(function, "min"))
	{
		ret = evaluate_MIN_or_MAX(value, item, parameter, ts, error, EVALUATE_MIN);
	}
	else if (0 == strcmp(function, "max"))
	{
		ret = evaluate_MIN_or_MAX(value, item, parameter, ts, error, EVALUATE_MAX);
	}
	else if (0 == strcmp(function, "avg"))
	{
		ret = evaluate_AVG(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "sum"))
	{
		ret = evaluate_SUM(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "percentile"))
	{
		ret = evaluate_PERCENTILE(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "count"))
	{
		ret = evaluate_COUNT(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "delta"))
	{
		ret = evaluate_DELTA(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "nodata"))
	{
		ret = evaluate_NODATA(value, item, parameter, error);
	}
	else if (0 == strcmp(function, "date"))
	{
		size_t	value_alloc = 0, value_offset = 0;
		time_t	now = ts->sec;

		tm = localtime(&now);
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%.4d%.2d%.2d",
				tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "dayofweek"))
	{
		size_t	value_alloc = 0, value_offset = 0;
		time_t	now = ts->sec;

		tm = localtime(&now);
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", 0 == tm->tm_wday ? 7 : tm->tm_wday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "dayofmonth"))
	{
		size_t	value_alloc = 0, value_offset = 0;
		time_t	now = ts->sec;

		tm = localtime(&now);
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", tm->tm_mday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "time"))
	{
		size_t	value_alloc = 0, value_offset = 0;
		time_t	now = ts->sec;

		tm = localtime(&now);
		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%.2d%.2d%.2d", tm->tm_hour, tm->tm_min,
				tm->tm_sec);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "abschange"))
	{
		ret = evaluate_ABSCHANGE(value, item, ts, error);
	}
	else if (0 == strcmp(function, "change"))
	{
		ret = evaluate_CHANGE(value, item, ts, error);
	}
	else if (0 == strcmp(function, "diff"))
	{
		ret = evaluate_DIFF(value, item, ts, error);
	}
	else if (0 == strcmp(function, "str") || 0 == strcmp(function, "regexp") || 0 == strcmp(function, "iregexp"))
	{
		ret = evaluate_STR(value, item, function, parameter, ts, error);
	}
	else if (0 == strcmp(function, "strlen"))
	{
		ret = evaluate_STRLEN(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "now"))
	{
		size_t	value_alloc = 0, value_offset = 0;

		zbx_snprintf_alloc(value, &value_alloc, &value_offset, "%d", ts->sec);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "fuzzytime"))
	{
		ret = evaluate_FUZZYTIME(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "logeventid"))
	{
		ret = evaluate_LOGEVENTID(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "logseverity"))
	{
		ret = evaluate_LOGSEVERITY(value, item, ts, error);
	}
	else if (0 == strcmp(function, "logsource"))
	{
		ret = evaluate_LOGSOURCE(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "band"))
	{
		ret = evaluate_BAND(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "forecast"))
	{
		ret = evaluate_FORECAST(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "timeleft"))
	{
		ret = evaluate_TIMELEFT(value, item, parameter, ts, error);
	}
	else
	{
		*value = zbx_malloc(*value, 1);
		(*value)[0] = '\0';
		*error = zbx_strdup(*error, "function is not supported");
		ret = FAIL;
	}

	if (SUCCEED == ret)
		del_zeros(*value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s'", __func__, zbx_result_string(ret), *value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_uptime                                          *
 *                                                                            *
 * Purpose: Process suffix 'uptime'                                           *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_uptime(char *value, size_t max_len)
{
	double	secs, days;
	size_t	offset = 0;
	int	hours, mins;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (secs = round(atof(value))))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}

	days = floor(secs / SEC_PER_DAY);
	secs -= days * SEC_PER_DAY;

	hours = (int)(secs / SEC_PER_HOUR);
	secs -= (double)hours * SEC_PER_HOUR;

	mins = (int)(secs / SEC_PER_MIN);
	secs -= (double)mins * SEC_PER_MIN;

	if (0 != days)
	{
		if (1 == days)
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " day, ", days);
		else
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " days, ", days);
	}

	zbx_snprintf(value + offset, max_len - offset, "%02d:%02d:%02d", hours, mins, (int)secs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_s                                               *
 *                                                                            *
 * Purpose: Process suffix 's'                                                *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_s(char *value, size_t max_len)
{
	double	secs, n;
	size_t	offset = 0;
	int	n_unit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	secs = atof(value);

	if (0 == floor(fabs(secs) * 1000))
	{
		zbx_snprintf(value, max_len, "%s", (0 == secs ? "0s" : "< 1ms"));
		goto clean;
	}

	if (0 > (secs = round(secs * 1000) / 1000))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}
	else
		*value = '\0';

	if (0 != (n = floor(secs / SEC_PER_YEAR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) "y ", n);
		secs -= n * SEC_PER_YEAR;
		if (0 == n_unit)
			n_unit = 4;
	}

	if (0 != (n = floor(secs / SEC_PER_MONTH)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dM ", (int)n);
		secs -= n * SEC_PER_MONTH;
		if (0 == n_unit)
			n_unit = 3;
	}

	if (0 != (n = floor(secs / SEC_PER_DAY)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dd ", (int)n);
		secs -= n * SEC_PER_DAY;
		if (0 == n_unit)
			n_unit = 2;
	}

	if (4 > n_unit && 0 != (n = floor(secs / SEC_PER_HOUR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dh ", (int)n);
		secs -= n * SEC_PER_HOUR;
		if (0 == n_unit)
			n_unit = 1;
	}

	if (3 > n_unit && 0 != (n = floor(secs / SEC_PER_MIN)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dm ", (int)n);
		secs -= n * SEC_PER_MIN;
	}

	if (2 > n_unit && 0 != (n = floor(secs)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%ds ", (int)n);
		secs -= n;
	}

	if (1 > n_unit && 0 != (n = round(secs * 1000)))
		offset += zbx_snprintf(value + offset, max_len - offset, "%dms", (int)n);

	if (0 != offset && ' ' == value[--offset])
		value[offset] = '\0';
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: is_blacklisted_unit                                              *
 *                                                                            *
 * Purpose:  check if unit is blacklisted or not                              *
 *                                                                            *
 * Parameters: unit - unit to check                                           *
 *                                                                            *
 * Return value: SUCCEED - unit blacklisted                                   *
 *               FAIL - unit is not blacklisted                               *
 *                                                                            *
 ******************************************************************************/
static int	is_blacklisted_unit(const char *unit)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = str_in_list("%,ms,rpm,RPM", unit, ',');

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_units_no_kmgt                                          *
 *                                                                            *
 * Purpose: add only units to the value                                       *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *             units - units (bps, b, B, etc)                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_value_units_no_kmgt(char *value, size_t max_len, const char *units)
{
	const char	*minus = "";
	char		tmp[64];
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (value_double = atof(value)))
	{
		minus = "-";
		value_double = -value_double;
	}

	if (SUCCEED != zbx_double_compare(round(value_double), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s", minus, tmp, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_units_with_kmgt                                        *
 *                                                                            *
 * Purpose: add units with K,M,G,T prefix to the value                        *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *             units - units (bps, b, B, etc)                                 *
 *                                                                            *
 ******************************************************************************/
static void	add_value_units_with_kmgt(char *value, size_t max_len, const char *units)
{
	const char	*minus = "";
	char		kmgt[8];
	char		tmp[64];
	double		base;
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (value_double = atof(value)))
	{
		minus = "-";
		value_double = -value_double;
	}

	base = (0 == strcmp(units, "B") || 0 == strcmp(units, "Bps") ? 1024 : 1000);

	if (value_double < base)
	{
		strscpy(kmgt, "");
	}
	else if (value_double < base * base)
	{
		strscpy(kmgt, "K");
		value_double /= base;
	}
	else if (value_double < base * base * base)
	{
		strscpy(kmgt, "M");
		value_double /= base * base;
	}
	else if (value_double < base * base * base * base)
	{
		strscpy(kmgt, "G");
		value_double /= base * base * base;
	}
	else
	{
		strscpy(kmgt, "T");
		value_double /= base * base * base * base;
	}

	if (SUCCEED != zbx_double_compare(round(value_double), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s%s", minus, tmp, kmgt, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix                                                 *
 *                                                                            *
 * Purpose: Add suffix for value                                              *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *                                                                            *
 * Return value: SUCCEED - suffix added successfully, value contains new value*
 *               FAIL - adding failed, value contains old value               *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix(char *value, size_t max_len, const char *units, unsigned char value_type)
{
	struct tm	*local_time;
	time_t		time;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' units:'%s' value_type:%d",
			__func__, value, units, (int)value_type);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			if (0 == strcmp(units, "unixtime"))
			{
				time = (time_t)atoi(value);
				local_time = localtime(&time);
				strftime(value, max_len, "%Y.%m.%d %H:%M:%S", local_time);
				break;
			}
			ZBX_FALLTHROUGH;
		case ITEM_VALUE_TYPE_FLOAT:
			if (0 == strcmp(units, "s"))
				add_value_suffix_s(value, max_len);
			else if (0 == strcmp(units, "uptime"))
				add_value_suffix_uptime(value, max_len);
			else if ('!' == *units)
				add_value_units_no_kmgt(value, max_len, (const char *)(units + 1));
			else if (SUCCEED == is_blacklisted_unit(units))
				add_value_units_no_kmgt(value, max_len, units);
			else if ('\0' != *units)
				add_value_units_with_kmgt(value, max_len, units);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __func__, value);
}

/******************************************************************************
 *                                                                            *
 * Function: replace_value_by_map                                             *
 *                                                                            *
 * Purpose: replace value by mapping value                                    *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *             valuemapid - index of value map                                *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 ******************************************************************************/
static int	replace_value_by_map(char *value, size_t max_len, zbx_uint64_t valuemapid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*value_esc, *value_tmp;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' valuemapid:" ZBX_FS_UI64, __func__, value, valuemapid);

	if (0 == valuemapid)
		goto clean;

	value_esc = DBdyn_escape_string(value);
	result = DBselect(
			"select newvalue"
			" from mappings"
			" where valuemapid=" ZBX_FS_UI64
				" and value" ZBX_SQL_STRCMP,
			valuemapid, ZBX_SQL_STRVAL_EQ(value_esc));
	zbx_free(value_esc);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
	{
		del_zeros(row[0]);

		value_tmp = zbx_dsprintf(NULL, "%s (%s)", row[0], value);
		zbx_strlcpy_utf8(value, value_tmp, max_len);
		zbx_free(value_tmp);

		ret = SUCCEED;
	}
	DBfree_result(result);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __func__, value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_format_value                                                 *
 *                                                                            *
 * Purpose: replace value by value mapping or by units                        *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             valuemapid - [IN] identificator of value map                   *
 *             units      - [IN] units                                        *
 *             value_type - [IN] value type; ITEM_VALUE_TYPE_*                *
 *                                                                            *
 ******************************************************************************/
void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
			replace_value_by_map(value, max_len, valuemapid);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			del_zeros(value);
			ZBX_FALLTHROUGH;
		case ITEM_VALUE_TYPE_UINT64:
			if (SUCCEED != replace_value_by_map(value, max_len, valuemapid))
				add_value_suffix(value, max_len, units, value_type);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_macro_function                                          *
 *                                                                            *
 * Purpose: evaluate function used in simple macro                            *
 *                                                                            *
 * Parameters: result    - [OUT] evaluation result (if it's successful)       *
 *             host      - [IN] host the key belongs to                       *
 *             key       - [IN] item's key                                    *
 *                              (for example, 'system.cpu.load[,avg1]')       *
 *             function  - [IN] function name (for example, 'max')            *
 *             parameter - [IN] function parameter list                       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains its value   *
 *               FAIL - evaluation failed                                     *
 *                                                                            *
 * Comments: used for evaluation of notification macros                       *
 *                                                                            *
 ******************************************************************************/
int	evaluate_macro_function(char **result, const char *host, const char *key, const char *function,
		const char *parameter)
{
	zbx_host_key_t		host_key = {(char *)host, (char *)key};
	DC_ITEM			item;
	char			*value = NULL, *error = NULL, *resolved_params = NULL;
	int			ret, errcode;
	zbx_timespec_t		ts;
	DC_EVALUATE_ITEM	evaluate_item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s:%s.%s(%s)'", __func__, host, key, function, parameter);

	DCconfig_get_items_by_keys(&item, &host_key, &errcode, 1);

	zbx_timespec(&ts);

	/* User macros in trigger and calculated function parameters are resolved during configuration sync. */
	/* However simple macro user parameters are not expanded, do it now.                                 */


	evaluate_item.itemid = item.itemid;
	evaluate_item.value_type = item.value_type;
	evaluate_item.proxy_hostid = item.host.proxy_hostid;
	evaluate_item.host = item.host.host;
	evaluate_item.key_orig = item.key_orig;

	if (SUCCEED != errcode || SUCCEED != evaluate_function(&value, &evaluate_item, function,
			(resolved_params = zbx_dc_expand_user_macros_in_func_params(parameter, item.host.hostid)),
			&ts, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot evaluate function \"%s:%s.%s(%s)\": %s", host, key, function,
				parameter, (NULL == error ? "item does not exist" : error));
		ret = FAIL;
	}
	else
	{
		size_t	len;

		len = strlen(value) + 1 + MAX_BUFFER_LEN;
		value = (char *)zbx_realloc(value, len);

		if (SUCCEED == str_in_list("last,prev", function, ','))
		{
			/* last, prev functions can return quoted and escaped string values */
			/* which must be unquoted and unescaped before further processing   */
			if ('"' == *value)
			{
				char	*src, *dst;

				for (dst = value, src = dst + 1; '"' != *src; )
				{
					if ('\\' == *src)
						src++;
					if ('\0' == *src)
						break;
					*dst++ = *src++;
				}
				*dst = '\0';
			}
			zbx_format_value(value, len, item.valuemapid, ZBX_NULL2EMPTY_STR(item.units), item.value_type);
		}
		else if (SUCCEED == str_in_list("abschange,avg,change,delta,max,min,percentile,sum,forecast", function,
				','))
		{
			switch (item.value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					add_value_suffix(value, len, ZBX_NULL2EMPTY_STR(item.units), item.value_type);
					break;
				default:
					;
			}
		}
		else if (SUCCEED == str_in_list("timeleft", function, ','))
		{
			add_value_suffix(value, len, "s", ITEM_VALUE_TYPE_FLOAT);
		}

		*result = zbx_strdup(NULL, value);
		ret = SUCCEED;
	}

	DCconfig_clean_items(&item, &errcode, 1);
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s'", __func__, zbx_result_string(ret), value);
	zbx_free(value);
	zbx_free(resolved_params);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluatable_for_notsupported                                     *
 *                                                                            *
 * Purpose: check is function to be evaluated for NOTSUPPORTED items          *
 *                                                                            *
 * Parameters: fn - [IN] function name                                        *
 *                                                                            *
 * Return value: SUCCEED - do evaluate the function for NOTSUPPORTED items    *
 *               FAIL - don't evaluate the function for NOTSUPPORTED items    *
 *                                                                            *
 ******************************************************************************/
int	evaluatable_for_notsupported(const char *fn)
{
	/* functions date(), dayofmonth(), dayofweek(), now(), time() and nodata() are exceptions, */
	/* they should be evaluated for NOTSUPPORTED items, too */

	if ('n' != *fn && 'd' != *fn && 't' != *fn)
		return FAIL;

	if (('n' == *fn) && (0 == strcmp(fn, "nodata") || 0 == strcmp(fn, "now")))
		return SUCCEED;

	if (('d' == *fn) && (0 == strcmp(fn, "dayofweek") || 0 == strcmp(fn, "dayofmonth") || 0 == strcmp(fn, "date")))
		return SUCCEED;

	if (0 == strcmp(fn, "time"))
		return SUCCEED;

	return FAIL;
}

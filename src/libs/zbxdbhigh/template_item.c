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
#include "dbcache.h"
#include "zbxserver.h"
#include "template.h"

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		valuemapid;
	zbx_uint64_t		interfaceid;
	zbx_uint64_t		templateid;
	zbx_uint64_t		master_itemid;
	char			*name;
	char			*key;
	char			*delay;
	char			*history;
	char			*trends;
	char			*trapper_hosts;
	char			*units;
	char			*formula;
	char			*logtimefmt;
	char			*params;
	char			*ipmi_sensor;
	char			*snmp_oid;
	char			*username;
	char			*password;
	char			*publickey;
	char			*privatekey;
	char			*description;
	char			*lifetime;
	char			*jmx_endpoint;
	char			*timeout;
	char			*url;
	char			*query_fields;
	char			*posts;
	char			*status_codes;
	char			*http_proxy;
	char			*headers;
	char			*ssl_cert_file;
	char			*ssl_key_file;
	char			*ssl_key_password;
	unsigned char		verify_peer;
	unsigned char		verify_host;
	unsigned char		follow_redirects;
	unsigned char		post_type;
	unsigned char		retrieve_mode;
	unsigned char		request_method;
	unsigned char		output_format;
	unsigned char		type;
	unsigned char		value_type;
	unsigned char		status;
	unsigned char		authtype;
	unsigned char		flags;
	unsigned char		inventory_link;
	unsigned char		evaltype;
	unsigned char		allow_traps;
	unsigned char		discover;
	zbx_vector_ptr_t	dependent_items;
}
zbx_template_item_t;

/* lld rule condition */
typedef struct
{
	zbx_uint64_t	item_conditionid;
	char		*macro;
	char		*value;
	unsigned char	op;
}
zbx_lld_rule_condition_t;

/* lld rule */
typedef struct
{
	/* discovery rule source id */
	zbx_uint64_t		templateid;
	/* discovery rule source conditions */
	zbx_vector_ptr_t	conditions;

	/* discovery rule destination id */
	zbx_uint64_t		itemid;
	/* the starting id to be used for destination condition ids */
	zbx_uint64_t		conditionid;
	/* discovery rule destination condition ids */
	zbx_vector_uint64_t	conditionids;
}
zbx_lld_rule_map_t;

typedef struct
{
	zbx_uint64_t		overrideid;
	zbx_uint64_t		itemid;
	char			*name;
	char			*formula;
	zbx_vector_ptr_t	override_conditions;
	zbx_vector_ptr_t	override_operations;
	unsigned char		step;
	unsigned char		evaltype;
	unsigned char		stop;
}
lld_override_t;

typedef struct
{
	zbx_uint64_t		override_conditionid;
	char			*macro;
	char			*value;
	unsigned char		operator;
}
lld_override_codition_t;

typedef struct
{
	zbx_uint64_t		override_operationid;
	char			*value;
	char			*delay;
	char			*history;
	char			*trends;
	zbx_vector_ptr_pair_t	trigger_tags;
	zbx_vector_uint64_t	templateids;
	unsigned char		operationtype;
	unsigned char		operator;
	unsigned char		status;
	unsigned char		severity;
	unsigned char		inventory_mode;
	unsigned char		discover;
}
lld_override_operation_t;

/* auxiliary function for DBcopy_template_items() */
static void	DBget_interfaces_by_hostid(zbx_uint64_t hostid, zbx_uint64_t *interfaceids)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	type;

	result = DBselect(
			"select type,interfaceid"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type in (%d,%d,%d,%d)"
				" and main=1",
			hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UCHAR(type, row[0]);
		ZBX_STR2UINT64(interfaceids[type - 1], row[1]);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_template_items                                               *
 *                                                                            *
 * Purpose: read template items from database                                 *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *             items       - [OUT] the item data                              *
 *                                                                            *
 * Comments: The itemid and key are set depending on whether the item exists  *
 *           for the specified host.                                          *
 *           If item exists itemid will be set to its itemid and key will be  *
 *           set to NULL.                                                     *
 *           If item does not exist, itemid will be set to 0 and key will be  *
 *           set to item key.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	get_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, i;
	unsigned char		interface_type;
	zbx_template_item_t	*item;
	zbx_uint64_t		interfaceids[4];

	memset(&interfaceids, 0, sizeof(interfaceids));
	DBget_interfaces_by_hostid(hostid, interfaceids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid,ti.name,ti.key_,ti.type,ti.value_type,ti.delay,"
				"ti.history,ti.trends,ti.status,ti.trapper_hosts,ti.units,"
				"ti.formula,ti.logtimefmt,ti.valuemapid,ti.params,ti.ipmi_sensor,ti.snmp_oid,ti.authtype,"
				"ti.username,ti.password,ti.publickey,ti.privatekey,ti.flags,ti.description,"
				"ti.inventory_link,ti.lifetime,hi.itemid,ti.evaltype,"
				"ti.jmx_endpoint,ti.master_itemid,ti.timeout,ti.url,ti.query_fields,ti.posts,"
				"ti.status_codes,ti.follow_redirects,ti.post_type,ti.http_proxy,ti.headers,"
				"ti.retrieve_mode,ti.request_method,ti.output_format,ti.ssl_cert_file,ti.ssl_key_file,"
				"ti.ssl_key_password,ti.verify_peer,ti.verify_host,ti.allow_traps,ti.discover"
			" from items ti"
			" left join items hi on hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
			" where",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		item = (zbx_template_item_t *)zbx_malloc(NULL, sizeof(zbx_template_item_t));

		ZBX_STR2UINT64(item->templateid, row[0]);
		ZBX_STR2UCHAR(item->type, row[3]);
		ZBX_STR2UCHAR(item->value_type, row[4]);
		ZBX_STR2UCHAR(item->status, row[8]);
		ZBX_DBROW2UINT64(item->valuemapid, row[13]);
		ZBX_STR2UCHAR(item->authtype, row[17]);
		ZBX_STR2UCHAR(item->flags, row[22]);
		ZBX_STR2UCHAR(item->inventory_link, row[24]);
		ZBX_STR2UCHAR(item->evaltype, row[27]);

		switch (interface_type = get_interface_type_by_item_type(item->type))
		{
			case INTERFACE_TYPE_UNKNOWN:
				item->interfaceid = 0;
				break;
			case INTERFACE_TYPE_ANY:
				for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
				{
					if (0 != interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1])
						break;
				}
				item->interfaceid = interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1];
				break;
			default:
				item->interfaceid = interfaceids[interface_type - 1];
		}

		item->name = zbx_strdup(NULL, row[1]);
		item->delay = zbx_strdup(NULL, row[5]);
		item->history = zbx_strdup(NULL, row[6]);
		item->trends = zbx_strdup(NULL, row[7]);
		item->trapper_hosts = zbx_strdup(NULL, row[9]);
		item->units = zbx_strdup(NULL, row[10]);
		item->formula = zbx_strdup(NULL, row[11]);
		item->logtimefmt = zbx_strdup(NULL, row[12]);
		item->params = zbx_strdup(NULL, row[14]);
		item->ipmi_sensor = zbx_strdup(NULL, row[15]);
		item->snmp_oid = zbx_strdup(NULL, row[16]);
		item->username = zbx_strdup(NULL, row[18]);
		item->password = zbx_strdup(NULL, row[19]);
		item->publickey = zbx_strdup(NULL, row[20]);
		item->privatekey = zbx_strdup(NULL, row[21]);
		item->description = zbx_strdup(NULL, row[23]);
		item->lifetime = zbx_strdup(NULL, row[25]);
		item->jmx_endpoint = zbx_strdup(NULL, row[28]);
		ZBX_DBROW2UINT64(item->master_itemid, row[29]);

		if (SUCCEED != DBis_null(row[26]))
		{
			item->key = NULL;
			ZBX_STR2UINT64(item->itemid, row[26]);
		}
		else
		{
			item->key = zbx_strdup(NULL, row[2]);
			item->itemid = 0;
		}

		item->timeout = zbx_strdup(NULL, row[30]);
		item->url = zbx_strdup(NULL, row[31]);
		item->query_fields = zbx_strdup(NULL, row[32]);
		item->posts = zbx_strdup(NULL, row[33]);
		item->status_codes = zbx_strdup(NULL, row[34]);
		ZBX_STR2UCHAR(item->follow_redirects, row[35]);
		ZBX_STR2UCHAR(item->post_type, row[36]);
		item->http_proxy = zbx_strdup(NULL, row[37]);
		item->headers = zbx_strdup(NULL, row[38]);
		ZBX_STR2UCHAR(item->retrieve_mode, row[39]);
		ZBX_STR2UCHAR(item->request_method, row[40]);
		ZBX_STR2UCHAR(item->output_format, row[41]);
		item->ssl_cert_file = zbx_strdup(NULL, row[42]);
		item->ssl_key_file = zbx_strdup(NULL, row[43]);
		item->ssl_key_password = zbx_strdup(NULL, row[44]);
		ZBX_STR2UCHAR(item->verify_peer, row[45]);
		ZBX_STR2UCHAR(item->verify_host, row[46]);
		ZBX_STR2UCHAR(item->allow_traps, row[47]);
		ZBX_STR2UCHAR(item->discover, row[48]);
		zbx_vector_ptr_create(&item->dependent_items);
		zbx_vector_ptr_append(items, item);
	}
	DBfree_result(result);

	zbx_free(sql);

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: get_template_lld_rule_map                                        *
 *                                                                            *
 * Purpose: reads template lld rule conditions and host lld_rule identifiers  *
 *          from database                                                     *
 *                                                                            *
 * Parameters: items - [IN] the host items including lld rules                *
 *             rules - [OUT] the ldd rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	get_template_lld_rule_map(const zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_template_item_t		*item;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	int				i, index;
	zbx_vector_uint64_t		itemids;
	DB_RESULT			result;
	DB_ROW				row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid, item_conditionid;

	zbx_vector_uint64_create(&itemids);

	/* prepare discovery rules */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			continue;

		rule = (zbx_lld_rule_map_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_map_t));

		rule->itemid = item->itemid;
		rule->templateid = item->templateid;
		rule->conditionid = 0;
		zbx_vector_uint64_create(&rule->conditionids);
		zbx_vector_ptr_create(&rule->conditions);

		zbx_vector_ptr_append(rules, rule);

		if (0 != rule->itemid)
			zbx_vector_uint64_append(&itemids, rule->itemid);
		zbx_vector_uint64_append(&itemids, rule->templateid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_ptr_sort(rules, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select item_conditionid,itemid,operator,macro,value from item_condition where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			index = zbx_vector_ptr_bsearch(rules, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
			{
				/* read template lld conditions */

				rule = (zbx_lld_rule_map_t *)rules->values[index];

				condition = (zbx_lld_rule_condition_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_condition_t));

				ZBX_STR2UINT64(condition->item_conditionid, row[0]);
				ZBX_STR2UCHAR(condition->op, row[2]);
				condition->macro = zbx_strdup(NULL, row[3]);
				condition->value = zbx_strdup(NULL, row[4]);

				zbx_vector_ptr_append(&rule->conditions, condition);
			}
			else
			{
				/* read host lld conditions identifiers */

				for (i = 0; i < rules->values_num; i++)
				{
					rule = (zbx_lld_rule_map_t *)rules->values[i];

					if (itemid != rule->itemid)
						continue;

					ZBX_STR2UINT64(item_conditionid, row[0]);
					zbx_vector_uint64_append(&rule->conditionids, item_conditionid);

					break;
				}

				if (i == rules->values_num)
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_template_lld_rule_conditionids                         *
 *                                                                            *
 * Purpose: calculate identifiers for new item conditions                     *
 *                                                                            *
 * Parameters: rules - [IN] the ldd rule mapping                              *
 *                                                                            *
 * Return value: The number of new item conditions to be inserted.            *
 *                                                                            *
 ******************************************************************************/
static int	calculate_template_lld_rule_conditionids(zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, conditions_num = 0;
	zbx_uint64_t		conditionid;

	/* calculate the number of new conditions to be inserted */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num > rule->conditionids.values_num)
			conditions_num += rule->conditions.values_num - rule->conditionids.values_num;
	}

	/* reserve ids for the new conditions to be inserted and assign to lld rules */
	if (0 == conditions_num)
		goto out;

	conditionid = DBget_maxid_num("item_condition", conditions_num);

	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num <= rule->conditionids.values_num)
			continue;

		rule->conditionid = conditionid;
		conditionid += rule->conditions.values_num - rule->conditionids.values_num;
	}
out:
	return conditions_num;
}

static void	update_template_lld_formula(char **formula, zbx_uint64_t id_proto, zbx_uint64_t id)
{
	char	srcid[64], dstid[64], *ptr;
	size_t	pos = 0, len;

	zbx_snprintf(srcid, sizeof(srcid), "{" ZBX_FS_UI64 "}", id_proto);
	zbx_snprintf(dstid, sizeof(dstid), "{" ZBX_FS_UI64 "}", id);

	len = strlen(srcid);

	while (NULL != (ptr = strstr(*formula + pos, srcid)))
	{
		pos = ptr - *formula + len - 1;
		zbx_replace_string(formula, ptr - *formula, &pos, dstid);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: update_template_lld_rule_formulas                                *
 *                                                                            *
 * Purpose: translate template item condition identifiers in expression type  *
 *          discovery rule formulas to refer the host item condition          *
 *          identifiers instead.                                              *
 *                                                                            *
 * Parameters:  items  - [IN] the template items                              *
 *              rules  - [IN] the ldd rule mapping                            *
 *                                                                            *
 ******************************************************************************/
static void	update_template_lld_rule_formulas(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, j, index;
	char			*formula;
	zbx_uint64_t		conditionid;

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags) || CONDITION_EVAL_TYPE_EXPRESSION != item->evaltype)
			continue;

		index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule = (zbx_lld_rule_map_t *)rules->values[index];

		formula = zbx_strdup(NULL, item->formula);

		conditionid = rule->conditionid;

		for (j = 0; j < rule->conditions.values_num; j++)
		{
			zbx_uint64_t			id;
			zbx_lld_rule_condition_t	*condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			if (j < rule->conditionids.values_num)
				id = rule->conditionids.values[j];
			else
				id = conditionid++;

			update_template_lld_formula(&formula, condition->item_conditionid, id);
		}

		zbx_free(item->formula);
		item->formula = formula;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_item                                               *
 *                                                                            *
 * Purpose: save (insert or update) template item                             *
 *                                                                            *
 * Parameters: hostid            - [IN] parent host id                        *
 *             itemid            - [IN/OUT] item id used for insert           *
 *                                          operations                        *
 *             item              - [IN] item to be saved                      *
 *             db_insert_items   - [IN] prepared item bulk insert             *
 *             db_insert_irtdata - [IN] prepared item discovery bulk insert   *
 *             sql               - [IN/OUT] sql buffer pointer used for       *
 *                                          update operations                 *
 *             sql_alloc         - [IN/OUT] sql buffer already allocated      *
 *                                          memory                            *
 *             sql_offset        - [IN/OUT] offset for writing within sql     *
 *                                          buffer                            *
 *                                                                            *
 ******************************************************************************/
static void	save_template_item(zbx_uint64_t hostid, zbx_uint64_t *itemid, zbx_template_item_t *item,
		zbx_db_insert_t *db_insert_items, zbx_db_insert_t *db_insert_irtdata, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	int			i;
	zbx_template_item_t	*dependent;

	if (NULL == item->key) /* existing item */
	{
		char	*name_esc, *delay_esc, *history_esc, *trends_esc, *trapper_hosts_esc, *units_esc, *formula_esc,
			*logtimefmt_esc, *params_esc, *ipmi_sensor_esc, *snmp_oid_esc, *username_esc,
			*password_esc, *publickey_esc, *privatekey_esc, *description_esc, *lifetime_esc,
			*jmx_endpoint_esc, *timeout_esc, *url_esc,
			*query_fields_esc, *posts_esc, *status_codes_esc, *http_proxy_esc, *headers_esc,
			*ssl_cert_file_esc, *ssl_key_file_esc, *ssl_key_password_esc;

		name_esc = DBdyn_escape_string(item->name);
		delay_esc = DBdyn_escape_string(item->delay);
		history_esc = DBdyn_escape_string(item->history);
		trends_esc = DBdyn_escape_string(item->trends);
		trapper_hosts_esc = DBdyn_escape_string(item->trapper_hosts);
		units_esc = DBdyn_escape_string(item->units);
		formula_esc = DBdyn_escape_string(item->formula);
		logtimefmt_esc = DBdyn_escape_string(item->logtimefmt);
		params_esc = DBdyn_escape_string(item->params);
		ipmi_sensor_esc = DBdyn_escape_string(item->ipmi_sensor);
		snmp_oid_esc = DBdyn_escape_string(item->snmp_oid);
		username_esc = DBdyn_escape_string(item->username);
		password_esc = DBdyn_escape_string(item->password);
		publickey_esc = DBdyn_escape_string(item->publickey);
		privatekey_esc = DBdyn_escape_string(item->privatekey);
		description_esc = DBdyn_escape_string(item->description);
		lifetime_esc = DBdyn_escape_string(item->lifetime);
		jmx_endpoint_esc = DBdyn_escape_string(item->jmx_endpoint);
		timeout_esc = DBdyn_escape_string(item->timeout);
		url_esc = DBdyn_escape_string(item->url);
		query_fields_esc = DBdyn_escape_string(item->query_fields);
		posts_esc = DBdyn_escape_string(item->posts);
		status_codes_esc = DBdyn_escape_string(item->status_codes);
		http_proxy_esc = DBdyn_escape_string(item->http_proxy);
		headers_esc = DBdyn_escape_string(item->headers);
		ssl_cert_file_esc = DBdyn_escape_string(item->ssl_cert_file);
		ssl_key_file_esc = DBdyn_escape_string(item->ssl_key_file);
		ssl_key_password_esc = DBdyn_escape_string(item->ssl_key_password);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update items"
				" set name='%s',"
					"type=%d,"
					"value_type=%d,"
					"delay='%s',"
					"history='%s',"
					"trends='%s',"
					"status=%d,"
					"trapper_hosts='%s',"
					"units='%s',"
					"formula='%s',"
					"logtimefmt='%s',"
					"valuemapid=%s,"
					"params='%s',"
					"ipmi_sensor='%s',"
					"snmp_oid='%s',"
					"authtype=%d,"
					"username='%s',"
					"password='%s',"
					"publickey='%s',"
					"privatekey='%s',"
					"templateid=" ZBX_FS_UI64 ","
					"flags=%d,"
					"description='%s',"
					"inventory_link=%d,"
					"interfaceid=%s,"
					"lifetime='%s',"
					"evaltype=%d,"
					"jmx_endpoint='%s',"
					"master_itemid=%s,"
					"timeout='%s',"
					"url='%s',"
					"query_fields='%s',"
					"posts='%s',"
					"status_codes='%s',"
					"follow_redirects=%d,"
					"post_type=%d,"
					"http_proxy='%s',"
					"headers='%s',"
					"retrieve_mode=%d,"
					"request_method=%d,"
					"output_format=%d,"
					"ssl_cert_file='%s',"
					"ssl_key_file='%s',"
					"ssl_key_password='%s',"
					"verify_peer=%d,"
					"verify_host=%d,"
					"allow_traps=%d,"
					"discover=%d"
				" where itemid=" ZBX_FS_UI64 ";\n",
				name_esc, (int)item->type, (int)item->value_type, delay_esc,
				history_esc, trends_esc, (int)item->status, trapper_hosts_esc, units_esc,
				formula_esc, logtimefmt_esc, DBsql_id_ins(item->valuemapid), params_esc,
				ipmi_sensor_esc, snmp_oid_esc,(int)item->authtype, username_esc, password_esc,
				publickey_esc, privatekey_esc, item->templateid, (int)item->flags, description_esc,
				(int)item->inventory_link, DBsql_id_ins(item->interfaceid), lifetime_esc,
				(int)item->evaltype, jmx_endpoint_esc, DBsql_id_ins(item->master_itemid),
				timeout_esc, url_esc, query_fields_esc, posts_esc, status_codes_esc,
				item->follow_redirects, item->post_type, http_proxy_esc, headers_esc,
				item->retrieve_mode, item->request_method, item->output_format, ssl_cert_file_esc,
				ssl_key_file_esc, ssl_key_password_esc, item->verify_peer, item->verify_host,
				item->allow_traps, item->discover, item->itemid);

		zbx_free(jmx_endpoint_esc);
		zbx_free(lifetime_esc);
		zbx_free(description_esc);
		zbx_free(privatekey_esc);
		zbx_free(publickey_esc);
		zbx_free(password_esc);
		zbx_free(username_esc);
		zbx_free(snmp_oid_esc);
		zbx_free(ipmi_sensor_esc);
		zbx_free(params_esc);
		zbx_free(logtimefmt_esc);
		zbx_free(formula_esc);
		zbx_free(units_esc);
		zbx_free(trapper_hosts_esc);
		zbx_free(trends_esc);
		zbx_free(history_esc);
		zbx_free(delay_esc);
		zbx_free(name_esc);
		zbx_free(timeout_esc);
		zbx_free(url_esc);
		zbx_free(query_fields_esc);
		zbx_free(posts_esc);
		zbx_free(status_codes_esc);
		zbx_free(http_proxy_esc);
		zbx_free(headers_esc);
		zbx_free(ssl_cert_file_esc);
		zbx_free(ssl_key_file_esc);
		zbx_free(ssl_key_password_esc);
	}
	else
	{
		zbx_db_insert_add_values(db_insert_items, *itemid, item->name, item->key, hostid, (int)item->type,
				(int)item->value_type, item->delay, item->history, item->trends,
				(int)item->status, item->trapper_hosts, item->units, item->formula, item->logtimefmt,
				item->valuemapid, item->params, item->ipmi_sensor, item->snmp_oid, (int)item->authtype,
				item->username, item->password, item->publickey, item->privatekey, item->templateid,
				(int)item->flags, item->description, (int)item->inventory_link, item->interfaceid,
				item->lifetime, (int)item->evaltype,
				item->jmx_endpoint, item->master_itemid, item->timeout, item->url, item->query_fields,
				item->posts, item->status_codes, item->follow_redirects, item->post_type,
				item->http_proxy, item->headers, item->retrieve_mode, item->request_method,
				item->output_format, item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password,
				item->verify_peer, item->verify_host, item->allow_traps, item->discover);

		zbx_db_insert_add_values(db_insert_irtdata, *itemid);

		item->itemid = (*itemid)++;
	}

	for (i = 0; i < item->dependent_items.values_num; i++)
	{
		dependent = (zbx_template_item_t *)item->dependent_items.values[i];
		dependent->master_itemid = item->itemid;
		save_template_item(hostid, itemid, dependent, db_insert_items, db_insert_irtdata, sql, sql_alloc,
				sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_items                                              *
 *                                                                            *
 * Purpose: saves template items to the target host in database               *
 *                                                                            *
 * Parameters:  hostid - [IN] the target host                                 *
 *              items  - [IN] the template items                              *
 *                                                                            *
 ******************************************************************************/
static void	save_template_items(zbx_uint64_t hostid, zbx_vector_ptr_t *items)
{
	char			*sql = NULL;
	size_t			sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
	int			new_items = 0, upd_items = 0, i;
	zbx_uint64_t		itemid = 0;
	zbx_db_insert_t		db_insert_items, db_insert_irtdata;
	zbx_template_item_t	*item;

	if (0 == items->values_num)
		return;

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key)
			upd_items++;
		else
			new_items++;
	}

	if (0 != new_items)
	{
		itemid = DBget_maxid_num("items", new_items);

		zbx_db_insert_prepare(&db_insert_items, "items", "itemid", "name", "key_", "hostid", "type", "value_type",
				"delay", "history", "trends", "status", "trapper_hosts", "units",
				"formula", "logtimefmt", "valuemapid", "params", "ipmi_sensor",
				"snmp_oid", "authtype", "username", "password", "publickey", "privatekey",
				"templateid", "flags", "description", "inventory_link", "interfaceid", "lifetime",
				"evaltype","jmx_endpoint", "master_itemid",
				"timeout", "url", "query_fields", "posts", "status_codes", "follow_redirects",
				"post_type", "http_proxy", "headers", "retrieve_mode", "request_method",
				"output_format", "ssl_cert_file", "ssl_key_file", "ssl_key_password", "verify_peer",
				"verify_host", "allow_traps", "discover", NULL);

		zbx_db_insert_prepare(&db_insert_irtdata, "item_rtdata", "itemid", NULL);
	}

	if (0 != upd_items)
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		/* dependent items are saved within recursive save_template_item calls while saving master */
		if (0 == item->master_itemid)
		{
			save_template_item(hostid, &itemid, item, &db_insert_items, &db_insert_irtdata,
					&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != new_items)
	{
		zbx_db_insert_execute(&db_insert_items);
		zbx_db_insert_clean(&db_insert_items);

		zbx_db_insert_execute(&db_insert_irtdata);
		zbx_db_insert_clean(&db_insert_irtdata);
	}

	if (0 != upd_items)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);

		zbx_free(sql);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_lld_rules                                          *
 *                                                                            *
 * Purpose: saves template lld rule item conditions to the target host in     *
 *          database                                                          *
 *                                                                            *
 * Parameters:  items          - [IN] the template items                      *
 *              rules          - [IN] the ldd rule mapping                    *
 *              new_conditions - [IN] the number of new item conditions to    *
 *                                    be inserted                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_lld_rules(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules, int new_conditions)
{
	char				*macro_esc, *value_esc;
	int				i, j, index;
	zbx_db_insert_t			db_insert;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		item_conditionids;

	if (0 == rules->values_num)
		return;

	zbx_vector_uint64_create(&item_conditionids);

	if (0 != new_conditions)
	{
		zbx_db_insert_prepare(&db_insert, "item_condition", "item_conditionid", "itemid", "operator", "macro",
				"value", NULL);

		/* insert lld rule conditions for new items */
		for (i = 0; i < items->values_num; i++)
		{
			zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

			if (NULL == item->key)
				continue;

			if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
				continue;

			index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL == index)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			rule = (zbx_lld_rule_map_t *)rules->values[index];

			for (j = 0; j < rule->conditions.values_num; j++)
			{
				condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

				zbx_db_insert_add_values(&db_insert, rule->conditionid++, item->itemid,
						(int)condition->op, condition->macro, condition->value);
			}
		}
	}

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* update lld rule conditions for existing items */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		/* skip lld rules of new items */
		if (0 == rule->itemid)
			continue;

		index = MIN(rule->conditions.values_num, rule->conditionids.values_num);

		/* update intersecting rule conditions */
		for (j = 0; j < index; j++)
		{
			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			macro_esc = DBdyn_escape_string(condition->macro);
			value_esc = DBdyn_escape_string(condition->value);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_condition"
					" set operator=%d,macro='%s',value='%s'"
					" where item_conditionid=" ZBX_FS_UI64 ";\n",
					(int)condition->op, macro_esc, value_esc, rule->conditionids.values[j]);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			zbx_free(value_esc);
			zbx_free(macro_esc);
		}

		/* delete removed rule conditions */
		for (j = index; j < rule->conditionids.values_num; j++)
			zbx_vector_uint64_append(&item_conditionids, rule->conditionids.values[j]);

		/* insert new rule conditions */
		for (j = index; j < rule->conditions.values_num; j++)
		{
			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			zbx_db_insert_add_values(&db_insert, rule->conditionid++, rule->itemid,
					(int)condition->op, condition->macro, condition->value);
		}
	}

	/* delete removed item conditions */
	if (0 != item_conditionids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_condition where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "item_conditionid", item_conditionids.values,
				item_conditionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	if (0 != new_conditions)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&item_conditionids);
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_item_applications                                  *
 *                                                                            *
 * Purpose: saves new item applications links in database                     *
 *                                                                            *
 * Parameters:  items   - [IN] the template items                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_item_applications(zbx_vector_ptr_t *items)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	applicationid;
	}
	zbx_itemapp_t;

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	itemapps;
	zbx_itemapp_t		*itemapp;
	int			i;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&itemapps);
	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		zbx_vector_uint64_append(&itemids, item->itemid);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hi.itemid,ha.applicationid"
			" from items_applications tia"
				" join items hi on hi.templateid=tia.itemid"
					" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.itemid", itemids.values, itemids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" join application_template hat on hat.templateid=tia.applicationid"
				" join applications ha on ha.applicationid=hat.applicationid"
					" and ha.hostid=hi.hostid"
					" left join items_applications hia on hia.applicationid=ha.applicationid"
						" and hia.itemid=hi.itemid"
			" where hia.itemappid is null");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		itemapp = (zbx_itemapp_t *)zbx_malloc(NULL, sizeof(zbx_itemapp_t));

		ZBX_STR2UINT64(itemapp->itemid, row[0]);
		ZBX_STR2UINT64(itemapp->applicationid, row[1]);

		zbx_vector_ptr_append(&itemapps, itemapp);
	}
	DBfree_result(result);

	if (0 == itemapps.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "items_applications", "itemappid", "itemid", "applicationid", NULL);

	for (i = 0; i < itemapps.values_num; i++)
	{
		itemapp = (zbx_itemapp_t *)itemapps.values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemapp->itemid, itemapp->applicationid);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemappid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	zbx_vector_ptr_clear_ext(&itemapps, zbx_ptr_free);
	zbx_vector_ptr_destroy(&itemapps);
}

/******************************************************************************
 *                                                                            *
 * Function: save_template_discovery_prototypes                               *
 *                                                                            *
 * Purpose: saves host item prototypes in database                            *
 *                                                                            *
 * Parameters:  hostid  - [IN] the target host                                *
 *              items   - [IN] the template items                             *
 *                                                                            *
 ******************************************************************************/
static void	save_template_discovery_prototypes(zbx_uint64_t hostid, zbx_vector_ptr_t *items)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	parent_itemid;
	}
	zbx_proto_t;

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	prototypes;
	zbx_proto_t		*proto;
	int			i;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&prototypes);
	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		/* process only new prototype items */
		if (NULL == item->key || 0 == (ZBX_FLAG_DISCOVERY_PROTOTYPE & item->flags))
			continue;

		zbx_vector_uint64_append(&itemids, item->itemid);
	}

	if (0 == itemids.values_num)
		goto out;

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,r.itemid"
			" from items i,item_discovery id,items r"
			" where i.templateid=id.itemid"
				" and id.parent_itemid=r.templateid"
				" and r.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids.values, itemids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		proto = (zbx_proto_t *)zbx_malloc(NULL, sizeof(zbx_proto_t));

		ZBX_STR2UINT64(proto->itemid, row[0]);
		ZBX_STR2UINT64(proto->parent_itemid, row[1]);

		zbx_vector_ptr_append(&prototypes, proto);
	}
	DBfree_result(result);

	if (0 == prototypes.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "item_discovery", "itemdiscoveryid", "itemid",
					"parent_itemid", NULL);

	for (i = 0; i < prototypes.values_num; i++)
	{
		proto = (zbx_proto_t *)prototypes.values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), proto->itemid, proto->parent_itemid);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemdiscoveryid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	zbx_vector_ptr_clear_ext(&prototypes, zbx_ptr_free);
	zbx_vector_ptr_destroy(&prototypes);
}

/******************************************************************************
 *                                                                            *
 * Function: free_template_item                                               *
 *                                                                            *
 * Purpose: frees template item                                               *
 *                                                                            *
 * Parameters:  item  - [IN] the template item                                *
 *                                                                            *
 ******************************************************************************/
static void	free_template_item(zbx_template_item_t *item)
{
	zbx_free(item->timeout);
	zbx_free(item->url);
	zbx_free(item->query_fields);
	zbx_free(item->posts);
	zbx_free(item->status_codes);
	zbx_free(item->http_proxy);
	zbx_free(item->headers);
	zbx_free(item->ssl_cert_file);
	zbx_free(item->ssl_key_file);
	zbx_free(item->ssl_key_password);
	zbx_free(item->jmx_endpoint);
	zbx_free(item->lifetime);
	zbx_free(item->description);
	zbx_free(item->privatekey);
	zbx_free(item->publickey);
	zbx_free(item->password);
	zbx_free(item->username);
	zbx_free(item->snmp_oid);
	zbx_free(item->ipmi_sensor);
	zbx_free(item->params);
	zbx_free(item->logtimefmt);
	zbx_free(item->formula);
	zbx_free(item->units);
	zbx_free(item->trapper_hosts);
	zbx_free(item->trends);
	zbx_free(item->history);
	zbx_free(item->delay);
	zbx_free(item->name);
	zbx_free(item->key);

	zbx_vector_ptr_destroy(&item->dependent_items);

	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Function: free_lld_rule_condition                                          *
 *                                                                            *
 * Purpose: frees lld rule condition                                          *
 *                                                                            *
 * Parameters:  item  - [IN] the lld rule condition                           *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_condition(zbx_lld_rule_condition_t *condition)
{
	zbx_free(condition->macro);
	zbx_free(condition->value);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Function: free_lld_rule_map                                                *
 *                                                                            *
 * Purpose: frees lld rule mapping                                            *
 *                                                                            *
 * Parameters:  item  - [IN] the lld rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_map(zbx_lld_rule_map_t *rule)
{
	zbx_vector_ptr_clear_ext(&rule->conditions, (zbx_clean_func_t)free_lld_rule_condition);
	zbx_vector_ptr_destroy(&rule->conditions);

	zbx_vector_uint64_destroy(&rule->conditionids);

	zbx_free(rule);
}

static zbx_hash_t	template_item_hash_func(const void *d)
{
	const zbx_template_item_t	*item = *(const zbx_template_item_t **)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&item->templateid);
}

static int	template_item_compare_func(const void *d1, const void *d2)
{
	const zbx_template_item_t	*item1 = *(const zbx_template_item_t **)d1;
	const zbx_template_item_t	*item2 = *(const zbx_template_item_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item1->templateid, item2->templateid);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: copy_template_items_preproc                                      *
 *                                                                            *
 * Purpose: copy template item preprocessing options                          *
 *                                                                            *
 * Parameters: templateids - [IN] array of template IDs                       *
 *             items       - [IN] array of new/updated items                  *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_items_preproc(const zbx_vector_uint64_t *templateids, const zbx_vector_ptr_t *items)
{
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			items_t;
	int				i;
	const zbx_template_item_t	*item, **pitem;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_ROW				row;
	DB_RESULT			result;
	zbx_db_insert_t			db_insert;

	if (0 == items->values_num)
		return;

	zbx_vector_uint64_create(&itemids);
	zbx_hashset_create(&items_t, items->values_num, template_item_hash_func, template_item_compare_func);

	/* remove old item preprocessing options */

	for (i = 0; i < items->values_num; i++)
	{
		item = (const zbx_template_item_t *)items->values[i];

		if (NULL == item->key)
			zbx_vector_uint64_append(&itemids, item->itemid);

		zbx_hashset_insert(&items_t, &item, sizeof(zbx_template_item_t *));
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_preproc where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
		DBexecute("%s", sql);
		sql_offset = 0;
	}

	zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params",
			"error_handler", "error_handler_params", NULL);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ip.itemid,ip.step,ip.type,ip.params,ip.error_handler,ip.error_handler_params"
				" from item_preproc ip,items ti"
				" where ip.itemid=ti.itemid"
				" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local;

		ZBX_STR2UINT64(item_local.templateid, row[0]);
		if (NULL == (pitem = (const zbx_template_item_t **)zbx_hashset_search(&items_t, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), (*pitem)->itemid, atoi(row[1]), atoi(row[2]),
				row[3], atoi(row[4]), row[5]);

	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "item_preprocid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
	zbx_hashset_destroy(&items_t);
	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Function: copy_template_lld_macro_paths                                    *
 *                                                                            *
 * Purpose: copy template discovery item lld macro paths                      *
 *                                                                            *
 * Parameters: templateids - [IN] array of template IDs                       *
 *             items       - [IN] array of new/updated items                  *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_lld_macro_paths(const zbx_vector_uint64_t *templateids,
		const zbx_vector_uint64_t *lld_itemids, zbx_hashset_t *lld_items)
{
	const zbx_template_item_t	**pitem;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_ROW				row;
	DB_RESULT			result;
	zbx_db_insert_t			db_insert;

	/* remove old lld rules macros */
	if (0 != lld_itemids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from lld_macro_path where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", lld_itemids->values,
				lld_itemids->values_num);
		DBexecute("%s", sql);
		sql_offset = 0;
	}

	zbx_db_insert_prepare(&db_insert, "lld_macro_path", "lld_macro_pathid", "itemid", "lld_macro", "path", NULL);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select l.itemid,l.lld_macro,l.path"
				" from lld_macro_path l,items i"
				" where l.itemid=i.itemid"
				" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);
	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local;

		ZBX_STR2UINT64(item_local.templateid, row[0]);
		if (NULL == (pitem = (const zbx_template_item_t **)zbx_hashset_search(lld_items, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), (*pitem)->itemid, row[1], row[2]);

	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "lld_macro_pathid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
}

static void	lld_override_condition_free(lld_override_codition_t *override_condition)
{
	zbx_free(override_condition->macro);
	zbx_free(override_condition->value);
	zbx_free(override_condition);
}

static void	lld_override_operation_free(lld_override_operation_t *override_operation)
{
	int	i;

	for (i = 0; i < override_operation->trigger_tags.values_num; i++)
	{
		zbx_free(override_operation->trigger_tags.values[i].first);
		zbx_free(override_operation->trigger_tags.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&override_operation->trigger_tags);

	zbx_vector_uint64_destroy(&override_operation->templateids);

	zbx_free(override_operation->value);
	zbx_free(override_operation->delay);
	zbx_free(override_operation->history);
	zbx_free(override_operation->trends);
	zbx_free(override_operation);
}

static void	lld_override_free(lld_override_t *override)
{
	zbx_vector_ptr_clear_ext(&override->override_conditions, (zbx_clean_func_t)lld_override_condition_free);
	zbx_vector_ptr_destroy(&override->override_conditions);
	zbx_vector_ptr_clear_ext(&override->override_operations, (zbx_clean_func_t)lld_override_operation_free);
	zbx_vector_ptr_destroy(&override->override_operations);
	zbx_free(override->name);
	zbx_free(override->formula);
	zbx_free(override);
}

static void	lld_override_conditions_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc)
{
	size_t			sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		overrideid;
	int			i;
	lld_override_t		*override;
	lld_override_codition_t	*override_condition;

	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset,
		"select lld_overrideid,lld_override_conditionid,operator,macro,value"
			" from lld_override_condition"
			" where");
	DBadd_condition_alloc(sql, sql_alloc, &sql_offset, "lld_overrideid", overrideids->values,
			overrideids->values_num);

	result = DBselect("%s", *sql);
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(overrideid, row[0]);

		if (FAIL == (i = zbx_vector_ptr_bsearch(overrides, &overrideid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		override = (lld_override_t *)overrides->values[i];

		override_condition = (lld_override_codition_t *)zbx_malloc(NULL, sizeof(lld_override_codition_t));
		ZBX_STR2UINT64(override_condition->override_conditionid, row[1]);
		ZBX_STR2UCHAR(override_condition->operator, row[2]);
		override_condition->macro = zbx_strdup(NULL, row[3]);
		override_condition->value = zbx_strdup(NULL, row[4]);

		zbx_vector_ptr_append(&override->override_conditions, override_condition);
	}
	DBfree_result(result);
}

static void	lld_override_operations_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc)
{
	size_t				sql_offset = 0;
	DB_RESULT			result;
	DB_ROW				row;
	lld_override_t			*override = NULL;
	lld_override_operation_t	*override_operation = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset,
			"select o.lld_overrideid,o.lld_override_operationid,o.operationobject,o.operator,o.value,"
				"s.status,"
				"d.discover,"
				"p.delay,"
				"h.history,"
				"t.trends,"
				"os.severity,"
				"ot.tag,ot.value,"
				"ote.templateid,"
				"i.inventory_mode"
			" from lld_override_operation o"
			" left join lld_override_opstatus s"
				" on o.lld_override_operationid=s.lld_override_operationid"
			" left join lld_override_opdiscover d"
				" on o.lld_override_operationid=d.lld_override_operationid"
			" left join lld_override_opperiod p"
				" on o.lld_override_operationid=p.lld_override_operationid"
			" left join lld_override_ophistory h"
				" on o.lld_override_operationid=h.lld_override_operationid"
			" left join lld_override_optrends t"
				" on o.lld_override_operationid=t.lld_override_operationid"
			" left join lld_override_opseverity os"
				" on o.lld_override_operationid=os.lld_override_operationid"
			" left join lld_override_optag ot"
				" on o.lld_override_operationid=ot.lld_override_operationid"
			" left join lld_override_optemplate ote"
				" on o.lld_override_operationid=ote.lld_override_operationid"
			" left join lld_override_opinventory i"
				" on o.lld_override_operationid=i.lld_override_operationid"
			" where");
	DBadd_condition_alloc(sql, sql_alloc, &sql_offset, "o.lld_overrideid", overrideids->values,
			overrideids->values_num);
	zbx_strcpy_alloc(sql, sql_alloc, &sql_offset, " order by o.lld_override_operationid");

	result = DBselect("%s", *sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	overrideid, override_operationid;

		ZBX_STR2UINT64(overrideid, row[0]);
		if (NULL == override || override->overrideid != overrideid)
		{
			int	index;

			if (FAIL == (index = zbx_vector_ptr_bsearch(overrides, &overrideid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			override = (lld_override_t *)overrides->values[index];
		}

		ZBX_STR2UINT64(override_operationid, row[1]);
		if (NULL == override_operation || override_operation->override_operationid != override_operationid)
		{
			override_operation = (lld_override_operation_t *)zbx_malloc(NULL,
					sizeof(lld_override_operation_t));

			zbx_vector_ptr_pair_create(&override_operation->trigger_tags);
			zbx_vector_uint64_create(&override_operation->templateids);

			override_operation->override_operationid = override_operationid;
			override_operation->operationtype = (unsigned char)atoi(row[2]);
			override_operation->operator = (unsigned char)atoi(row[3]);
			override_operation->value = zbx_strdup(NULL, row[4]);

			override_operation->status = FAIL == DBis_null(row[5]) ? (unsigned char)atoi(row[5]) :
					ZBX_PROTOTYPE_STATUS_COUNT;

			override_operation->discover = FAIL == DBis_null(row[6]) ? (unsigned char)atoi(row[6]) :
					ZBX_PROTOTYPE_DISCOVER_COUNT;

			zbx_vector_ptr_append(&override->override_operations, override_operation);
		}

		override_operation->delay = FAIL == DBis_null(row[7]) ? zbx_strdup(NULL, row[7]) :
				NULL;
		override_operation->history = FAIL == DBis_null(row[8]) ? zbx_strdup(NULL, row[8]) :
				NULL;
		override_operation->trends = FAIL == DBis_null(row[9]) ? zbx_strdup(NULL, row[9]) :
				NULL;
		override_operation->severity = FAIL == DBis_null(row[10]) ? (unsigned char)atoi(row[10]) :
				TRIGGER_SEVERITY_COUNT;

		if (FAIL == DBis_null(row[11]))
		{
			zbx_ptr_pair_t	pair;

			pair.first = zbx_strdup(NULL, row[11]);
			pair.second = zbx_strdup(NULL, row[12]);

			zbx_vector_ptr_pair_append(&override_operation->trigger_tags, pair);
		}

		if (FAIL == DBis_null(row[13]))
		{
			zbx_uint64_t	templateid;

			ZBX_STR2UINT64(templateid, row[13]);
			zbx_vector_uint64_append(&override_operation->templateids, templateid);
		}

		override_operation->inventory_mode = FAIL == DBis_null(row[14]) ?
				(unsigned char)atoi(row[14]) : HOST_INVENTORY_COUNT;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	save_template_lld_overrides(zbx_vector_ptr_t *overrides, zbx_hashset_t *lld_items)
{
	zbx_uint64_t			overrideid, override_operationid, override_conditionid;
	zbx_db_insert_t			db_insert, db_insert_oconditions, db_insert_ooperations, db_insert_opstatus,
					db_insert_opdiscover, db_insert_opperiod, db_insert_ophistory,
					db_insert_optrends, db_insert_opseverity, db_insert_optag, db_insert_optemplate,
					db_insert_opinventory;
	int				i, j, k, conditions_num, operations_num;
	lld_override_t			*override;
	lld_override_codition_t		*override_condition;
	lld_override_operation_t	*override_operation;
	const zbx_template_item_t	**pitem;

	if (0 != overrides->values_num)
		overrideid = DBget_maxid_num("lld_override", overrides->values_num);

	zbx_db_insert_prepare(&db_insert, "lld_override", "lld_overrideid", "itemid", "name", "step", "evaltype",
			"formula", "stop", NULL);

	zbx_db_insert_prepare(&db_insert_oconditions, "lld_override_condition", "lld_override_conditionid",
			"lld_overrideid", "operator", "macro", "value", NULL);

	for (i = 0, operations_num = 0, conditions_num = 0; i < overrides->values_num; i++)
	{
		override = (lld_override_t *)overrides->values[i];
		operations_num += override->override_operations.values_num;
		conditions_num += override->override_conditions.values_num;
	}

	if (0 != operations_num)
		override_operationid = DBget_maxid_num("lld_override_operation", operations_num);

	if (0 != conditions_num)
		override_conditionid = DBget_maxid_num("lld_override_condition", conditions_num);

	zbx_db_insert_prepare(&db_insert_ooperations, "lld_override_operation", "lld_override_operationid",
				"lld_overrideid", "operationobject", "operator", "value", NULL);

	zbx_db_insert_prepare(&db_insert_opstatus, "lld_override_opstatus", "lld_override_operationid", "status", NULL);

	zbx_db_insert_prepare(&db_insert_opdiscover, "lld_override_opdiscover", "lld_override_operationid", "discover",
			NULL);

	zbx_db_insert_prepare(&db_insert_opperiod, "lld_override_opperiod", "lld_override_operationid", "delay",
			NULL);
	zbx_db_insert_prepare(&db_insert_ophistory, "lld_override_ophistory", "lld_override_operationid", "history",
			NULL);
	zbx_db_insert_prepare(&db_insert_optrends, "lld_override_optrends", "lld_override_operationid", "trends",
			NULL);

	zbx_db_insert_prepare(&db_insert_opseverity, "lld_override_opseverity", "lld_override_operationid", "severity",
			NULL);
	zbx_db_insert_prepare(&db_insert_optag, "lld_override_optag", "lld_override_optagid",
			"lld_override_operationid", "tag", "value", NULL);

	zbx_db_insert_prepare(&db_insert_optemplate, "lld_override_optemplate", "lld_override_optemplateid",
				"lld_override_operationid", "templateid", NULL);
	zbx_db_insert_prepare(&db_insert_opinventory, "lld_override_opinventory", "lld_override_operationid",
			"inventory_mode", NULL);

	for (i = 0; i < overrides->values_num; i++)
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local;

		override = (lld_override_t *)overrides->values[i];

		item_local.templateid = override->itemid;
		if (NULL == (pitem = (const zbx_template_item_t **)zbx_hashset_search(lld_items, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		for (j = 0; j < override->override_conditions.values_num; j++)
		{
			override_condition = (lld_override_codition_t *)override->override_conditions.values[j];

			zbx_db_insert_add_values(&db_insert_oconditions, override_conditionid, overrideid,
					(int)override_condition->operator, override_condition->macro,
					override_condition->value);

			if (CONDITION_EVAL_TYPE_EXPRESSION == override->evaltype)
			{
				update_template_lld_formula(&override->formula,
						override_condition->override_conditionid, override_conditionid);
			}

			override_conditionid++;
		}

		/* prepare lld_override insert after formula is updated */
		zbx_db_insert_add_values(&db_insert, overrideid, (*pitem)->itemid, override->name, (int)override->step,
				(int)override->evaltype, override->formula, (int)override->stop);

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			override_operation = (lld_override_operation_t *)override->override_operations.values[j];

			zbx_db_insert_add_values(&db_insert_ooperations, override_operationid, overrideid,
					(int)override_operation->operationtype, (int)override_operation->operator,
					override_operation->value);

			if (ZBX_PROTOTYPE_STATUS_COUNT != override_operation->status)
			{
				zbx_db_insert_add_values(&db_insert_opstatus, override_operationid,
						(int)override_operation->status);
			}

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
			{
				zbx_db_insert_add_values(&db_insert_opdiscover, override_operationid,
						(int)override_operation->discover);
			}

			if (NULL != override_operation->delay)
			{
				zbx_db_insert_add_values(&db_insert_opperiod, override_operationid,
						override_operation->delay);
			}

			if (NULL != override_operation->history)
			{
				zbx_db_insert_add_values(&db_insert_ophistory, override_operationid,
						override_operation->history);
			}

			if (NULL != override_operation->trends)
			{
				zbx_db_insert_add_values(&db_insert_optrends, override_operationid,
						override_operation->trends);
			}

			if (TRIGGER_SEVERITY_COUNT != override_operation->severity)
			{
				zbx_db_insert_add_values(&db_insert_opseverity, override_operationid,
						(int)override_operation->severity);
			}

			for (k = 0; k < override_operation->trigger_tags.values_num; k++)
			{
				zbx_ptr_pair_t	pair = override_operation->trigger_tags.values[k];

				zbx_db_insert_add_values(&db_insert_optag, __UINT64_C(0), override_operationid,
						pair.first, pair.second);
			}

			for (k = 0; k < override_operation->templateids.values_num; k++)
			{
				zbx_db_insert_add_values(&db_insert_optemplate, __UINT64_C(0), override_operationid,
						override_operation->templateids.values[k]);
			}

			if (HOST_INVENTORY_COUNT != override_operation->inventory_mode)
			{
				zbx_db_insert_add_values(&db_insert_opinventory, override_operationid,
						(int)override_operation->inventory_mode);
			}

			override_operationid++;
		}

		overrideid++;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_db_insert_execute(&db_insert_oconditions);
	zbx_db_insert_clean(&db_insert_oconditions);

	zbx_db_insert_execute(&db_insert_ooperations);
	zbx_db_insert_clean(&db_insert_ooperations);

	zbx_db_insert_execute(&db_insert_opstatus);
	zbx_db_insert_clean(&db_insert_opstatus);

	zbx_db_insert_execute(&db_insert_opdiscover);
	zbx_db_insert_clean(&db_insert_opdiscover);

	zbx_db_insert_execute(&db_insert_opperiod);
	zbx_db_insert_clean(&db_insert_opperiod);

	zbx_db_insert_execute(&db_insert_ophistory);
	zbx_db_insert_clean(&db_insert_ophistory);

	zbx_db_insert_execute(&db_insert_optrends);
	zbx_db_insert_clean(&db_insert_optrends);

	zbx_db_insert_execute(&db_insert_opseverity);
	zbx_db_insert_clean(&db_insert_opseverity);

	zbx_db_insert_autoincrement(&db_insert_optag, "lld_override_optagid");
	zbx_db_insert_execute(&db_insert_optag);
	zbx_db_insert_clean(&db_insert_optag);

	zbx_db_insert_autoincrement(&db_insert_optemplate, "lld_override_optemplateid");
	zbx_db_insert_execute(&db_insert_optemplate);
	zbx_db_insert_clean(&db_insert_optemplate);

	zbx_db_insert_execute(&db_insert_opinventory);
	zbx_db_insert_clean(&db_insert_opinventory);
}

static void	copy_template_lld_overrides(const zbx_vector_uint64_t *templateids,
		const zbx_vector_uint64_t *lld_itemids, zbx_hashset_t *lld_items)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	lld_override_t		*override;
	zbx_vector_ptr_t	overrides;
	zbx_vector_uint64_t	overrideids;

	zbx_vector_uint64_create(&overrideids);
	zbx_vector_ptr_create(&overrides);

	/* remove overrides from existing items with same key */
	if (0 != lld_itemids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from lld_override where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", lld_itemids->values,
				lld_itemids->values_num);
		DBexecute("%s", sql);
		sql_offset = 0;
	}

	/* read overrides from templates that should be linked */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
		"select l.lld_overrideid,l.itemid,l.name,l.step,l.evaltype,l.formula,l.stop"
			" from lld_override l,items i"
			" where l.itemid=i.itemid"
			" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by l.lld_overrideid");

	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		override = (lld_override_t *)zbx_malloc(NULL, sizeof(lld_override_t));
		ZBX_STR2UINT64(override->overrideid, row[0]);
		ZBX_STR2UINT64(override->itemid, row[1]);
		override->name = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(override->step, row[3]);
		ZBX_STR2UCHAR(override->evaltype, row[4]);
		override->formula = zbx_strdup(NULL, row[5]);
		ZBX_STR2UCHAR(override->stop, row[6]);
		zbx_vector_ptr_create(&override->override_conditions);
		zbx_vector_ptr_create(&override->override_operations);

		zbx_vector_uint64_append(&overrideids, override->overrideid);
		zbx_vector_ptr_append(&overrides, override);
	}
	DBfree_result(result);

	if (0 != overrides.values_num)
	{
		lld_override_conditions_load(&overrides, &overrideids, &sql, &sql_alloc);
		lld_override_operations_load(&overrides, &overrideids, &sql, &sql_alloc);
		save_template_lld_overrides(&overrides, lld_items);
	}
	zbx_free(sql);

	zbx_vector_uint64_destroy(&overrideids);
	zbx_vector_ptr_clear_ext(&overrides, (zbx_clean_func_t)lld_override_free);
	zbx_vector_ptr_destroy(&overrides);
}

/******************************************************************************
 *                                                                            *
 * Function: compare_template_items                                           *
 *                                                                            *
 * Purpose: compare templateid of two template items                          *
 *                                                                            *
 * Parameters: d1 - [IN] first template item                                  *
 *             d2 - [IN] second template item                                 *
 *                                                                            *
 * Return value: compare result (-1 for d1<d2, 1 for d1>d2, 0 for d1==d2)     *
 *                                                                            *
 ******************************************************************************/
static int	compare_template_items(const void *d1, const void *d2)
{
	const zbx_template_item_t	*i1 = *(const zbx_template_item_t **)d1;
	const zbx_template_item_t	*i2 = *(const zbx_template_item_t **)d2;

	return zbx_default_uint64_compare_func(&i1->templateid, &i2->templateid);
}

/******************************************************************************
 *                                                                            *
 * Function: link_template_dependent_items                                    *
 *                                                                            *
 * Purpose: create dependent item index in master item data                   *
 *                                                                            *
 * Parameters: items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_dependent_items(zbx_vector_ptr_t *items)
{
	zbx_template_item_t	*item, *master, item_local;
	int			i, index;
	zbx_vector_ptr_t	template_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&template_index);
	zbx_vector_ptr_append_array(&template_index, items->values, items->values_num);
	zbx_vector_ptr_sort(&template_index, compare_template_items);

	for (i = items->values_num - 1; i >= 0; i--)
	{
		item = (zbx_template_item_t *)items->values[i];
		if (0 != item->master_itemid)
		{
			item_local.templateid = item->master_itemid;
			if (FAIL == (index = zbx_vector_ptr_bsearch(&template_index, &item_local,
					compare_template_items)))
			{
				/* dependent item without master item should be removed */
				THIS_SHOULD_NEVER_HAPPEN;
				free_template_item(item);
				zbx_vector_ptr_remove(items, i);
			}
			else
			{
				master = (zbx_template_item_t *)template_index.values[index];
				zbx_vector_ptr_append(&master->dependent_items, item);
			}
		}
	}

	zbx_vector_ptr_destroy(&template_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: prepare_lld_items                                                *
 *                                                                            *
 * Purpose: prepare lld items by indexing them and scanning for already       *
 *          existing items                                                    *
 *                                                                            *
 * Parameters: items       - [IN] lld items                                   *
 *             lld_itemids - [OUT] identifiers of existing lld items          *
 *             lld_items   - [OUT] lld items indexed by itemid                *
 *                                                                            *
 ******************************************************************************/
static void	prepare_lld_items(const zbx_vector_ptr_t *items, zbx_vector_uint64_t *lld_itemids,
		zbx_hashset_t *lld_items)
{
	int				i;
	const zbx_template_item_t	*item;

	for (i = 0; i < items->values_num; i++)
	{
		item = (const zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			continue;

		if (NULL == item->key)	/* item already existed */
			zbx_vector_uint64_append(lld_itemids, item->itemid);

		zbx_hashset_insert(lld_items, &item, sizeof(zbx_template_item_t *));
	}

	zbx_vector_uint64_sort(lld_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_items                                            *
 *                                                                            *
 * Purpose: copy template items to host                                       *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
void	DBcopy_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	zbx_vector_ptr_t	items, lld_rules;
	int			new_conditions = 0;
	zbx_vector_uint64_t	lld_itemids;
	zbx_hashset_t		lld_items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&items);
	zbx_vector_ptr_create(&lld_rules);

	get_template_items(hostid, templateids, &items);

	if (0 == items.values_num)
		goto out;

	get_template_lld_rule_map(&items, &lld_rules);

	new_conditions = calculate_template_lld_rule_conditionids(&lld_rules);
	update_template_lld_rule_formulas(&items, &lld_rules);

	link_template_dependent_items(&items);
	save_template_items(hostid, &items);
	save_template_lld_rules(&items, &lld_rules, new_conditions);
	save_template_item_applications(&items);
	save_template_discovery_prototypes(hostid, &items);
	copy_template_items_preproc(templateids, &items);

	zbx_vector_uint64_create(&lld_itemids);
	zbx_hashset_create(&lld_items, items.values_num, template_item_hash_func, template_item_compare_func);

	prepare_lld_items(&items, &lld_itemids, &lld_items);
	if (0 != lld_items.num_data)
	{
		copy_template_lld_macro_paths(templateids, &lld_itemids, &lld_items);
		copy_template_lld_overrides(templateids, &lld_itemids, &lld_items);
	}

	zbx_hashset_destroy(&lld_items);
	zbx_vector_uint64_destroy(&lld_itemids);
out:
	zbx_vector_ptr_clear_ext(&lld_rules, (zbx_clean_func_t)free_lld_rule_map);
	zbx_vector_ptr_destroy(&lld_rules);

	zbx_vector_ptr_clear_ext(&items, (zbx_clean_func_t)free_template_item);
	zbx_vector_ptr_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

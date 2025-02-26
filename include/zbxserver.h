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

#ifndef ZABBIX_ZBXSERVER_H
#define ZABBIX_ZBXSERVER_H

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "zbxjson.h"

#define MACRO_TYPE_MESSAGE_NORMAL	0x00000001
#define MACRO_TYPE_MESSAGE_RECOVERY	0x00000002
#define MACRO_TYPE_TRIGGER_URL		0x00000004
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x00000008
#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define MACRO_TYPE_TRIGGER_COMMENTS	0x00000020	/* description */
#define MACRO_TYPE_ITEM_KEY		0x00000040
#define MACRO_TYPE_INTERFACE_ADDR	0x00000100
#define MACRO_TYPE_COMMON		0x00000400
#define MACRO_TYPE_PARAMS_FIELD		0x00000800
#define MACRO_TYPE_SCRIPT		0x00001000
#define MACRO_TYPE_SNMP_OID		0x00002000
#define MACRO_TYPE_HTTPTEST_FIELD	0x00004000
#define MACRO_TYPE_LLD_FILTER		0x00008000
#define MACRO_TYPE_ALERT		0x00010000
#define MACRO_TYPE_TRIGGER_TAG		0x00020000
#define MACRO_TYPE_JMX_ENDPOINT		0x00040000
#define MACRO_TYPE_MESSAGE_ACK		0x00080000
#define MACRO_TYPE_HTTP_RAW		0x00100000
#define MACRO_TYPE_HTTP_JSON		0x00200000
#define MACRO_TYPE_HTTP_XML		0x00400000
#define MACRO_TYPE_ALLOWED_HOSTS	0x00800000
#define MACRO_TYPE_ITEM_TAG		0x01000000

#define MACRO_EXPAND_NO			0
#define MACRO_EXPAND_YES		1

#define STR_CONTAINS_MACROS(str)	(NULL != strchr(str, '{'))

int	get_N_functionid(const char *expression, int N_functionid, zbx_uint64_t *functionid, const char **end);
void	get_functionids(zbx_vector_uint64_t *functionids, const char *expression);

int	evaluate_function(char **value, DC_EVALUATE_ITEM *item, const char *function, const char *parameter,
		const zbx_timespec_t *ts, char **error);

int	substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen);

int	substitute_simple_macros_unmasked(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen);

void	zbx_substitute_simple_macros_allowed_hosts(zbx_history_recv_item_t *item, char **allowed_peers);

void	evaluate_expressions(zbx_vector_ptr_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

void	zbx_determine_items_in_expressions(zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, int item_num);

void	get_trigger_expression_constant(const char *expression, const zbx_token_reference_t *reference,
		char **constant);

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_NUMERIC	(ZBX_MACRO_ANY | ZBX_TOKEN_NUMERIC)
#define ZBX_MACRO_JSON		(ZBX_MACRO_ANY | ZBX_TOKEN_JSON)
#define ZBX_MACRO_SIMPLE	(ZBX_MACRO_ANY | ZBX_TOKEN_SIMPLE_MACRO)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO)

int	substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		int flags, char *error, size_t max_error_len);
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, int macro_type, char *error, size_t maxerrlen);
int	substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths, int macro_type,
		char *error, size_t maxerrlen);
int	substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, size_t max_error_len);
int	substitute_macros_xml(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	substitute_macros_xml_unmasked(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	zbx_substitute_item_name_macros(DC_ITEM *dc_item, const char *name, char **replace_to);
int	substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_ptr_t *lld_macro_paths, char *error, int maxerrlen);
int	xml_xpath_check(const char *xpath, char *error, size_t errlen);

#endif

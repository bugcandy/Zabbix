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
#include "zbxembed.h"
#include "embed.h"
#include "global.h"
#include "duktape.h"
#include "base64.h"
#include "sha256crypt.h"

/******************************************************************************
 *                                                                            *
 * Purpose: export duktape data at the index into buffer                      *
 *                                                                            *
 * Return value: allocated buffer with exported data or NULL on error         *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is non buffer object   *
 *               - if the value stack is empty                                *
 *           The returned buffer must be freed by the caller.                 *
 *                                                                            *
 ******************************************************************************/
char	*es_get_buffer_dyn(duk_context *ctx, int index, duk_size_t *len)
{
	duk_int_t	type;
	const char	*ptr;
	char		*buf = NULL;

	*len = 0;

	type = duk_get_type(ctx, index);

	switch (type)
	{
		case DUK_TYPE_UNDEFINED:
		case DUK_TYPE_NONE:
		case DUK_TYPE_NULL:
			return NULL;
	}

	if (NULL != (ptr = duk_get_buffer_data(ctx, index, len)))
	{
		buf = zbx_malloc(NULL, *len);
		memcpy(buf, ptr, *len);

		return buf;
	}

	if (type == DUK_TYPE_BUFFER)
		return NULL;

	if (SUCCEED == es_duktape_string_decode(duk_safe_to_string(ctx, index), &buf))
		*len = strlen(buf);

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Function: es_btoa                                                          *
 *                                                                            *
 * Purpose: encodes parameter to base64 string                                *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_btoa(duk_context *ctx)
{
	char		*str, *b64str = NULL;
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	str_base64_encode_dyn(str, &b64str, (int)len);
	duk_push_string(ctx, b64str);
	zbx_free(str);
	zbx_free(b64str);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_atob                                                          *
 *                                                                            *
 * Purpose: decodes base64 string                                             *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_atob(duk_context *ctx)
{
	char	*buffer = NULL, *str = NULL, *ptr;
	int	out_size, buffer_size;

	if (SUCCEED != es_duktape_string_decode(duk_require_string(ctx, 0), &str))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert value to utf8");

	buffer_size = (int)strlen(str) * 3 / 4 + 1;
	buffer = zbx_malloc(buffer, (size_t)buffer_size);
	str_base64_decode(str, buffer, buffer_size, &out_size);

	ptr = duk_push_fixed_buffer(ctx, out_size);
	memcpy(ptr, buffer, out_size);

	zbx_free(str);
	zbx_free(buffer);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_md5                                                           *
 *                                                                            *
 * Purpose: compute a md5 checksum                                            *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_md5(duk_context *ctx)
{
	const char	*hex = "0123456789abcdef";
	md5_state_t	state;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	int		i;
	char		*str = NULL, *md5sum, *ptr;
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	ptr = md5sum = (char *)zbx_malloc(NULL, MD5_DIGEST_SIZE * 2 + 1);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)str, (int)len);
	zbx_md5_finish(&state, hash);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		*ptr++ = hex[hash[i] >> 4];
		*ptr++ = hex[hash[i] & 15];
	}

	*ptr = '\0';

	duk_push_string(ctx, md5sum);
	zbx_free(str);
	zbx_free(md5sum);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_sha256                                                        *
 *                                                                            *
 * Purpose: compute a sha256 checksum                                         *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_sha256(duk_context *ctx)
{
	char		*str = NULL, hash_res[ZBX_SHA256_DIGEST_SIZE],
			hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	int		i;
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	zbx_sha256_hash_len(str, len, hash_res);

	for (i = 0 ; i < ZBX_SHA256_DIGEST_SIZE; i++)
	{
		char z[3];

		zbx_snprintf(z, 3, "%02x", (unsigned char)hash_res[i]);
		hash_res_stringhexes[i * 2] = z[0];
		hash_res_stringhexes[i * 2 + 1] = z[1];
	}

	hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2] = '\0';

	duk_push_string(ctx, hash_res_stringhexes);
	zbx_free(str);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_init_global_functions                                         *
 *                                                                            *
 * Purpose: initializes additional global functions                           *
 *                                                                            *
 * Parameters: es - [IN] the embedded scripting engine                        *
 *                                                                            *
 ******************************************************************************/
void	es_init_global_functions(zbx_es_t *es)
{
	duk_push_c_function(es->env->ctx, es_atob, 1);
	duk_put_global_string(es->env->ctx, "atob");

	duk_push_c_function(es->env->ctx, es_btoa, 1);
	duk_put_global_string(es->env->ctx, "btoa");

	duk_push_c_function(es->env->ctx, es_md5, 1);
	duk_put_global_string(es->env->ctx, "md5");

	duk_push_c_function(es->env->ctx, es_sha256, 1);
	duk_put_global_string(es->env->ctx, "sha256");
}

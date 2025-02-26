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
#include "sysinfo.h"
#include "md5.h"
#include "file.h"
#include "dir.h"
#include "zbxregexp.h"
#include "log.h"

#define ZBX_MAX_DB_FILE_SIZE	64 * ZBX_KIBIBYTE	/* files larger than 64 KB cannot be stored in the database */

extern int	CONFIG_TIMEOUT;

int	VFS_FILE_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_stat_t	buf;
	char		*filename;
	int		ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (0 != zbx_stat(filename, &buf))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	SET_UI64_RESULT(result, buf.st_size);

	ret = SYSINFO_RET_OK;
err:
	return ret;
}

int	VFS_FILE_TIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_file_time_t	file_time;
	char		*filename, *type;
	int		ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	type = get_rparam(request, 1);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (SUCCEED != zbx_get_file_time(filename, &file_time))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "modify"))	/* default parameter */
		SET_UI64_RESULT(result, file_time.modification_time);
	else if (0 == strcmp(type, "access"))
		SET_UI64_RESULT(result, file_time.access_time);
	else if (0 == strcmp(type, "change"))
		SET_UI64_RESULT(result, file_time.change_time);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	ret = SYSINFO_RET_OK;
err:
	return ret;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
static int	vfs_file_exists(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*filename;
	int		ret = SYSINFO_RET_FAIL, file_exists = 0, types, types_incl, types_excl;
	DWORD		file_attributes;
	wchar_t		*wpath;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (FAIL == (types_incl = zbx_etypes_to_mask(get_rparam(request, 1), result)) ||
			FAIL == (types_excl = zbx_etypes_to_mask(get_rparam(request, 2), result)))
	{
		goto err;
	}

	if (0 == types_incl)
	{
		if (0 == types_excl)
			types_incl = ZBX_FT_FILE;
		else
			types_incl = ZBX_FT_ALLMASK;
	}

	types = types_incl & (~types_excl) & ZBX_FT_ALLMASK;

	if (NULL == (wpath = zbx_utf8_to_unicode(filename)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot convert file name to UTF-16."));
		goto err;
	}

	file_attributes = GetFileAttributesW(wpath);
	zbx_free(wpath);

	if (INVALID_FILE_ATTRIBUTES == file_attributes)
	{
		DWORD	error;

		switch (error = GetLastError())
		{
			case ERROR_FILE_NOT_FOUND:
				goto exit;
			case ERROR_BAD_NETPATH:	/* special case from GetFileAttributesW() documentation */
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "The specified file is a network share."
						" Use a path to a subfolder on that share."));
				goto err;
			default:
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s",
						strerror_from_system(error)));
				goto err;
		}
	}

	switch (file_attributes & (FILE_ATTRIBUTE_REPARSE_POINT | FILE_ATTRIBUTE_DIRECTORY))
	{
		case FILE_ATTRIBUTE_REPARSE_POINT | FILE_ATTRIBUTE_DIRECTORY:
			if (0 != (types & ZBX_FT_SYM) || 0 != (types & ZBX_FT_DIR))
				file_exists = 1;
			break;
		case FILE_ATTRIBUTE_REPARSE_POINT:
						/* not a symlink directory => symlink regular file*/
						/* counting symlink files as MS explorer */
			if (0 != (types & ZBX_FT_FILE))
				file_exists = 1;
			break;
		case FILE_ATTRIBUTE_DIRECTORY:
			if (0 != (types & ZBX_FT_DIR))
				file_exists = 1;
			break;
		default:	/* not a directory => regular file */
			if (0 != (types & ZBX_FT_FILE))
				file_exists = 1;
	}
exit:
	SET_UI64_RESULT(result, file_exists);
	ret = SYSINFO_RET_OK;
err:
	return ret;
}
#else /* not _WINDOWS or __MINGW32__ */
static int	vfs_file_exists(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_stat_t	buf;
	const char	*filename;
	int		types = 0, types_incl, types_excl;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == (types_incl = zbx_etypes_to_mask(get_rparam(request, 1), result)) ||
			FAIL == (types_excl = zbx_etypes_to_mask(get_rparam(request, 2), result)))
	{
		return SYSINFO_RET_FAIL;
	}

	if (0 == types_incl)
	{
		if (0 == types_excl)
			types_incl = ZBX_FT_FILE;
		else
			types_incl = ZBX_FT_ALLMASK;
	}

	if (0 != (types_incl & ZBX_FT_SYM) || 0 != (types_excl & ZBX_FT_SYM))
	{
		if (0 == lstat(filename, &buf))
		{
			if (0 != S_ISLNK(buf.st_mode))
				types |= ZBX_FT_SYM;
		}
		else if (ENOENT != errno)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s",
					zbx_strerror(errno)));
			return SYSINFO_RET_FAIL;
		}
	}

	if (0 == zbx_stat(filename, &buf))
	{
		if (0 != S_ISREG(buf.st_mode))
			types |= ZBX_FT_FILE;
		else if (0 != S_ISDIR(buf.st_mode))
			types |= ZBX_FT_DIR;
		else if (0 != S_ISSOCK(buf.st_mode))
			types |= ZBX_FT_SOCK;
		else if (0 != S_ISBLK(buf.st_mode))
			types |= ZBX_FT_BDEV;
		else if (0 != S_ISCHR(buf.st_mode))
			types |= ZBX_FT_CDEV;
		else if (0 != S_ISFIFO(buf.st_mode))
			types |= ZBX_FT_FIFO;
	}
	else if (ENOENT != errno)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (0 == (types & types_excl) && 0 != (types & types_incl))
		SET_UI64_RESULT(result, 1);
	else
		SET_UI64_RESULT(result, 0);

	return SYSINFO_RET_OK;
}
#endif

int	VFS_FILE_EXISTS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_file_exists(request, result);
}

int	VFS_FILE_CONTENTS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *tmp, encoding[32];
	char		read_buf[MAX_BUFFER_LEN], *utf8, *contents = NULL;
	size_t		contents_alloc = 0, contents_offset = 0;
	int		nbytes, flen, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_stat_t	stat_buf;
	double		ts;

	ts = zbx_time();

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	tmp = get_rparam(request, 1);

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	if (0 != zbx_fstat(f, &stat_buf))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	if (ZBX_MAX_DB_FILE_SIZE < stat_buf.st_size)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "File is too large for this check."));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	flen = 0;

	while (0 < (nbytes = read(f, read_buf, sizeof(read_buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			zbx_free(contents);
			goto err;
		}

		if (ZBX_MAX_DB_FILE_SIZE < (flen += nbytes))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "File is too large for this check."));
			zbx_free(contents);
			goto err;
		}

		zbx_str_memcpy_alloc(&contents, &contents_alloc, &contents_offset, read_buf, nbytes);
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		zbx_free(contents);
		goto err;
	}

	if (NULL != contents)
	{
		utf8 = convert_to_utf8(contents, contents_offset, encoding);
		zbx_free(contents);
		zbx_rtrim(utf8, "\r\n");

		SET_TEXT_RESULT(result, utf8);
	}
	else
		SET_TEXT_RESULT(result, zbx_strdup(NULL, ""));

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_REGEXP(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *regexp, encoding[32], *output, *start_line_str, *end_line_str;
	char		buf[MAX_BUFFER_LEN], *utf8, *tmp, *ptr = NULL;
	int		nbytes, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	start_line, end_line, current_line = 0;
	double		ts;

	ts = zbx_time();

	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	regexp = get_rparam(request, 1);
	tmp = get_rparam(request, 2);
	start_line_str = get_rparam(request, 3);
	end_line_str = get_rparam(request, 4);
	output = get_rparam(request, 5);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL == regexp || '\0' == *regexp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == start_line_str || '\0' == *start_line_str)
		start_line = 0;
	else if (FAIL == is_uint32(start_line_str, &start_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}

	if (NULL == end_line_str || '\0' == *end_line_str)
		end_line = 0xffffffff;
	else if (FAIL == is_uint32(end_line_str, &end_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	if (start_line > end_line)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Start line parameter must not exceed end line."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	while (0 < (nbytes = zbx_read(f, buf, sizeof(buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		if (++current_line < start_line)
			continue;

		utf8 = convert_to_utf8(buf, nbytes, encoding);
		zbx_rtrim(utf8, "\r\n");
		zbx_regexp_sub(utf8, regexp, output, &ptr);
		zbx_free(utf8);

		if (NULL != ptr)
		{
			SET_STR_RESULT(result, ptr);
			break;
		}

		if (current_line >= end_line)
		{
			/* force EOF state */
			nbytes = 0;
			break;
		}
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	if (0 == nbytes)	/* EOF */
		SET_STR_RESULT(result, zbx_strdup(NULL, ""));

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_REGMATCH(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *regexp, *tmp, encoding[32];
	char		buf[MAX_BUFFER_LEN], *utf8, *start_line_str, *end_line_str;
	int		nbytes, res, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	start_line, end_line, current_line = 0;
	double		ts;

	ts = zbx_time();

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	regexp = get_rparam(request, 1);
	tmp = get_rparam(request, 2);
	start_line_str = get_rparam(request, 3);
	end_line_str = get_rparam(request, 4);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL == regexp || '\0' == *regexp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == start_line_str || '\0' == *start_line_str)
		start_line = 0;
	else if (FAIL == is_uint32(start_line_str, &start_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}

	if (NULL == end_line_str || '\0' == *end_line_str)
		end_line = 0xffffffff;
	else if (FAIL == is_uint32(end_line_str, &end_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	if (start_line > end_line)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Start line must not exceed end line."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	res = 0;

	while (0 == res && 0 < (nbytes = zbx_read(f, buf, sizeof(buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		if (++current_line < start_line)
			continue;

		utf8 = convert_to_utf8(buf, nbytes, encoding);
		zbx_rtrim(utf8, "\r\n");
		if (NULL != zbx_regexp_match(utf8, regexp, NULL))
			res = 1;
		zbx_free(utf8);

		if (current_line >= end_line)
			break;
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	SET_UI64_RESULT(result, res);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_MD5SUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename;
	int		i, nbytes, f = -1, ret = SYSINFO_RET_FAIL;
	md5_state_t	state;
	u_char		buf[16 * ZBX_KIBIBYTE];
	char		*hash_text = NULL;
	size_t		sz;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	double		ts;

	ts = zbx_time();

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	zbx_md5_init(&state);

	while (0 < (nbytes = (int)read(f, buf, sizeof(buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		zbx_md5_append(&state, (const md5_byte_t *)buf, nbytes);
	}

	zbx_md5_finish(&state, hash);

	if (0 > nbytes)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	/* convert MD5 hash to text form */

	sz = MD5_DIGEST_SIZE * 2 + 1;
	hash_text = (char *)zbx_malloc(hash_text, sz);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		zbx_snprintf(&hash_text[i << 1], sz - (i << 1), "%02x", hash[i]);
	}

	SET_STR_RESULT(result, hash_text);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

static u_long	crctab[] =
{
	0x0,
	0x04c11db7, 0x09823b6e, 0x0d4326d9, 0x130476dc, 0x17c56b6b,
	0x1a864db2, 0x1e475005, 0x2608edb8, 0x22c9f00f, 0x2f8ad6d6,
	0x2b4bcb61, 0x350c9b64, 0x31cd86d3, 0x3c8ea00a, 0x384fbdbd,
	0x4c11db70, 0x48d0c6c7, 0x4593e01e, 0x4152fda9, 0x5f15adac,
	0x5bd4b01b, 0x569796c2, 0x52568b75, 0x6a1936c8, 0x6ed82b7f,
	0x639b0da6, 0x675a1011, 0x791d4014, 0x7ddc5da3, 0x709f7b7a,
	0x745e66cd, 0x9823b6e0, 0x9ce2ab57, 0x91a18d8e, 0x95609039,
	0x8b27c03c, 0x8fe6dd8b, 0x82a5fb52, 0x8664e6e5, 0xbe2b5b58,
	0xbaea46ef, 0xb7a96036, 0xb3687d81, 0xad2f2d84, 0xa9ee3033,
	0xa4ad16ea, 0xa06c0b5d, 0xd4326d90, 0xd0f37027, 0xddb056fe,
	0xd9714b49, 0xc7361b4c, 0xc3f706fb, 0xceb42022, 0xca753d95,
	0xf23a8028, 0xf6fb9d9f, 0xfbb8bb46, 0xff79a6f1, 0xe13ef6f4,
	0xe5ffeb43, 0xe8bccd9a, 0xec7dd02d, 0x34867077, 0x30476dc0,
	0x3d044b19, 0x39c556ae, 0x278206ab, 0x23431b1c, 0x2e003dc5,
	0x2ac12072, 0x128e9dcf, 0x164f8078, 0x1b0ca6a1, 0x1fcdbb16,
	0x018aeb13, 0x054bf6a4, 0x0808d07d, 0x0cc9cdca, 0x7897ab07,
	0x7c56b6b0, 0x71159069, 0x75d48dde, 0x6b93dddb, 0x6f52c06c,
	0x6211e6b5, 0x66d0fb02, 0x5e9f46bf, 0x5a5e5b08, 0x571d7dd1,
	0x53dc6066, 0x4d9b3063, 0x495a2dd4, 0x44190b0d, 0x40d816ba,
	0xaca5c697, 0xa864db20, 0xa527fdf9, 0xa1e6e04e, 0xbfa1b04b,
	0xbb60adfc, 0xb6238b25, 0xb2e29692, 0x8aad2b2f, 0x8e6c3698,
	0x832f1041, 0x87ee0df6, 0x99a95df3, 0x9d684044, 0x902b669d,
	0x94ea7b2a, 0xe0b41de7, 0xe4750050, 0xe9362689, 0xedf73b3e,
	0xf3b06b3b, 0xf771768c, 0xfa325055, 0xfef34de2, 0xc6bcf05f,
	0xc27dede8, 0xcf3ecb31, 0xcbffd686, 0xd5b88683, 0xd1799b34,
	0xdc3abded, 0xd8fba05a, 0x690ce0ee, 0x6dcdfd59, 0x608edb80,
	0x644fc637, 0x7a089632, 0x7ec98b85, 0x738aad5c, 0x774bb0eb,
	0x4f040d56, 0x4bc510e1, 0x46863638, 0x42472b8f, 0x5c007b8a,
	0x58c1663d, 0x558240e4, 0x51435d53, 0x251d3b9e, 0x21dc2629,
	0x2c9f00f0, 0x285e1d47, 0x36194d42, 0x32d850f5, 0x3f9b762c,
	0x3b5a6b9b, 0x0315d626, 0x07d4cb91, 0x0a97ed48, 0x0e56f0ff,
	0x1011a0fa, 0x14d0bd4d, 0x19939b94, 0x1d528623, 0xf12f560e,
	0xf5ee4bb9, 0xf8ad6d60, 0xfc6c70d7, 0xe22b20d2, 0xe6ea3d65,
	0xeba91bbc, 0xef68060b, 0xd727bbb6, 0xd3e6a601, 0xdea580d8,
	0xda649d6f, 0xc423cd6a, 0xc0e2d0dd, 0xcda1f604, 0xc960ebb3,
	0xbd3e8d7e, 0xb9ff90c9, 0xb4bcb610, 0xb07daba7, 0xae3afba2,
	0xaafbe615, 0xa7b8c0cc, 0xa379dd7b, 0x9b3660c6, 0x9ff77d71,
	0x92b45ba8, 0x9675461f, 0x8832161a, 0x8cf30bad, 0x81b02d74,
	0x857130c3, 0x5d8a9099, 0x594b8d2e, 0x5408abf7, 0x50c9b640,
	0x4e8ee645, 0x4a4ffbf2, 0x470cdd2b, 0x43cdc09c, 0x7b827d21,
	0x7f436096, 0x7200464f, 0x76c15bf8, 0x68860bfd, 0x6c47164a,
	0x61043093, 0x65c52d24, 0x119b4be9, 0x155a565e, 0x18197087,
	0x1cd86d30, 0x029f3d35, 0x065e2082, 0x0b1d065b, 0x0fdc1bec,
	0x3793a651, 0x3352bbe6, 0x3e119d3f, 0x3ad08088, 0x2497d08d,
	0x2056cd3a, 0x2d15ebe3, 0x29d4f654, 0xc5a92679, 0xc1683bce,
	0xcc2b1d17, 0xc8ea00a0, 0xd6ad50a5, 0xd26c4d12, 0xdf2f6bcb,
	0xdbee767c, 0xe3a1cbc1, 0xe760d676, 0xea23f0af, 0xeee2ed18,
	0xf0a5bd1d, 0xf464a0aa, 0xf9278673, 0xfde69bc4, 0x89b8fd09,
	0x8d79e0be, 0x803ac667, 0x84fbdbd0, 0x9abc8bd5, 0x9e7d9662,
	0x933eb0bb, 0x97ffad0c, 0xafb010b1, 0xab710d06, 0xa6322bdf,
	0xa2f33668, 0xbcb4666d, 0xb8757bda, 0xb5365d03, 0xb1f740b4
};

/******************************************************************************
 *                                                                            *
 * Comments: computes POSIX 1003.2 checksum                                   *
 *                                                                            *
 ******************************************************************************/
int	VFS_FILE_CKSUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename;
	int		i, nr, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	crc, flen;
	u_char		buf[16 * ZBX_KIBIBYTE];
	u_long		cval;
	double		ts;

	ts = zbx_time();

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	crc = flen = 0;

	while (0 < (nr = (int)read(f, buf, sizeof(buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		flen += nr;

		for (i = 0; i < nr; i++)
			crc = (crc << 8) ^ crctab[((crc >> 24) ^ buf[i]) & 0xff];
	}

	if (0 > nr)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	/* include the length of the file */
	for (; 0 != flen; flen >>= 8)
		crc = (crc << 8) ^ crctab[((crc >> 24) ^ flen) & 0xff];

	cval = ~crc;

	SET_UI64_RESULT(result, cval);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

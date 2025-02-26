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

#include "log.h"

#include "comms.h"
#include "servercomms.h"
#include "daemon.h"

extern unsigned int	configured_tls_connect_mode;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
extern char	*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char	*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char	*CONFIG_TLS_PSK_IDENTITY;
#endif

int	connect_to_server(zbx_socket_t *sock, int timeout, int retry_interval)
{
	int	res, lastlogtime, now;
	char	*tls_arg1, *tls_arg2;

	zabbix_log(LOG_LEVEL_DEBUG, "In connect_to_server() [%s]:%d [timeout:%d]",
			CONFIG_SERVER, CONFIG_SERVER_PORT, timeout);

	switch (configured_tls_connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
			tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = CONFIG_TLS_PSK_IDENTITY;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (FAIL == (res = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, CONFIG_SERVER, CONFIG_SERVER_PORT, timeout,
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		if (0 == retry_interval)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to connect to the server [%s]:%d [%s]",
					CONFIG_SERVER, CONFIG_SERVER_PORT, zbx_socket_strerror());
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to connect to the server [%s]:%d [%s]. Will retry every"
					" %d second(s)", CONFIG_SERVER, CONFIG_SERVER_PORT, zbx_socket_strerror(),
					retry_interval);

			lastlogtime = (int)time(NULL);

			while (ZBX_IS_RUNNING() && FAIL == (res = zbx_tcp_connect(sock, CONFIG_SOURCE_IP,
					CONFIG_SERVER, CONFIG_SERVER_PORT, timeout, configured_tls_connect_mode,
					tls_arg1, tls_arg2)))
			{
				now = (int)time(NULL);

				if (LOG_ENTRY_INTERVAL_DELAY <= now - lastlogtime)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Still unable to connect...");
					lastlogtime = now;
				}

				sleep(retry_interval);
			}

			if (FAIL != res)
				zabbix_log(LOG_LEVEL_WARNING, "Connection restored.");
		}
	}

	return res;
}

void	disconnect_server(zbx_socket_t *sock)
{
	zbx_tcp_close(sock);
}

/******************************************************************************
 *                                                                            *
 * Function: get_data_from_server                                             *
 *                                                                            *
 * Purpose: get configuration and other data from server                      *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, 0))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_tcp_recv_large(sock))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Received [%s] from server", sock->buffer);

	ret = SUCCEED;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: put_data_to_server                                               *
 *                                                                            *
 * Purpose: send data to server                                               *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datalen:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)buffer_size);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, 0))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_recv_response(sock, 0, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

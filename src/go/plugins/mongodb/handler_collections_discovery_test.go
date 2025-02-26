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

package mongodb

import (
	"errors"
	"reflect"
	"testing"

	"zabbix.com/pkg/zbxerr"
)

func Test_collectionsDiscoveryHandler(t *testing.T) {
	type args struct {
		s   Session
		dbs map[string][]string
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Must return a list of collections",
			args: args{
				s: NewMockConn(),
				dbs: map[string][]string{
					"testdb": {"col1", "col2"},
					"local":  {"startup_log"},
					"config": {"system.sessions"},
				},
			},
			want: "[{\"{#COLLECTION}\":\"system.sessions\",\"{#DBNAME}\":\"config\"},{\"{#COLLECTION}\":" +
				"\"startup_log\",\"{#DBNAME}\":\"local\"},{\"{#COLLECTION}\":\"col1\",\"{#DBNAME}\":\"testdb\"}," +
				"{\"{#COLLECTION}\":\"col2\",\"{#DBNAME}\":\"testdb\"}]",
			wantErr: nil,
		},
		{
			name: "Must catch DB.DatabaseNames() error",
			args: args{
				s:   NewMockConn(),
				dbs: map[string][]string{mustFail: {}},
			},
			want:    nil,
			wantErr: zbxerr.ErrorCannotFetchData,
		},
		{
			name: "Must catch DB.CollectionNames() error",
			args: args{
				s:   NewMockConn(),
				dbs: map[string][]string{"MyDatabase": {mustFail}},
			},
			want:    nil,
			wantErr: zbxerr.ErrorCannotFetchData,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			for db, cc := range tt.args.dbs {
				tt.args.s.DB(db)
				for _, c := range cc {
					tt.args.s.DB(db).C(c)
				}
			}

			got, err := collectionsDiscoveryHandler(tt.args.s, nil)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("collectionsDiscoveryHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("collectionsDiscoveryHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

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


var CTree = function(name, nodes) {
	this.name = name;
	this.nodes = nodes;

	if ((tree_init = cookie.read(name)) != null) {
		var nodes = tree_init.split(this.cookie_delimiter);

		for (var i = 0, size = nodes.length; i < size; i++) {
			this.onStartSetStatus(nodes[i]);
		}

		this.onStartOpen(nodes);
	}
};

CTree.prototype = {
	name: null,
	nodes: [],
	/*
	 * Encoding values separated by a comma (%2C), makes the cookie very large in size. A dot, however, remains a dot.
	 * Must be synced with class.cookie.js for consistency.
	 */
	cookie_delimiter: '.',

	getNodeStatus: function(id) {
		return (empty(this.nodes[id]) || this.nodes[id].status == 'close') ? 'close' : 'open';
	},

	changeNodeStatus: function(id) {
		if (!empty(this.nodes[id])) {
			this.nodes[id].status = (this.nodes[id].status == 'close') ? 'open' : 'close';

			var cookieData = '';

			for (var id in this.nodes) {
				var node = this.nodes[id];

				if (!empty(node.status) && node.status == 'open') {
					cookieData += id + this.cookie_delimiter;
				}
			}

			cookie.create(this.name, cookieData.slice(0, -1));
		}
	},

	closeSNodeX: function(id, arrow) {
		if (!empty(this.nodes[id]) && !empty(arrow)) {
			var nodelist = this.nodes[id].nodelist.split(',');

			if (this.getNodeStatus(id) == 'close') {
				this.openNode(nodelist);
				arrow.className = 'arrow-down';
			}
			else {
				this.closeNode(nodelist);
				arrow.className = 'arrow-right';
			}

			this.changeNodeStatus(id);
		}
	},

	openNode: function(nodes) {
		for (var i = 0, size = nodes.length; i < size; i++) {
			var nodeId = nodes[i].replace('.', '\\.');

			jQuery('#id_' + nodeId).show();

			if (this.getNodeStatus(nodes[i]) == 'open') {
				this.openNode(this.nodes[nodes[i]].nodelist.split(','));
			}
		}
	},

	closeNode: function(nodes) {
		for (var i = 0, size = nodes.length; i < size; i++) {
			var nodeId = nodes[i].replace('.', '\\.');

			jQuery('#id_' + nodeId).hide();

			if (this.checkParent(nodes[i])) {
				if (this.getNodeStatus(nodes[i]) == 'open') {
					this.closeNode(this.nodes[nodes[i]].nodelist.split(','));
				}
			}
		}
	},

	onStartOpen: function(nodes) {
		for (var i = 0, size = nodes.length; i < size; i++) {
			if (!empty(nodes[i])) {
				if (this.checkParent(nodes[i])) {
					this.openNode(this.nodes[nodes[i]].nodelist.split(','));
				}
			}
		}
	},

	onStartSetStatus: function(id) {
		if (!empty(this.nodes[id])) {
			var arrow = document.getElementById('idi_' + id).getElementsByTagName('span')[0];

			if (!empty(arrow)) {
				arrow.className = 'arrow-down';
			}

			this.nodes[id].status = 'open';
		}
	},

	checkParent: function(id) {
		if (id == '0') {
			return true;
		}
		else if (empty(this.nodes[id])) {
			return false;
		}
		else if (this.nodes[id].status != 'open') {
			return false;
		}
		else {
			return this.checkParent(this.nodes[id].parentid);
		}
	}
};

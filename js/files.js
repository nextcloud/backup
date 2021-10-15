/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


/** global: OCA */

var Backup = function() {
	this.init();
};


Backup.prototype = {

	fileActions: null,

	init: function() {
		this._initFileActions();
	},

	_initFileActions: function() {
		var self = this;

		this.fileActions = OCA.Files.fileActions;
		this.fileActions.registerAction({
			name: 'ScanBackupFolder',
			displayName: t('backup', 'Scan Backup Folder'),
			mime: 'file',
			filename: 'restoring-point.data',
			order: -50,
			iconClass: 'icon-folder',
			permissions: OC.PERMISSION_READ,
			actionHandler: self.scanBackupFile
		});
	},

	scanBackupFile: function(fileName, context) {
		var fileId = context.$file.data('id')
		$.ajax({
			method: 'POST',
			url: OC.linkToOCS('apps/backup/action/scan', 2) + fileId + '?format=json'
		}).done(function(res) {
			OCP.Toast.success(res.ocs.data.message);
		}).fail(function(res) {
			var message = res.responseJSON.ocs.meta.message;
			OCP.Toast.warning((message) ? message : 'failed to initiate scan');
		});
	}


};


OCA.Files.Backup = Backup;

$(document).ready(function() {
	OCA.Files.Backup = new Backup();
});




/**
 * Nextcloud - Backup now. Restore later.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Louis Chmn <louis@chmn.me>
 * @copyright 2021, Maxence Lange <maxence@artificial-owl.com>
 * @license AGPL-3.0-or-later
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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import Folder from '@mdi/svg/svg/folder.svg'
import {
	FileAction,
	registerFileAction,
	Permission,
	FileType,
} from '@nextcloud/files'

window.addEventListener('DOMContentLoaded', function() {
	registerFileAction(new FileAction({
		id: 'ScanBackupFolder',
		displayName() { return t('backup', 'Scan Backup Folder') },
		iconSvgInline: () => Folder,
		enabled(nodes) {
			return nodes.length === 1 && nodes[0].type === FileType.File && nodes[0].basename === 'restoring-point.data' && (nodes[0].permissions & Permission.READ) !== 0
		},
		async exec({ fileid }) {
			try {
				const res = await axios.post(generateOcsUrl('apps/backup/action/scan/') + fileid + '?format=json')
				showSuccess(res.data.ocs.data.message)
				return true
			} catch (e) {
				showWarning((e.message) || 'failed to initiate scan')
				return false
			}
		},
		order: -50,
	}))
})

<!--
  - @copyright Copyright (c) 2021 Louis Chemineau <louis@chmn.me>
  -
  - @author Louis Chemineau <louis@chmn.me>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div>
		<GeneralSettingsSection @general-settings-change="handleGeneralSettingsChange" />
		<ExternalLocationsSection @external-storages-change="handleExternalStoragesChange" />
		<RestoringPointsListSection :general-settings="generalSettings" :external-storages="externalStorages" />
		<AppDataSection :external-storages="externalStorages" />
	</div>
</template>

<script>
import GeneralSettingsSection from '../components/GeneralSettingsSection'
import ExternalLocationsSection from '../components/ExternalLocationsSection'
import RestoringPointsListSection from '../components/RestoringPointsListSection'
import AppDataSection from '../components/AppDataSection'

import SettingsModel from '../models/SettingsModel.js'

export default {
	name: 'BackupAdminSettings',
	components: {
		GeneralSettingsSection,
		ExternalLocationsSection,
		RestoringPointsListSection,
		AppDataSection,
	},
	data: () => {
		return {
			generalSettings: undefined,
			externalStorages: [],
		}
	},
	methods: {
		/**
		 * @param {SettingsModel} value - The new value.
		 */
		handleGeneralSettingsChange(value) {
			this.generalSettings = value
		},
		/**
		 * @param {Array<object>} value - The new value.
		 */
		handleExternalStoragesChange(value) {
			this.externalStorages = value
		},
	},
}
</script>

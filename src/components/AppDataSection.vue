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
	<SettingsSection
		:title="t('backup', 'App Data')"
		:description="t('backup', 'Choose where the backup app will initially store the restoring points.')">
		<form ref="app-data-form" class="app-data__form">
			<select
				v-model="appDataForm.storageId"
				class="app-data__form__select"
				name="storageId"
				:disabled="loadingAppData">
				<template
					v-if="appDataForm !== undefined">
					<option v-for="external in externalStoragesWithLocal"
						:key="external.storageId"
						:selected="external.storageId === appDataForm.storageId"
						:value="external.storageId">
						{{ external.storage }}
					</option>
				</template>
			</select>
			<input v-model="appDataForm.root"
				class="app-data__form__input"
				type="text"
				:placeholder="t('backup', 'Path in which to store the data. (ex: \'app_data\')')"
				:disabled="loadingAppData || appDataForm.storageId === 0"
				name="root">
			<button class="primary"
				:disabled="loadingAppData || !formIsTouched()"
				@click.prevent="showSetAppDataPopup = true">
				{{ t('backup', 'Set as App Data') }}
			</button>

			<span v-if="loadingAppData" class="icon-loading" />
			<WindowClose
				v-else-if="error"
				slot="icon"
				fill-color="#e9322d"
				:title="t('backup', 'Error')" />
		</form>

		<Modal v-if="showSetAppDataPopup"
			size="large"
			@close="!showSetAppDataPopup">
			<div class="app-data__set-popup">
				<div class="app-data__set-popup__header">
					{{ t('backup', "App Data change.") }}
				</div>
				<div class="app-data__set-popup__content">
					{{ t('backup', 'Changing the App Data will delete the data stored in the previous one including restoring points.') }}

					<CheckboxRadioSwitch :loading="loadingSetAppData" :checked.sync="validationCheckboxGorSetAppData">
						{{ t('backup', 'I understand some data will be deleted.') }}
					</CheckboxRadioSwitch>
				</div>
				<div class="app-data__set-popup__actions">
					<button @click="showSetAppDataPopup = false">
						Cancel
					</button>
					<button class="primary"
						:class="{loading: loadingSetAppData}"
						:disabled="!validationCheckboxGorSetAppData || loadingSetAppData"
						@click="setAppData">
						{{ t('backup', 'Change the App Data') }}
					</button>
				</div>
			</div>
		</Modal>
	</SettingsSection>
</template>

<script>
import WindowClose from 'vue-material-design-icons/WindowClose.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'

import logger from '../logger'

/**
 * @typedef {object} ExternalLocation
 * @property {number} storageId - The ID of the external storage.
 * @property {string} storage - The description of the external storage.
 * @property {string} root - The path where the restoring points will be stored.
 */

export default {
	name: 'AppDataSection',
	components: {
		SettingsSection,
		WindowClose,
		Modal,
		CheckboxRadioSwitch,
	},

	props: {
		externalStorages: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			/** @type {ExternalLocation} */
			appData: undefined,
			/** @type {ExternalLocation} */
			appDataForm: {
				storageId: 0,
				root: '',
			},
			loadingAppData: false,
			loadingSetAppData: false,
			showSetAppDataPopup: false,
			validationCheckboxGorSetAppData: false,
			error: false,
		}
	},

	computed: {
		/** @return {Array<ExternalLocation>} */
		externalStoragesWithLocal() {
			return [
				{
					storageId: 0,
					root: '',
					storage: t('backup', 'Local storage'),
				},
				...this.externalStorages,
			]
		},
	},

	async mounted() {
		this.fetchAppData()
	},

	methods: {
		async fetchAppData() {
			try {
				this.loadingAppData = true
				const response = await axios.get(generateOcsUrl('apps/backup/appdata'))
				this.appData = response.data.ocs.data
				this.appDataForm = { ...response.data.ocs.data }
			} catch (error) {
				showError(t('backup', 'Unable to fetch app data'))
				logger.error('An error occurred while fetching app data', error)
			} finally {
				this.loadingAppData = false
			}
		},

		/**
		 * Add a new external location based on the form.
		 */
		async displaySetAppDataPopup() {
			this.showSetAppDataPopup = true
		},

		/**
		 * Add a new external location based on the form.
		 */
		async setAppData() {
			if (this.loadingSetAppData) {
				return
			}

			try {
				this.error = false
				this.loadingSetAppData = true
				const response = await axios.post(generateOcsUrl('apps/backup/appdata'), this.appDataForm)
				this.appData = response.data.ocs.data
				this.appDataForm = { ...response.data.ocs.data }
				this.showSetAppDataPopup = false
				showSuccess(t('backup', 'App data has been set'))
			} catch (error) {
				this.error = true
				showError(t('backup', 'Unable to set app data'))
				logger.error('An error occurred while setting app data', error)
			} finally {
				this.loadingSetAppData = false
			}
		},

		/** @return {boolean} */
		formIsTouched() {
			if (this.appData === undefined) {
				return false
			}

			return this.appData.storageId !== this.appDataForm.storageId || this.appData.root !== this.appDataForm.root
		},
	},
}
</script>
<style lang="scss" scoped>

button.loading {
	color: transparent !important;

	&::after {
		height: 20px !important;
		width: 20px !important;
		margin: -12px 0 0 -12px !important;
	}

	.icon {
		background-image: none !important;
	}
}

.app-data {
	&__form {
		margin: 24px 0;
		display: flex;

		.icon-loading {
			margin-left: 8px;
		}

		& > * {
			margin-right: 8px;
		}

		&__select {
			width: 400px;
		}

		&__input {
			width: 400px;
		}
	}

	&__set-popup {
		padding: 16px;

		&__header {
			font-weight: bold;
			margin-bottom: 12px;
		}

		&__content {
			margin-bottom: 12px;
		}

		&__actions {
			display: flex;
			justify-content: end;

			button {
				margin: 0 8px;
			}
		}
	}
}
</style>

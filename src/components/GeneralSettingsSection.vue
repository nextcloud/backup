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
		class="backup-settings"
		:title="t('backup', 'Backups configuration')"
		:description="t('backup', 'General configuration on how and when your restoring points are created.')">
		<form ref="settings-form">
			<h3 class="backup-settings__sub-headers">
				<span>
					{{ t('backup', 'Restoring point packing') }}
				</span>
				<span v-if="loadingSetSettings > 0" class="icon-loading" />
			</h3>
			<CheckboxRadioSwitch :loading="loadingFetchSettings" :checked.sync="settings.packBackup" @update:checked="setSettings">
				{{ t('backup', 'Enable restoring point packing') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch :loading="loadingFetchSettings"
				:checked.sync="settings.packEncrypt"
				:disabled="!settings.packBackup"
				@update:checked="setSettings">
				{{ t('backup', 'Enable encryption') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch :loading="loadingFetchSettings"
				:checked.sync="settings.packCompress"
				:disabled="!settings.packBackup"
				@update:checked="setSettings">
				{{ t('backup', 'Enable compression') }}
			</CheckboxRadioSwitch>

			<h3 class="backup-settings__sub-headers">
				{{ t('backup', 'Backup schedule') }}
			</h3>

			<label class="backup-settings__time-slots">
				{{ t('backup', 'Create restoring points during the following time slot during the day:') }}
				<select v-model.number="settings.timeSlotsStart"
					:disabled="loadingFetchSettings"
					name="timeSlotsStart"
					@change="setSettings">
					<option v-for="(hour, index) in new Array(24)" :key="index" :value="index">{{ index }}</option>
				</select>
				{{ t('backup', 'h') }}
				{{ t('backup', 'and') }}
				<select v-model.number="settings.timeSlotsEnd"
					:disabled="loadingFetchSettings"
					name="timeSlotsEnd"
					@change="setSettings">
					<option v-for="(hour, index) in new Array(24)" :key="index" :value="index">{{ index }}</option>
				</select>
				{{ t('backup', 'h') }}
			</label>

			<CheckboxRadioSwitch :loading="loadingFetchSettings" :checked.sync="settings.allowWeekdays" @update:checked="setSettings">
				{{ t('backup', 'Allow to create restoring points during week days') }}
			</CheckboxRadioSwitch>

			<ul class="backup-settings__delays">
				<li>
					{{ t('backup', 'Delay between two full restoring points') }}:
					<input v-model.number="settings.delayFullRestoringPoint"
						:disabled="loadingFetchSettings"
						class="backup-settings__input"
						type="text"
						@change="setSettings">
				</li>

				<li>
					{{ t('backup', 'Delay between two partial restoring points') }}:
					<input v-model.number="settings.delayPartialRestoringPoint"
						:disabled="loadingFetchSettings"
						class="backup-settings__input"
						type="text"
						@change="setSettings">
				</li>
			</ul>
		</form>

		<h3 class="backup-settings__sub-headers">
			{{ t('backup', 'Schedule summary') }}
		</h3>
		<ul class="backup-settings__summary">
			<template v-if="settings.allowWeekdays">
				<li>
					{{ t('backup', 'A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00.', settings) }}
				</li>
				<li>
					{{ t('backup', 'A full restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00.', settings) }}
				</li>
			</template>
			<template v-if="!settings.allowWeekdays">
				<li>
					{{ t('backup', 'A full restoring point will be created {delayFullRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends.', settings) }}
				</li>
				<li>
					{{ t('backup', 'A full restoring point will be created {delayPartialRestoringPoint} days after the last one between {timeSlotsStart}:00 and {timeSlotsEnd}:00 during weekends.', settings) }}
				</li>
			</template>
		</ul>

		<div class="backup-settings__actions">
			<h3 class="backup-settings__sub-headers">
				{{ t('backup', 'Export backup configuration') }}
			</h3>
			<div class="backup-settings__actions__action">
				{{ t('backup', 'You can export your settings with the below button. The exported file is important as it allows you to restore your backup in case of full data lost. Keep it in a safe place!') }}
				<button
					:disabled="loadingExportSettings"
					class="backup-settings__actions__action__export"
					:class="{loading: loadingExportSettings}"
					@click="downloadSettings">
					<span class="icon icon-external" />
					{{ t('backup', 'Export configuration') }}
				</button>
			</div>
			<div v-if="exportedPrivateKey !== undefined" class="backup-settings__export__info">
				{{ t('backup', 'Your settings export as been downloaded encrypted. To be able to decrypt it later, please keep the following private key in a safe place:') }}
				<br>
				<code><b>{{ exportedPrivateKey }}</b></code>
				<br>
			</div>

			<div class="backup-settings__actions__action">
				<h3 class="backup-settings__sub-headers">
					{{ t('backup', 'Request the creation of a new restoring point now') }}
				</h3>
				<div v-if="settings.restoringPointRequested" class="backup-settings__actions__action__info">
					{{ t('backup', 'The creation of a restoring point as been requested and will be initiated soon.') }}
				</div>
				<button class="primary" :disabled="loadingFetchSettings || settings.restoringPointRequested" @click="requestRestoringPointType = 'full'">
					{{ t('backup', 'Create full restoring point') }}
				</button>

				<Modal v-if="requestRestoringPointType !== ''"
					size="large"
					@close="requestRestoringPointType = ''">
					<div class="backup-settings__request-modal">
						<div class="backup-settings__request-modal__header">
							{{ t('backup', "Request a {mode} restoring point.", { mode: requestRestoringPointType}) }}
						</div>
						<div class="backup-settings__request-modal__content">
							{{ t('backup', 'Requesting a backup will put the server in maintenance mode.') }}

							<CheckboxRadioSwitch :loading="loadingRequestRestoringPoint" :checked.sync="validationCheckboxForRestoringPointRequest">
								{{ t('backup', 'I understand that the server will be put in maintenance mode.') }}
							</CheckboxRadioSwitch>
						</div>
						<div class="backup-settings__request-modal__actions">
							<button class="error" @click="requestRestoringPointType = ''">
								Cancel
							</button>
							<button class="primary"
								:class="{loading: loadingRequestRestoringPoint}"
								:disabled="!validationCheckboxForRestoringPointRequest"
								@click="requestRestoringPoint">
								{{ t('backup', 'Request {mode} restoring point', { mode: requestRestoringPointType}) }}
							</button>
						</div>
					</div>
				</Modal>
			</div>
		</div>
	</SettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'

import logger from '../logger'
import SettingsModel from '../models/SettingsModel.js'

export default {
	name: 'GeneralSettingsSection',
	components: {
		SettingsSection,
		CheckboxRadioSwitch,
		Modal,
	},

	data() {
		return {
			// Init settings with dummy value to have the form displayed initial during loading.
			settings: new SettingsModel({
				date_full_rp: 0,
				date_partial_rp: 0,
				time_slots: '0-0',
				delay_full_rp: 0,
				delay_partial_rp: 0,
				allow_weekday: false,
				pack_backup: false,
				pack_compress: false,
				pack_encrypt: false,
				mockup_date: 0,
				partial: 0,
				full: 0,
			}),
			exportedPrivateKey: undefined,
			exportedSettings: undefined,
			loadingFetchSettings: false,
			loadingSetSettings: 0,
			loadingRequestRestoringPoint: false,
			loadingExportSettings: false,
			/** @type {'full'|'partial'|''} */
			requestRestoringPointType: '',
			validationCheckboxForRestoringPointRequest: false,
		}
	},

	computed: {
		/** @return {HTMLFormElement} */
		settingsForm() {
			return this.$refs['settings-form']
		},
	},

	async mounted() {
		this.fetchSettings()
	},

	methods: {
		async fetchSettings() {
			try {
				this.loadingFetchSettings = true
				const response = await axios.get(generateOcsUrl('apps/backup/settings'))
				this.settings = new SettingsModel(response.data.ocs.data)
				this.$emit('general-settings-change', this.settings)
			} catch (error) {
				showError(t('backup', 'Unable to fetch the settings'))
				logger.error('An error occurred while fetching the backup settings', { error })
			} finally {
				this.loadingFetchSettings = false
			}
		},

		async setSettings() {
			try {
				if (!this.settingsForm.reportValidity()) {
					throw new Error('Form is invalid.')
				}

				this.loadingSetSettings++
				const response = await axios.put(generateOcsUrl('apps/backup/settings'), { settings: this.settings.settings })
				// Limit update to values computed by the server.
				this.settings = new SettingsModel({
					...this.settings.settings,
					date_full_rp: response.data.ocs.data.date_full_rp,
					date_partial_rp: response.data.ocs.data.date_partial_rp,
					mockup_date: response.data.ocs.data.mockup_date,
					partial: response.data.ocs.data.partial,
					full: response.data.ocs.data.full,
				})
				this.$emit('general-settings-change', this.settings)
				showSuccess(t('backup', 'Settings saved'))
			} catch (error) {
				showError(t('backup', 'Unable to save the settings'))
				logger.error('An error occurred while saving the backup settings', { error })
			} finally {
				this.loadingSetSettings--
			}
		},

		async requestRestoringPoint() {
			try {
				this.loadingRequestRestoringPoint = true
				const response = await axios.post(generateOcsUrl('apps/backup/action/backup/{mode}', { mode: this.requestRestoringPointType }))
				if (response.data.ocs.meta.status !== 'ok') {
					throw new Error(response.data.ocs.meta.message)
				}
				await this.fetchSettings()
				this.requestRestoringPointType = ''
				this.validationCheckboxForRestoringPointRequest = false
			} catch (error) {
				showError(t('backup', 'Unable to request restoring point'))
				logger.error('An error occurred while requesting the creation of a restoring point', { error })
			} finally {
				this.loadingRequestRestoringPoint = false
			}
		},

		async downloadSettings() {
			if (this.loadingExportSettings) {
				return
			}

			if (this.exportedSettings !== undefined) {
				this.saveFile('settings.asc', this.exportedSettings)
				return
			}

			try {
				this.loadingExportSettings = true
				const response = await axios.get(generateOcsUrl('apps/backup/setup/encrypted'))
				this.exportedSettings = response.data.ocs.data.content
				this.exportedPrivateKey = response.data.ocs.data.key
				this.saveFile('settings.asc', response.data.ocs.data.content)

			} catch (error) {
				showError(t('backup', 'Unable to export settings'))
				logger.error('An error occurred while exporting the settings', { error })
			} finally {
				this.loadingExportSettings = false
			}
		},

		saveFile(name, content) {
			// From: https://stackoverflow.com/questions/13405129/javascript-create-and-save-file
			const file = new Blob([content], { type: 'asc' })
			const a = document.createElement('a')
			const url = URL.createObjectURL(file)
			a.href = url
			a.download = name
			document.body.appendChild(a)
			a.click()
			setTimeout(() => {
				document.body.removeChild(a)
				window.URL.revokeObjectURL(url)
			}, 0)
		},
	},
}
</script>
<style lang="scss" scoped>
button.loading {
	color: transparent;

	&::after {
		height: 20px !important;
		width: 20px !important;
		margin: -12px 0 0 -12px !important;
	}
}

.icon-loading {
	margin-left: 28px;
}

.backup-settings {
	&__sub-headers {
		font-weight: bold;
	}

	&__summary {
		color: var(--color-text-lighter);
		list-style: inside;
	}

	&__input {
		width: 30px;
		text-align: center;
	}

	&__export__info {
		color: var(--color-error);

		code {
			display: inline-block;
			margin-top: 12px;
			padding: 8px;
			border-radius: 4px;
			user-select: all;
			background: var(--color-background-dark);
			color: var(--color-text-lighter);
		}
	}

	&__actions {
		&__action {
			margin: 16px 0;

			&__info {
				margin-bottom: 12px;
				color: var(--color-error);
			}

			&__export {
				display: block;
				margin-top: 16px;
			}
		}
	}

	&__request-modal {
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

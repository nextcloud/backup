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
		:title="t('backup', 'Restoring points locations')"
		:description="t('backup', 'Manage available storage locations for storing restoring points')">
		<form ref="external-location-form" class="external-location__form">
			<select class="external-location__form__select" name="storageId" :disabled="loadingData || availableExternalLocations.length === 0">
				<option v-for="external in availableExternalLocations"
					:key="external.storageId"
					:value="external.storageId">
					{{ external.storage }}
				</option>
			</select>
			<input class="external-location__form__input"
				type="text"
				:placeholder="t('backup', 'Path in which to store the restoring points. (ex: \"backups\")')"
				:disabled="loadingData || loadingAddExternalLocation || availableExternalLocations.length === 0"
				name="root">
			<button class="primary"
				:disabled="loadingData || availableExternalLocations.length === 0"
				:class="{loading: loadingAddExternalLocation }"
				@click.prevent="addExternalLocation">
				<span class="icon icon-add-white" />
				{{ t('backup', 'Add new external location') }}
			</button>
		</form>

		<div v-if="loadingData" class="icon-loading" />

		<table v-show="setExternalLocations.length !== 0" class="grid groups external-locations">
			<thead>
				<tr>
					<th>{{ t('backup', 'External storage') }}</th>
					<th>{{ t('backup', 'Restoring point location') }}</th>
					<th>{{ t('backup', 'Actions') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-if="loadingData && externalLocations.length === 0">
					<td colspan="3">
						<div class="icon-loading" />
					</td>
				</tr>
				<tr v-for="external in setExternalLocations"
					:key="external.storageId"
					class="external-locations__location">
					<td>{{ external.storage }}</td>
					<td>{{ external.root }}</td>
					<td>
						<button
							class="error external-locations__location__delete"
							:disabled="loadingDeleteExternalLocation !== '' && loadingDeleteExternalLocation !== external.storageId"
							:class="{loading: loadingDeleteExternalLocation === external.storageId }"
							@click="deleteExternalLocation(external)">
							<span class="icon icon-delete-white" />
							{{ t('backup', 'Delete') }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<EmptyContent v-show="externalLocations.length === 0 && !loadingData" icon="icon-external">
			{{ t('backup', 'No external storage available') }}
			<template #desc>
				{{ t('backup', 'If you want to store your restoring points on an external location, configure an external storage in the "External storage" app.') }}
			</template>
		</EmptyContent>
		<EmptyContent v-show="externalLocations.length !== 0 && setExternalLocations.length === 0" icon="icon-external">
			{{ t('backup', 'No external locations set') }}
			<template #desc>
				{{ t('backup', 'You can add a new location with the above form.') }}
			</template>
		</EmptyContent>
	</SettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

import logger from '../logger'

/**
 * @typedef {object} ExternalLocation
 * @property {string} storageId - The ID of the external storage.
 * @property {string} storage - The description of the external storage.
 * @property {string} root - The path where the restoring points will be stored.
 */

export default {
	name: 'ExternalLocationsSection',
	components: {
		SettingsSection,
		EmptyContent,
	},

	data() {
		return {
			/** @type {Array<ExternalLocation>} */
			externalLocations: [],
			loadingData: false,
			loadingAddExternalLocation: false,
			loadingDeleteExternalLocation: '',
		}
	},

	computed: {
		/** @return {HTMLFormElement} */
		externalLocationForm() {
			return this.$refs['external-location-form']
		},

		/** @return {Array<ExternalLocation>} */
		setExternalLocations() {
			return this.externalLocations.filter(external => external.root !== '')
		},
		/** @return {Array<ExternalLocation>} */
		availableExternalLocations() {
			return this.externalLocations.filter(external => external.root === '')
		},
	},

	async mounted() {
		this.fetchExternalLocations()
	},

	methods: {
		async fetchExternalLocations() {
			try {
				this.loadingData = true
				const response = await axios.get(generateOcsUrl('apps/backup/external'))
				this.externalLocations = response.data.ocs.data
				this.$emit('external-storages-change', this.externalLocations)
			} catch (error) {
				showError(t('backup', 'Unable to fetch external locations'))
				logger.error('An error occurred while fetching external location', error)
			} finally {
				this.loadingData = false
			}
		},

		/**
		 * Add a new external location based on the form.
		 */
		async addExternalLocation() {
			if (this.loadingAddExternalLocation) {
				return
			}

			try {
				this.loadingAddExternalLocation = true

				const storageId = parseInt(this.externalLocationForm.elements.storageId.value)
				const external = {
					storageId,
					root: this.externalLocationForm.elements.root.value,
				}

				if (external.root === '') {
					return
				}

				const response = await axios.post(generateOcsUrl('apps/backup/external'), external)
				this.externalLocationForm.reset()
				this.externalLocations.find(e => e.storageId === external.storageId).root = response.data.ocs.data.root
				showSuccess(t('backup', 'New external location added'))
			} catch (error) {
				showError(t('backup', 'Unable to save new external location'))
				logger.error('An error occurred while saving the settings for external location', error)
			} finally {
				this.loadingAddExternalLocation = false
			}
		},

		/**
		 * Set settings for an external location
		 *
		 * @param {ExternalLocation} external - The external location
		 */
		async deleteExternalLocation(external) {
			if (this.loadingDeleteExternalLocation !== '') {
				return
			}

			try {
				this.loadingDeleteExternalLocation = external.storageId
				await axios.delete(generateOcsUrl('apps/backup/external/{storageId}', { storageId: external.storageId }))
				external.root = ''
				showSuccess(t('backup', 'External location deleted'))
			} catch (error) {
				showError(t('backup', 'Unable to delete the external location'))
				logger.error('An error occurred while deleting an external location', error)
			} finally {
				this.loadingDeleteExternalLocation = ''
			}
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

th {
	font-weight: bold !important;
	color: var(--color-text) !important;
}

.external-locations {
	&__location {
		height: 60px;

		&__delete .icon:hover {
			background-image: var(--icon-delete-fff);
		}
	}

	td {
		padding: 12px;
	}
}

.empty-content {
	margin-top: 5vh !important;
	margin-bottom: 5vh !important;
}

.external-location__form {
	margin: 24px 0;

	&__select {
		width: 400px;
	}

	&__input {
		width: 400px;
	}
}

.icon-loading {
	height: 150px;
}
</style>

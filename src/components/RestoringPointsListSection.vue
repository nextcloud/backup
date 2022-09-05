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
	<SettingsSection :title="t('backup', 'Restoring points history')"
		:description="t('backup', 'List of the past and future restoring points')">
		<table class="grid groups">
			<thead>
				<tr>
					<th class="center-text">
						{{ t('backup', 'Issue') }}
					</th>
					<th class="center-text">
						{{ t('backup', 'Health') }}
					</th>
					<th class="center-text">
						{{ t('backup', 'Status') }}
					</th>
					<th>{{ t('backup', 'Date') }}</th>
					<th>{{ t('backup', 'ID') }}</th>
				</tr>
			</thead>

			<tbody class="restoring-points">
				<tr v-if="loading && allRestoringPoints.length === 0">
					<td colspan="3">
						<div class="icon-loading" />
					</td>
				</tr>

				<tr v-for="point in allRestoringPoints"
					:key="point.id"
					class="restoring-points__point"
					:class="{'restoring-points__point--pending': point.health === -2}">
					<td class="restoring-points__point__icons">
						<Popover v-if="point.health !== -2">
							<template #trigger>
								<Check v-if="point.issue === ''"
									slot="icon"
									fill-color="#46ba61"
									:title="t('backup', 'No issue')" />
								<AlertCircle v-else-if="point.issue !== ''"
									slot="icon"
									fill-color="#e9322d"
									:title="point.issue" />
							</template>
							<div class="restoring-points__point__popover">
								<div v-if="point.local !== undefined" class="restoring-points__point__popover__item">
									<Check v-if="point.local.issue === ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="#46ba61"
										:title="t('backup', 'No issue')" />
									<AlertCircle v-else-if="point.local.issue !== ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="#e9322d"
										:title="point.local.issue" />
									{{ t('backup', 'Local') }}
								</div>
								<div v-for="external of point.externals"
									:key="external.storageId"
									class="restoring-points__point__popover__item">
									<Check v-if="external.issue === ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="#46ba61"
										:title="t('backup', 'No issue')" />
									<AlertCircle v-else-if="external.issue !== ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="#e9322d"
										:title="external.issue" />
									<template v-if="externalStorages.length !== 0">
										{{ getStorageNameFromStorageId(external.storageId) }}
									</template>

									<template v-if="external.issue !== ''">
										- ({{ external.issue }})
									</template>
								</div>
							</div>
						</Popover>
					</td>

					<td class="restoring-points__point__icons">
						<Popover :disabled="point.health < 0">
							<template #trigger>
								<div class="restoring-points__point__icons__health">
									<RestoringPointHealthIcon :health="point.health" />
								</div>
							</template>
							<div class="restoring-points__point__popover">
								<div v-if="point.local !== undefined && point.local.point.health !== undefined" class="restoring-points__point__popover__item">
									<RestoringPointHealthIcon :health="point.local.point.health.status" class="restoring-points__point__popover__item__icon" />
									{{ t('backup', 'Local') }}
								</div>
								<div v-for="external of point.externals"
									:key="external.storageId"
									class="restoring-points__point__popover__item">
									<RestoringPointHealthIcon :health="external.point.health.status" class="restoring-points__point__popover__item__icon" />
									<template v-if="externalStorages.length !== 0">
										{{ getStorageNameFromStorageId(external.storageId) }} -
									</template>
									(
									<template v-if="external.point.health.status === -2">
										{{ t('backup', 'Scheduled') }}
									</template>
									<template v-else-if="external.point.health.status === -1">
										{{ t('backup', 'Pending') }}
									</template>
									<template v-else-if="external.point.health.status === 0">
										{{ t('backup', 'Not completed') }}
									</template>
									<template v-else-if="external.point.health.status === 1">
										{{ t('backup', 'Orphan') }}
									</template>
									<template v-else-if="external.point.health.status === 9">
										{{ t('backup', 'Completed') }}
									</template>
									)
								</div>
							</div>
						</Popover>
					</td>

					<td class="restoring-points__point__icons">
						<RestoringPointStatusIcon v-if="point.health !== -2" :status="point.status" />
					</td>

					<td>
						<span v-tooltip.bottom="point.dateFormatted" class="activity-entry__date">{{ point.dateFromNow }}</span>
					</td>

					<td>
						<template v-if="point.id === 'next_full_restoring_point'">
							{{ t('backup', 'Next full restoring point') }}
						</template>
						<template v-else-if="point.id === 'next_partial_restoring_point'">
							{{ t('backup', 'Next partial restoring point') }}
						</template>
						<code v-else>
							{{ point.id }}
						</code>
					</td>
				</tr>
			</tbody>
		</table>
	</SettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

import Check from 'vue-material-design-icons/Check.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'

import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import Popover from '@nextcloud/vue/dist/Components/Popover'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

import RestoringPointHealthIcon from './RestoringPointHealthIcon.vue'
import RestoringPointStatusIcon from './RestoringPointStatusIcon.vue'
import SettingsModel from '../models/SettingsModel.js'
import RestoringPoint from '../models/RestoringPointModel.js'
import logger from '../logger'

/**
 * @typedef {object} ExternalLocation
 * @property {number} storageId - The ID of the external storage.
 * @property {string} storage - The description of the external storage.
 * @property {string} root - The path where the restoring points will be stored.
 */

export default {
	name: 'RestoringPointsListSection',
	components: {
		RestoringPointHealthIcon,
		RestoringPointStatusIcon,
		SettingsSection,
		Popover,
		Check,
		AlertCircle,
	},
	directives: {
		tooltip: Tooltip,
	},

	props: {
		generalSettings: SettingsModel,
		externalStorages: {
			type: Array,
			default: () => [],
		},

	},

	data() {
		return {
			/** @type {Array<RestoringPoint>} */
			restoringPoints: [],
			loading: false,
		}
	},

	computed: {
		/**
		 * The next full restoring point wrapped into a RestoringPoint object
		 *
		 * @return {Array<RestoringPoint>}
		 */
		nextFullRestoringPoint() {
			if (this.generalSettings === undefined || this.generalSettings.nextFullRestoringPointTimestamp === 0) {
				return []
			}

			return [
				new RestoringPoint({
					id: 'next_full_restoring_point',
					local: { point: { date: this.generalSettings.nextFullRestoringPointTimestamp } },
					externals: [],
				}),
			]
		},

		/**
		 * The next partial restoring point wrapped into a RestoringPoint object
		 *
		 * @return {Array<RestoringPoint>}
		 */
		nextPartialRestoringPoint() {
			if (this.generalSettings === undefined || this.generalSettings.nextPartialRestoringPointTimestamp === 0) {
				return []
			}

			return [new RestoringPoint({
				id: 'next_partial_restoring_point',
				local: { point: { date: this.generalSettings.nextPartialRestoringPointTimestamp } },
				externals: [],
			})]
		},

		/**
		 * The list of fetched restoring points + the next full and partial restoring points.
		 *
		 * @return {Array<RestoringPoint>}
		 */
		allRestoringPoints() {
			return [
				...this.nextFullRestoringPoint,
				...this.nextPartialRestoringPoint,
				...this.restoringPoints,
			]
		},
	},

	async mounted() {
		this.fetchRestoringPoints()
	},

	methods: {
		async fetchRestoringPoints() {
			try {
				this.loading = true
				const response = await axios.get(generateOcsUrl('apps/backup/rp'))
				this.restoringPoints = this.handleRestoringPoints(response.data.ocs.data)
			} catch (error) {
				showError(t('backup', 'Unable to fetch restoring points'))
				logger.error('An error occurred while fetching restoring points', { error })
			} finally {
				this.loading = false
			}
		},

		/**
		 * @typedef {object} Point
		 * @property {string} id - The id of the point
		 * @property {number} status - The status of the point
		 * @property {number} date - The creation date of the point
		 * @property {string} parent - The parent point of the point
		 * @property {number} duration - The duration of the creation of the point
		 */

		/**
		 * @typedef {object} PointLocation
		 * @property {string} issue - The issue of the point if any, else ''
		 * @property {Point} point - The point
		 */

		/**
		 * @typedef {object<string, PointLocation>} RestoringPoints
		 */

		/**
		 * @param {object<string, RestoringPoints>} restoringPoints - The list of restoring points
		 */
		handleRestoringPoints(restoringPoints) {
			return Object.keys(restoringPoints)
				.map(restoringPointId => {
					const restoringPoint = restoringPoints[restoringPointId]
					return {
						id: restoringPointId,
						local: restoringPoint.local,
						externals: Object.keys(restoringPoint).filter(storageId => storageId !== 'local').map(storageId => ({ ...restoringPoint[storageId], storageId })),
					}
				})
				.map(point => new RestoringPoint(point))
				.sort((point1, point2) => point2.dateTimestamp - point1.dateTimestamp)
		},

		/**
		 * @param {string} storageId - The storage ID in the following format: 'external:<storageId>'
		 */
		getStorageNameFromStorageId(storageId) {
			if (this.externalStorages === undefined) {
				return storageId
			}

			// The variable assignment is useful to have type completion.
			/** @type {Array<ExternalLocation>} */
			const externalStorages = this.externalStorages
			return externalStorages.find(storage => storage.storageId === parseInt(storageId.split(':')[1])).storage
		},

	},
}
</script>
<style lang="scss" scoped>
th {
	font-weight: bold !important;
	color: var(--color-text) !important;

	&.center-text {
		text-align: center !important;
	}
}

.restoring-points {

	&__point {
		&--pending {
			color: var(--color-text-lighter);
		}

		&__icons {
			text-align: center;

			&__health {
				cursor: pointer;
			}
		}

		&__popover {
			display: flex;
			flex-direction: column;
			padding: 12px;

			&__item {
				display: flex;

				&__icon {
					margin-right: 8px;
				}
			}
		}
	}

	td {
		padding: 12px;
	}
}
</style>

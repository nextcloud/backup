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
		:title="t('backup', 'Restoring points history')"
		:description="t('backup', 'List of the past and futur restoring points')">
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
								<Check
									v-if="point.issue === ''"
									slot="icon"
									fill-color="green"
									:title="t('backup', 'No issue')" />
								<AlertCircle
									v-else-if="point.issue !== ''"
									slot="icon"
									fill-color="red"
									:title="point.issue" />
							</template>
							<div class="restoring-points__point__popover">
								<div class="restoring-points__point__popover__item">
									<Check
										v-if="point.local.issue === ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="green"
										:title="t('backup', 'No issue')" />
									<AlertCircle
										v-else-if="point.local.issue !== ''"
										slot="icon"
										class="restoring-points__point__popover__item__icon"
										fill-color="red"
										:title="point.local.issue" />
									local
								</div>
								<template
									v-if="point.external !== undefined">
									<div v-for="external of point.externals"
										:key="external.storageId"
										class="restoring-points__point__popover__item">
										{{ external }}{{ external.storageId }}
										<Check
											v-if="external.issue === ''"
											slot="icon"
											class="restoring-points__point__popover__item__icon"
											fill-color="green"
											:title="t('backup', 'No issue')" />
										<AlertCircle
											v-else-if="external.issue !== ''"
											slot="icon"
											class="restoring-points__point__popover__item__icon"
											fill-color="red"
											:title="external.issue" />
									</div>
								</template>
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
							<div v-if="point.health >= 0" class="restoring-points__point__popover">
								<div class="restoring-points__point__popover__item">
									<RestoringPointHealthIcon :health="point.local.point.health.status" class="restoring-points__point__popover__item__icon" />
									{{ t('backup', 'local') }}
								</div>
								<template v-if="point.external !== undefined">
									<div v-for="external of point.externals"
										:key="external.storageId"
										class="restoring-points__point__popover__item">
										<RestoringPointHealthIcon :health="external.point.health.status" class="restoring-points__point__popover__item__icon" />
										{{ external.storageId }}
									</div>
								</template>
							</div>
						</Popover>
					</td>
					<td class="restoring-points__point__icons">
						<RestoringPointStatusIcon v-if="point.health !== -2" :status="point.local.point.status" />
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
import moment from '@nextcloud/moment'

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
	filters: {
		/**
		 * @param {number} timestamp - The timestamp to pretty print.
		 */
		dateFromNow(timestamp) {
			return moment(timestamp).fromNow()

		},

		/**
		 * @param {number} timestamp - The timestamp to pretty print.
		 */
		formatDate(timestamp) {
			return moment(timestamp).format('LLL')

		},
	},

	props: {
		generalSettings: SettingsModel,
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
				logger.error('An error occurred while fetching restoring points', error)
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
		 * @typedef {object} RestoringPoints
		 * @property {PointLocation} local - The local location
		 * @property {Array<PointLocation>} externals - The external locations
		 */

		/**
		 * @param {RestoringPoints} restoringPoints - The list of restoring points
		 */
		handleRestoringPoints(restoringPoints) {
			return Object.values(restoringPoints)
				.map(pointLocations => {
					return {
						id: pointLocations.local.point.id,
						local: pointLocations.local,
						external: pointLocations.external,
					}
				})
				.map(point => new RestoringPoint(point))
				.sort((point1, point2) => point2.dateTimestamp - point1.dateTimestamp)
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

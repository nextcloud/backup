/**
 * @copyright Copyright (c) 2021 Louis Chemineau <louis@chmn.me>
 *
 * @author Louis Chemineau <louis@chmn.me>
 *
 * @license GPL-3.0-or-later
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import moment from '@nextcloud/moment'

export default class RestoringPointModel {

	_restoringPoint

	/**
	 * Create the restoringPoint object.
	 *
	 * @param {object} rawRestoringPoint the restoringPoint object from the ocs response
	 */
	constructor(rawRestoringPoint) {
		if (typeof rawRestoringPoint !== 'object') {
			throw new Error('Received restoring point is not an object.')
		}

		// Sanity checks
		if (typeof rawRestoringPoint.id !== 'string') {
			throw new Error('The id property is not a valid string')
		}

		// store state
		this._restoringPoint = rawRestoringPoint
	}

	/**
	 * Get the id of the restoring point.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get id() {
		return this._restoringPoint.id
	}

	/**
	 * Get whether the restoring point is a full one or not.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get isFull() {
		return this.local.point.parent === ''
	}

	/**
	 * Get the parent of the restoring point if any, else ''.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get parent() {
		return this.local.point.parent
	}

	/**
	 * Get the parent of the restoring point if any, else ''.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get issue() {
		if (this._restoringPoint.local !== undefined && this._restoringPoint.local.issue !== '') {
			return this._restoringPoint.local.issue
		} else if (this.externals.length !== 0) {
			const external = this.externals.find(external => external.issue !== '')
			if (external !== undefined) {
				return external.issue
			}
		}

		return ''
	}

	/**
	 * Get the health of the restoring point.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get health() {
		if (this.local.point.health !== undefined) {
			return this.local.point.health.status
		} else if (this.externals.length !== 0) {
			const external = this.externals.find(external => external.issue !== '')
			if (external !== undefined) {
				return external.point.health.status
			}
		} else if (this.dateTimestamp > Date.now() / 1000) {
			return -2 // Scheduled
		}

		return -1 // Pending
	}

	/**
	 * @typedef {object} RestoringLocation
	 * @property {string} storageId - The storageId of the location
	 * @property {string} issue - The issue of the restoring point if any, else ''.
	 * @property {object} point - The information about the restoring point.
	 * @property {object} point.health - The health of the restoring location.
	 * @property {number} point.health.checked - The date of the last health check.
	 * @property {number} point.health.status - The health status.
	 * @property {number} point.status - The status of the restoring location.
	 * @property {number} point.date - When did the restoring point was created.
	 * @property {number} point.duration - The duration of the maintenance window.
	 * @property {string} point.parent - The parent of the restoring point.
	 * @property {boolean} point.archive - Whether the restoring point is an archive or not.
	 */

	/**
	 * Get the information about the local location.
	 *
	 * @return {RestoringLocation}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get local() {
		return this._restoringPoint.local
	}

	/**
	 * Get the information about the external locations.
	 *
	 * @return {object.<string, RestoringLocation>}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get external() {
		if (this._restoringPoint.external !== undefined) {
			return this._restoringPoint.external
		} else {
			return {}
		}
	}

	/**
	 * Get the information about the external locations.
	 *
	 * @return {Array<RestoringLocation>}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get externals() {
		return Object.keys(this.external)
			.map(storageId => {
				return { ...this.external[storageId], storageId }
			})
	}

	/**
	 * Get the timestamp of the restoring point.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get dateTimestamp() {
		return this.local.point.date
	}

	/**
	 * Get the formatted date from now of the restoring point.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get dateFromNow() {
		return moment(this.dateTimestamp * 1000).fromNow()
	}

	/**
	 * Get the formatted date of the restoring point.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof RestoringPointModel
	 */
	get dateFormatted() {
		return moment(this.dateTimestamp * 1000).format('LLL')
	}

}

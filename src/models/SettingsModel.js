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

export default class SettingsModel {

	_settings
	_timeSlots

	/**
	 * Create the settings object.
	 *
	 * @param {object} rawSettings the settings object from the ocs response
	 */
	constructor(rawSettings) {
		if (typeof rawSettings !== 'object') {
			throw new Error('Received settings is not an object.')
		}

		// Sanity checks
		if (typeof rawSettings.date_full_rp !== 'number') {
			throw new Error('The date_full_rp property is not a valid number')
		}
		if (typeof rawSettings.date_partial_rp !== 'number') {
			throw new Error('The date_partial_rp property is not a valid number')
		}
		if (typeof rawSettings.time_slots !== 'string') {
			throw new Error('The time_slots property is not a valid string')
		}
		this._timeSlots = rawSettings.time_slots.split('-').map(num => parseInt(num))
		if (this._timeSlots.length !== 2) {
			throw new Error('The time_slots format is not valid')
		}
		if (typeof this._timeSlots[0] !== 'number') {
			throw new Error('The time_slots left part is not valid')
		}
		if (typeof this._timeSlots[0] !== 'number') {
			throw new Error('The time_slots right part is not valid')
		}
		this._timeSlots = {
			start: this._timeSlots[0],
			end: this._timeSlots[1],
		}
		if (typeof rawSettings.delay_full_rp !== 'number') {
			throw new Error('The delay_full_rp property is not a valid number')
		}
		if (typeof rawSettings.delay_partial_rp !== 'number') {
			throw new Error('The delay_partial_rp property is not a valid number')
		}
		if (typeof rawSettings.allow_weekday !== 'boolean') {
			throw new Error('The allow_weekday property is not a valid boolean')
		}
		if (typeof rawSettings.pack_backup !== 'boolean') {
			throw new Error('The pack_backup property is not a valid boolean')
		}
		if (typeof rawSettings.pack_compress !== 'boolean') {
			throw new Error('The pack_compress property is not a valid boolean')
		}
		if (typeof rawSettings.pack_encrypt !== 'boolean') {
			throw new Error('The pack_encrypt property is not a valid boolean')
		}
		if (typeof rawSettings.mockup_date !== 'number') {
			throw new Error('The mockup_date property is not a valid number')
		}
		if (typeof rawSettings.partial !== 'number') {
			throw new Error('The partial property is not a valid number')
		}
		if (typeof rawSettings.full !== 'number') {
			throw new Error('The full property is not a valid number')
		}
		if (typeof rawSettings.store_items !== 'number') {
			throw new Error('The store_items property is not a valid number')
		}
		if (typeof rawSettings.store_items_external !== 'number') {
			throw new Error('The store_items_external property is not a valid number')
		}

		// store state
		this._settings = rawSettings
	}

	get settings() {
		return this._settings
	}

	/**
	 * Get the timestamp of the last full backup.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastFullRestoringPointTimestamp() {
		return this._settings.date_full_rp
	}

	/**
	 * Get the formatted date from now of the last full backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastFullRestoringPointFromNow() {
		return moment(this._settings.date_full_rp * 1000).fromNow()
	}

	/**
	 * Get the formatted date of the last full backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastFullRestoringPointFormattedDate() {
		return moment(this._settings.date_full_rp * 1000).format('LLL')
	}

	/**
	 * Get the timestamp of the last partial backup.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastPartialRestoringPointTimestamp() {
		return this._settings.date_partial_rp
	}

	/**
	 * Get the formatted date from now of the last partial backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastPartialRestoringPointFromNow() {
		return moment(this._settings.date_partial_rp * 1000).fromNow()
	}

	/**
	 * Get the formatted date of the last partial backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get lastPartialRestoringPointFormattedDate() {
		return moment(this._settings.date_partial_rp * 1000).format('LLL')
	}

	/**
	 * Get the delay between two full restoring points.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get delayFullRestoringPoint() {
		return this._settings.delay_full_rp
	}

	/**
	 * Set the delay between two full restoring points.
	 *
	 * @param {number} value - The new value.
	 * @readonly
	 * @memberof SettingsModel
	 */
	set delayFullRestoringPoint(value) {
		// Default to 14 days if value is not an integer
		value = Number.isInteger(value) ? value : 14
		this._settings.delay_full_rp = value
	}

	/**
	 * Get the delay between two partial restoring points.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get delayPartialRestoringPoint() {
		return this._settings.delay_partial_rp
	}

	/**
	 * Set the delay between two partial restoring points.
	 *
	 * @param {number} value - The new value.
	 * @readonly
	 * @memberof SettingsModel
	 */
	set delayPartialRestoringPoint(value) {
		// Default to 3 days if value is not an integer
		value = Number.isInteger(value) ? value : 3
		this._settings.delay_partial_rp = value
	}

	/**
	 * Get the wether the backup can be ade during week days.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get allowWeekdays() {
		return this._settings.allow_weekday
	}

	/**
	 * Set the wether the backup can be ade during week days.
	 *
	 * @param value {boolean}
	 * @memberof SettingsModel
	 */

	set allowWeekdays(value) {
		this._settings.allow_weekday = value
	}

	/**
	 * Get wether restore point packing is enabled.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get packBackup() {
		return this._settings.pack_backup
	}

	/**
	 * Set wether restore point packing is enabled.
	 *
	 * @param value {boolean}
	 * @memberof SettingsModel
	 */

	set packBackup(value) {
		this._settings.pack_backup = value
	}

	/**
	 * Get wether compression is enabled.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get packCompress() {
		return this._settings.pack_compress
	}

	/**
	 * Set wether compression is enabled.
	 *
	 * @param value {boolean}
	 * @memberof SettingsModel
	 */

	set packCompress(value) {
		this._settings.pack_compress = value
	}

	/**
	 * Get wether encryption is enabled.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get packEncrypt() {
		return this._settings.pack_encrypt
	}

	/**
	 * Set wether encryption is enabled.
	 *
	 * @param value {boolean}
	 * @memberof SettingsModel
	 */

	set packEncrypt(value) {
		this._settings.pack_encrypt = value
	}

	/**
	 * Get wether the user asked to force to create a restoring point.
	 *
	 * @return {boolean}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get restoringPointRequested() {
		return this._settings.mockup_date !== 0
	}

	/**
	 * Get the timestamp of the next full restoring point.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextFullRestoringPointTimestamp() {
		return this._settings.full
	}

	/**
	 * Get the formatted date from now of the next full restoring point.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextFullRestoringPointFromNow() {
		return moment(this._settings.full * 1000).fromNow()
	}

	/**
	 * Get the formatted date of the next full restoring point.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextFullRestoringPointFormatted() {
		return moment(this._settings.full * 1000).format('LLL')
	}

	/**
	 * Get the timestamp of the next partial backup.
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextPartialRestoringPointTimestamp() {
		return this._settings.partial
	}

	/**
	 * Get the formatted date from now of the next partial backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextPartialRestoringPointFromNow() {
		return moment(this._settings.partial * 1000).fromNow()
	}

	/**
	 * Get the formatted date of the next partial backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get nextPartialRestoringPointFormatted() {
		return moment(this._settings.partial * 1000).format('LLL')
	}

	/**
	 * Get the start of the allowed time slots to do a backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get timeSlotsStart() {
		return (this._timeSlots.start < 10 ? '0' : '') + this._timeSlots.start
	}

	/**
	 * Set the start of the allowed time slots to do a backup.
	 *
	 * @param {string} value - The new value.
	 * @memberof SettingsModel
	 */
	set timeSlotsStart(value) {
		this._timeSlots.start = value
		this._settings.time_slots = `${this._timeSlots.start}-${this._timeSlots.end}`
	}

	/**
	 * Get the end of the allowed time slots to do a backup.
	 *
	 * @return {string}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get timeSlotsEnd() {
		return (this._timeSlots.end < 10 ? '0' : '') + this._timeSlots.end
	}

	/**
	 * Set the end of the allowed time slots to do a backup.
	 *
	 * @param {string} value - The new value.
	 * @memberof SettingsModel
	 */
	set timeSlotsEnd(value) {
		this._timeSlots.end = value
		this._settings.time_slots = `${this._timeSlots.start}-${this._timeSlots.end}`
	}

	/**
	 * Get the number of restoring point to keep in the app data during a purge
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get storeItems() {
		return this._settings.store_items
	}

	/**
	 * Set the number of restoring point to keep in the app data during a purge
	 *
	 * @param {number} value - The new value.
	 * @memberof SettingsModel
	 */
	set storeItems(value) {
		this._settings.store_items = value
	}

	/**
	 * Get the number of restoring point to keep in external storages during a purge
	 *
	 * @return {number}
	 * @readonly
	 * @memberof SettingsModel
	 */
	get storeItemsExternal() {
		return this._settings.store_items_external
	}

	/**
	 * Set the number of restoring point to keep in external storages during a purge
	 *
	 * @param {number} value - The new value.
	 * @memberof SettingsModel
	 */
	set storeItemsExternal(value) {
		this._settings.store_items_external = value
	}

}

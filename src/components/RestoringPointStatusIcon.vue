<template>
	<span class="icon-container">
		<PackageVariant
			v-if="!isPacked"
			slot="icon"
			fill-color="#eca700"
			:title="t('backup', 'Not packed yet')" />
		<PackageVariantClosed
			v-if="isPacked"
			slot="icon"
			fill-color="gray"
			:title="t('backup', 'Packed')" />
		<Lock
			v-if="isEncrypted"
			slot="icon"
			fill-color="gray"
			:title="t('backup', 'Encrypted')" />
		<FolderZip
			v-if="isCompressed"
			slot="icon"
			fill-color="gray"
			:title="t('backup', 'Compressed')" />
	</span>
</template>

<script>
import PackageVariant from 'vue-material-design-icons/PackageVariant.vue'
import PackageVariantClosed from 'vue-material-design-icons/PackageVariantClosed.vue'
import FolderZip from 'vue-material-design-icons/FolderZip.vue'
import Lock from 'vue-material-design-icons/Lock.vue'

const StatusEnum = {
	STATUS_UNPACKED: 0,
	STATUS_PACKED: 1,
	STATUS_COMPRESSED: 2,
	STATUS_ENCRYPTED: 4,
}

export default {
	name: 'RestoringPointStatusIcon',
	components: {
		PackageVariant,
		PackageVariantClosed,
		FolderZip,
		Lock,
	},

	props: {
		status: {
			type: Number,
			required: true,
		},
	},

	computed: {
		/**
		 * Whether or not the restoring point is encrypted.
		 *
		 * @return {boolean}
		 */
		hasIssue() {
			return this.status === 32
		},

		/**
		 * Whether or not the restoring point is packed.
		 *
		 * @return {boolean}
		 */
		isPacked() {
			return (this.status & StatusEnum.STATUS_PACKED) !== 0
		},

		/**
		 * Whether or not the restoring point is compressed.
		 *
		 * @return {boolean}
		 */
		isCompressed() {
			return (this.status & StatusEnum.STATUS_COMPRESSED) !== 0
		},

		/**
		 * Whether or not the restoring point is encrypted.
		 *
		 * @return {boolean}
		 */
		isEncrypted() {
			return (this.status & StatusEnum.STATUS_ENCRYPTED) !== 0
		},

	},
}
</script>
<style lang="scss" scoped>
.icon-container {
	display: flex;
}
</style>

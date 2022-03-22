<template>
	<ClipboardClock v-if="health === -2"
		slot="icon"
		fill-color="#eca700"
		:title="t('backup', 'Scheduled')" />
	<ClockOutline v-else-if="health === -1"
		slot="icon"
		fill-color="#eca700"
		:title="t('backup', 'Pending')" />
	<AlertCircle v-else-if="health === 0"
		slot="icon"
		fill-color="#e9322d"
		:title="t('backup', 'Not completed')" />
	<AlertCircle v-else-if="health === 1"
		slot="icon"
		fill-color="#eca700"
		:title="t('backup', 'Orphan')" />
	<Check v-else-if="health === 9"
		slot="icon"
		fill-color="#46ba61"
		:title="t('backup', 'Completed')" />
</template>

<script>
import ClipboardClock from 'vue-material-design-icons/ClipboardClock.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'

export default {
	name: 'RestoringPointHealthIcon',
	components: {
		ClipboardClock,
		ClockOutline,
		AlertCircle,
		Check,
	},

	props: {
		health: {
			type: Number,
			required: true,
		},
	},
}
</script>

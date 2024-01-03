const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

webpackRules.RULE_RAW_SVGS = {
	test: /\.svg$/,
	type: 'asset/source',
}

webpackConfig.module.rules = Object.values(webpackRules)

webpackConfig.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	filesAction: path.join(__dirname, 'src', 'filesAction.js'),
}

module.exports = webpackConfig

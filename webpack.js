const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
}

module.exports = webpackConfig

/**
 * Webpack Configuration
 *
 * Custom configuration to ensure React and ReactDOM are not bundled
 * and instead use WordPress's provided versions.
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    externals: {
        'react': 'React',
        'react-dom': 'ReactDOM',
        '@wordpress/element': 'wp.element'
    }
};



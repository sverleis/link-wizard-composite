<?php
/**
 * Plugin Name: Link Wizard for Composites
 * Plugin URI: https://github.com/sverleis/link-wizard-composite
 * Description: Adds support for WooCommerce Composite Products to Link Wizard, enabling custom checkout-links with component selections and quantities.
 * Version: 1.0.0
 * Author: Sven Leisegang
 * Author URI: https://github.com/sverleis
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: link-wizard-composite
 * Requires at least: 6.2
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Requires Plugins: link-wizard-for-woocommerce, woocommerce
 *
 * @package Link_Wizard_Composite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'LWWC_COMPOSITE_VERSION', '1.0.0' );
define( 'LWWC_COMPOSITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LWWC_COMPOSITE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Initialize the plugin.
 */
function lwwc_composite_init() {
	// Check if Link Wizard for WooCommerce is active.
	if ( ! class_exists( 'LWWC_Link_Wizard' ) ) {
		add_action( 'admin_notices', 'lwwc_composite_missing_dependency_notice' );
		return;
	}

	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'lwwc_composite_missing_woocommerce_notice' );
		return;
	}

	// Check if WooCommerce Composite Products is active.
	if ( ! class_exists( 'WC_Composite_Products' ) ) {
		add_action( 'admin_notices', 'lwwc_composite_missing_composite_notice' );
		return;
	}

	// All dependencies met - initialize the plugin.
	require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-handler.php';
	
	$handler = new LWWC_Composite_Handler();
	$handler->init();
}
add_action( 'plugins_loaded', 'lwwc_composite_init' );

/**
 * Display notice if Link Wizard is not active.
 */
function lwwc_composite_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Link Wizard for Composites', 'link-wizard-composite' ); ?></strong>
			<?php esc_html_e( 'requires Link Wizard for WooCommerce to be installed and activated.', 'link-wizard-composite' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Display notice if WooCommerce is not active.
 */
function lwwc_composite_missing_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Link Wizard for Composites', 'link-wizard-composite' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'link-wizard-composite' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Display notice if WooCommerce Composite Products is not active.
 */
function lwwc_composite_missing_composite_notice() {
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Link Wizard for Composites', 'link-wizard-composite' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce Composite Products to be installed and activated to enable composite product checkout links.', 'link-wizard-composite' ); ?>
		</p>
	</div>
	<?php
}


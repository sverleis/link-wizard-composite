<?php
/**
 * Composite Products Handler.
 *
 * This is the main handler class that manages all composite product functionality.
 * It acts as the central coordinator for the plugin.
 *
 * @package Link_Wizard_Composite
 * @subpackage Link_Wizard_Composite/includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main handler class for composite products.
 *
 * This class:
 * - Registers the plugin with Link Wizard's hook system.
 * - Manages initialization of all composite product features.
 * - Coordinates between different components (URL mapper, product handler, etc.).
 */
class LWWC_Composite_Handler {

	/**
	 * Initialize the handler.
	 *
	 * This is called from the main plugin file after all dependencies are confirmed.
	 */
	public function init() {
		// Log initialization for debugging.
		error_log( 'Link Wizard Composite: Handler initialized' );

		// Register this plugin with Link Wizard's addon system.
		add_filter( 'lwwc_addon_capabilities', array( $this, 'register_capabilities' ), 10, 2 );

		// TODO: Initialize URL mapper (Step 3).
		// TODO: Register REST API endpoints (Step 5).
		// TODO: Enqueue admin assets (Step 6).
	}

	/**
	 * Register plugin capabilities with Link Wizard.
	 *
	 * This tells Link Wizard what this plugin can do.
	 *
	 * @param array  $capabilities Existing capabilities from other addons.
	 * @param string $plugin_slug  The plugin slug being checked.
	 * @return array Updated capabilities.
	 */
	public function register_capabilities( $capabilities, $plugin_slug ) {
		// Only respond if Link Wizard is asking about our plugin.
		if ( 'link-wizard-composite/link-wizard-composite.php' !== $plugin_slug ) {
			return $capabilities;
		}

		// Tell Link Wizard what we can do.
		return array(
			'product_types' => array( 'composite' ), // We handle composite products.
			'features'      => array(
				'checkout_links',     // We enable checkout-links for composites.
				'custom_components',  // We support custom component selections.
				'price_calculation',  // We can calculate composite prices.
			),
		);
	}
}


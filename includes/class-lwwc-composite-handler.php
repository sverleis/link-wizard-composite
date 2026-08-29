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
		// Register this plugin with Link Wizard's addon system.
		add_filter( 'lwwc_addon_capabilities', array( $this, 'register_capabilities' ), 10, 2 );
		add_filter( 'lwwc_addon_icon', array( $this, 'register_icon' ), 10, 2 );

		// Register our product handler with Link Wizard's handler manager.
		add_action( 'lwwc_after_product_handlers_loaded', array( $this, 'register_product_handler' ) );

		// Initialize URL mapper (Step 3).
		$this->init_url_mapper();

		// Initialize REST API endpoints (Step 5).
		$this->init_rest_api();

		// Enqueue admin assets (Step 6).
		$this->init_admin_assets();
	}

	/**
	 * Initialize the URL mapper.
	 *
	 * Creates the URL mapper instance and initializes it.
	 * This is called during plugin initialization.
	 */
	private function init_url_mapper() {
		require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-url-mapper.php';
		
		$url_mapper = new LWWC_Composite_URL_Mapper();
		$url_mapper->init();

	}

	/**
	 * Initialize the REST API.
	 *
	 * Creates the REST API handler and initializes endpoints.
	 * This is called during plugin initialization.
	 */
	private function init_rest_api() {
		require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-rest-api.php';
		
		$rest_api = new LWWC_Composite_REST_API();
		$rest_api->init();

	}

	/**
	 * Initialize the admin assets handler.
	 *
	 * Loads and initializes the admin assets class for enqueueing scripts.
	 * This is called during plugin initialization.
	 */
	private function init_admin_assets() {
		require_once LWWC_COMPOSITE_PATH . 'admin/class-lwwc-composite-admin.php';
		
		$admin = new LWWC_Composite_Admin();
		$admin->init();

	}

	/**
	 * Register our product handler with Link Wizard's handler manager.
	 *
	 * This allows composite products to appear in search results and be handled properly.
	 *
	 * @param LWWC_Product_Handler_Manager $handler_manager The handler manager instance.
	 */
	public function register_product_handler( $handler_manager ) {
		// Load our composite product handler.
		require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-product-handler.php';
		
		// Create and register the handler.
		$composite_handler = new LWWC_Composite_Product_Handler();
		$handler_manager->register_handler( $composite_handler );

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
		if ( ! in_array( $plugin_slug, array( 'link-wizard-composite', 'link-wizard-composite/link-wizard-composite.php' ), true ) ) {
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

	/**
	 * Register the icon shown in the add-on card.
	 *
	 * @param string $icon        Current icon.
	 * @param string $plugin_slug Add-on directory slug.
	 * @return string Filtered icon.
	 */
	public function register_icon( $icon, $plugin_slug ) {
		if ( 'link-wizard-composite' !== $plugin_slug ) {
			return $icon;
		}

		return '🧩';
	}
}
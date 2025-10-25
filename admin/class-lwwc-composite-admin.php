<?php
/**
 * Admin Assets Handler for Link Wizard Composite.
 *
 * Handles enqueueing of admin JavaScript and CSS files.
 *
 * @package Link_Wizard_Composite
 * @subpackage Link_Wizard_Composite/admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Composite Admin Assets class.
 *
 * Enqueues the CompositeProductConfig React component
 * on Link Wizard admin pages.
 */
class LWWC_Composite_Admin {

	/**
	 * Initialize the admin assets.
	 */
	public function init() {
		// Enqueue admin scripts on Link Wizard pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		error_log( 'Link Wizard for Composites: Admin assets handler initialized' );
	}

	/**
	 * Enqueue admin assets on Link Wizard pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on Link Wizard admin page.
		// Check if we're on the Link Wizard page.
		if ( ! $this->is_link_wizard_page( $hook ) ) {
			return;
		}

		// Enqueue integration script first (plain JavaScript, no dependencies).
		wp_enqueue_script(
			'lwwc-composite-integration',
			LWWC_COMPOSITE_URL . 'admin/js/composite-integration.js',
			array(), // No dependencies - loads immediately.
			LWWC_COMPOSITE_VERSION,
			false // Load in head so it's available early.
		);

		// Enqueue our React component.
		$asset_file = LWWC_COMPOSITE_PATH . 'admin/build/composite-admin.asset.php';
		
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			
			wp_enqueue_script(
				'lwwc-composite-admin',
				LWWC_COMPOSITE_URL . 'admin/build/composite-admin.js',
				$asset['dependencies'], // Use only the dependencies from the asset file.
				$asset['version'],
				true
			);

			// Localize script with any needed data.
			wp_localize_script(
				'lwwc-composite-admin',
				'lwwcCompositeSettings',
				array(
					'apiUrl'   => rest_url( 'lwwc-composite/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'pluginUrl' => LWWC_COMPOSITE_URL,
				)
			);

			error_log( 'Link Wizard for Composites: Admin assets enqueued' );
		}
	}

	/**
	 * Check if we're on a Link Wizard admin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool True if on Link Wizard page.
	 */
	private function is_link_wizard_page( $hook ) {
		// Link Wizard uses admin.php?page=link-wizard-for-woocommerce
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'link-wizard-for-woocommerce' ) {
			return true;
		}

		return false;
	}
}


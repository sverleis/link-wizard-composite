<?php
/**
 * Composite Product URL Mapper.
 *
 * This class handles the URL mapping system for composite products.
 * It converts complex composite configurations into simple, static URLs.
 *
 * @package Link_Wizard_Composite
 * @subpackage Link_Wizard_Composite/includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * URL Mapper class for composite products.
 *
 * HOW IT WORKS:
 * 
 * 1. USER CONFIGURES: User selects components and quantities (e.g., Component A: Product 72, Qty: 2)
 * 2. WE STORE: Configuration is saved in database with unique ID (e.g., cp139_3e3a7ecc)
 * 3. WE GENERATE: Simple URL is created: checkout-link/?products=cp139_3e3a7ecc:1
 * 4. USER VISITS: When someone clicks that URL, we intercept the request
 * 5. WE RETRIEVE: Look up the configuration from the database
 * 6. WE APPLY: Set up the composite product with saved components/quantities
 * 7. CHECKOUT: User goes to checkout with the configured composite product
 *
 * WHY WE NEED THIS:
 * - WooCommerce doesn't natively support composite products in checkout-links
 * - Facebook Commerce requires simple, static URLs (no complex parameters)
 * - This system converts complex configs into simple IDs
 * - We need the core plugin to allow for alterations to the product links, such as the cp139_3e3a7ecc part.
 */
class LWWC_Composite_URL_Mapper {

	/**
	 * Database table name for URL mappings.
	 *
	 * This stores the mapping between IDs (like cp139_3e3a7ecc) and configurations.
	 */
	const TABLE_NAME = 'lwwc_composite_url_mappings';

	/**
	 * Initialize the URL mapper.
	 *
	 * Sets up WordPress hooks for:
	 * - Creating the database table
	 * - Intercepting checkout-link requests
	 */
	public function init() {
		// Create the database table on plugin activation.
		add_action( 'init', array( $this, 'maybe_create_table' ), 1 );

		// Add rewrite endpoint for composite checkout links.
		add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );

		// Intercept composite checkout-link requests BEFORE WooCommerce's handler.
		// Priority 1 ensures we run before WooCommerce at priority 10.
		add_action( 'template_redirect', array( $this, 'handle_checkout_link' ), 1 );

		error_log( 'Link Wizard for Composites: URL Mapper initialized' );
	}

	/**
	 * Add rewrite endpoint for composite checkout links.
	 *
	 * This allows us to use /checkout-link/ for composite products
	 * while still intercepting them before WooCommerce processes them.
	 */
	public function add_rewrite_endpoint() {
		// We don't need a custom rewrite rule - we use WooCommerce's /checkout-link/
		// but intercept composite product requests before WooCommerce processes them.
	}

	/**
	 * Create database table if it doesn't exist.
	 *
	 * TABLE STRUCTURE:
	 * - id: Auto-increment primary key
	 * - mapping_id: Unique ID like "cp139_3e3a7ecc"
	 * - product_id: The composite product ID (139)
	 * - configuration: JSON with component selections and quantities
	 * - created_at: When this mapping was created
	 */
	public function maybe_create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Check if table already exists.
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

		if ( $table_exists ) {
			return; // Table already exists, nothing to do.
		}

		// Create the table.
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			mapping_id varchar(50) NOT NULL,
			product_id bigint(20) NOT NULL,
			configuration longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY mapping_id (mapping_id),
			KEY product_id (product_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		error_log( 'Link Wizard for Composites: Database table created' );
	}

	/**
	 * Generate a Meta-compatible URL for a composite product.
	 *
	 * PARAMETERS:
	 * @param int   $product_id    The composite product ID (e.g., 139)
	 * @param array $configuration The component selections (e.g., {'component_1': {'product_id': 72, 'quantity': 2}})
	 * @param int   $quantity      How many of this composite to add (usually 1)
	 *
	 * RETURNS:
	 * Simple URL like: http://site.com/checkout-link/?products=cp139_3e3a7ecc:1
	 *
	 * EXAMPLE:
	 * $mapper->generate_facebook_url(139, ['components' => [...]], 1)
	 * → "http://site.com/checkout-link/?products=cp139_3e3a7ecc:1"
	 */
	public function generate_facebook_url( $product_id, $configuration, $quantity = 1 ) {
		// Create or retrieve the mapping ID for this configuration.
		$mapping_id = $this->create_mapping( $product_id, $configuration );

		// Generate Facebook/META-compatible checkout-link URL.
		// Format: /checkout-link/?products=cp139_abc123:1
		// This format is REQUIRED by Facebook Commerce Platform.
		$base_url = home_url( '/checkout-link/' );
		return add_query_arg( 'products', $mapping_id . ':' . $quantity, $base_url );
	}

	/**
	 * Create a mapping for a composite configuration.
	 *
	 * HOW IT WORKS:
	 * 1. Generate unique ID: cp{product_id}_{hash} (e.g., cp139_3e3a7ecc)
	 * 2. Check if mapping already exists (to avoid duplicates)
	 * 3. If new, save to database
	 * 4. Return the mapping ID
	 *
	 * WHY THE HASH:
	 * - Same configuration = same hash = same URL (consistency)
	 * - Different configuration = different hash = different URL
	 * - Hash is first 8 characters of MD5 (short but unique enough)
	 *
	 * @param int   $product_id    The composite product ID.
	 * @param array $configuration The configuration array.
	 * @return string The mapping ID.
	 */
	private function create_mapping( $product_id, $configuration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Generate unique mapping ID based on product ID and configuration.
		// Format: cp{product_id}_{hash}
		// Example: cp139_3e3a7ecc
		$mapping_id = 'cp' . $product_id . '_' . substr( md5( wp_json_encode( $configuration ) ), 0, 8 );

		// Check if this mapping already exists.
		$existing_mapping = $wpdb->get_var(
			$wpdb->prepare( "SELECT mapping_id FROM $table_name WHERE mapping_id = %s", $mapping_id )
		);

		if ( $existing_mapping ) {
			// Mapping already exists, just return it.
			return $existing_mapping;
		}

		// Insert new mapping into database.
		$wpdb->insert(
			$table_name,
			array(
				'mapping_id'    => $mapping_id,
				'product_id'    => $product_id,
				'configuration' => wp_json_encode( $configuration ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);

		return $mapping_id;
	}

	/**
	 * Get configuration from a mapping ID.
	 *
	 * WHEN USED:
	 * When a user visits a URL like: checkout-link/?products=cp139_3e3a7ecc:1
	 * We need to look up what "cp139_3e3a7ecc" means.
	 *
	 * RETURNS:
	 * Array with:
	 * - 'product_id': The composite product ID
	 * - 'configuration': The component selections and quantities
	 *
	 * @param string $mapping_id The mapping ID to look up.
	 * @return array|null Configuration data or null if not found.
	 */
	private function get_configuration( $mapping_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Look up the mapping in the database.
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT configuration, product_id FROM $table_name WHERE mapping_id = %s", $mapping_id ),
			ARRAY_A
		);

		if ( $result ) {
			return array(
				'product_id'    => (int) $result['product_id'],
				'configuration' => json_decode( $result['configuration'], true ),
			);
		}

		return null;
	}

	/**
	 * Handle checkout-link requests with mapped composite URLs.
	 *
	 * THE INTERCEPTION PROCESS:
	 * 1. WordPress is about to load a page (template_redirect hook)
	 * 2. We check: Is this a checkout-link URL?
	 * 3. We check: Does it have a "cp" mapping ID?
	 * 4. If yes: Look up the configuration and apply it
	 * 5. Let WordPress continue (now with the composite configured)
	 *
	 * EXAMPLE:
	 * URL: /checkout-link/?products=cp139_3e3a7ecc:1
	 * We replace "cp139_3e3a7ecc" with the actual composite configuration.
	 */
	public function handle_checkout_link() {
		// Prevent double processing.
		static $processed = false;
		if ( $processed ) {
			return;
		}
		
		// Check if this is a checkout-link request.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public checkout link, no nonce needed.
		if ( ! isset( $_GET['products'] ) ) {
			return; // Not a checkout-link, do nothing.
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$products_param = sanitize_text_field( wp_unslash( $_GET['products'] ) );

		error_log( 'Link Wizard for Composites: Checking products param: ' . $products_param );

		// Check if this contains a composite mapping (cp prefix).
		if ( strpos( $products_param, 'cp' ) === false ) {
			error_log( 'Link Wizard for Composites: No cp prefix found, skipping' );
			return; // No composite mapping, do nothing.
		}

		$processed = true;
		error_log( 'Link Wizard for Composites: Found composite mapping, processing...' );

		// Ensure WooCommerce session is initialized.
		if ( is_null( WC()->session ) ) {
			WC()->initialize_session();
		}

		// Clear cart first (we'll re-add everything).
		WC()->cart->empty_cart();

		// Parse products parameter and separate composites from simple products.
		$products = explode( ',', $products_param );
		$composite_products = array();
		$simple_products    = array();
		
		foreach ( $products as $product_string ) {
			$parts = explode( ':', $product_string );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			
			list( $id, $quantity ) = $parts;
			$quantity = (int) $quantity;
			
			if ( strpos( $id, 'cp' ) === 0 ) {
				// This is a composite product mapping.
				$composite_products[] = array(
					'id'       => $id,
					'quantity' => $quantity,
				);
			} else {
				// This is a simple product.
				$simple_products[] = array(
					'id'       => (int) $id,
					'quantity' => $quantity,
				);
			}
		}

		// STEP 1: Add simple products FIRST.
		// This ensures they're in the cart before WooCommerce Composite Products takes over.
		if ( ! empty( $simple_products ) ) {
			error_log( 'Link Wizard for Composites: Adding ' . count( $simple_products ) . ' simple product(s) to cart' );
			
			foreach ( $simple_products as $simple_product ) {
				$product_id = $simple_product['id'];
				$quantity   = $simple_product['quantity'];
				
				// Validate product exists.
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					error_log( 'Link Wizard for Composites: Simple product ' . $product_id . ' not found, skipping' );
					continue;
				}
				
				// Add to cart using WooCommerce's standard method.
				$added = WC()->cart->add_to_cart( $product_id, $quantity );
				
				if ( $added ) {
					error_log( 'Link Wizard for Composites: Added simple product ' . $product_id . ' (qty: ' . $quantity . ') to cart' );
				} else {
					error_log( 'Link Wizard for Composites: Failed to add simple product ' . $product_id . ' to cart' );
				}
			}
		}

		// STEP 2: Process composite products.
		$has_composite = ! empty( $composite_products );
		
		foreach ( $composite_products as $composite_product ) {
			$id       = $composite_product['id'];
			$quantity = $composite_product['quantity'];
			
			// Process the composite mapping.
			$config = $this->get_configuration( $id );
			if ( ! $config ) {
				error_log( 'Link Wizard for Composites: Configuration not found for ' . $id );
				continue;
			}

			// Get the composite product.
			$product = wc_get_product( $config['product_id'] );
			if ( ! $product || ! $product->is_type( 'composite' ) ) {
				error_log( 'Link Wizard for Composites: Product not found or not composite' );
				continue;
			}

			error_log( 'Link Wizard for Composites: Found composite product ' . $config['product_id'] . ', adding to cart...' );
			
			$added = $this->add_composite_to_cart( $product, $config['configuration'], $quantity );
			
			if ( $added ) {
				error_log( 'Link Wizard for Composites: Added to cart successfully, cart contents: ' . count( WC()->cart->get_cart() ) . ' items' );
				
				// Calculate cart totals to ensure everything is ready.
				WC()->cart->calculate_totals();
				
				// Apply coupon if provided.
				if ( isset( $config['configuration']['coupon'] ) && ! empty( $config['configuration']['coupon'] ) ) {
					WC()->cart->apply_coupon( sanitize_text_field( $config['configuration']['coupon'] ) );
				}
				
				// CRITICAL: Persist the cart session.
				// This ensures the cart is saved before we redirect.
				WC()->session->set( 'cart', WC()->cart->get_cart_for_session() );
				WC()->session->set( 'cart_totals', WC()->cart->get_totals() );
				
				// Save the session data.
				if ( method_exists( WC()->session, 'save_data' ) ) {
					WC()->session->save_data();
				}
				
				// CRITICAL: Remove the products parameter so WooCommerce's handler doesn't process it.
				// This prevents WooCommerce from trying to add the composite product again.
				unset( $_GET['products'] );
				unset( $_REQUEST['products'] );
				
				// Redirect to checkout.
				error_log( 'Link Wizard for Composites: Redirecting to checkout: ' . wc_get_checkout_url() );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			} else {
				error_log( 'Link Wizard for Composites: Failed to add to cart' );
			}
		}
	}

	/**
	 * Add composite product to cart with configuration.
	 *
	 * This manually adds the composite product to the cart using WooCommerce's
	 * add_to_cart() method with the composite_data parameter.
	 *
	 * @param WC_Product $product       The composite product.
	 * @param array      $configuration The configuration with components.
	 * @param int        $quantity      Product quantity.
	 * @return bool|string Cart item key on success, false on failure.
	 */
	private function add_composite_to_cart( $product, $configuration, $quantity = 1 ) {
		// Build cart item data with composite configuration.
		$cart_item_data = array();
		
		// Format the components for WooCommerce Composite Products.
		// WooCommerce Composite Products expects $_GET parameters, not just cart_item_data!
		if ( isset( $configuration['components'] ) && ! empty( $configuration['components'] ) ) {
			$cart_item_data['composite_data'] = array();
			
			foreach ( $configuration['components'] as $component_id => $component_data ) {
				if ( isset( $component_data['product_id'] ) ) {
					// Build component data for cart
					$component_cart_data = array(
						'product_id' => absint( $component_data['product_id'] ),
						'quantity'   => isset( $component_data['quantity'] ) ? absint( $component_data['quantity'] ) : 1,
					);
					
					// Set $_GET parameters for WooCommerce Composite Products to read.
					// Note: wccps_* are used for component product selection (parent product for variables)
					$_GET['wccps_' . $component_id ] = absint( $component_data['product_id'] );
					$_GET['wccpq_' . $component_id ] = isset( $component_data['quantity'] ) ? absint( $component_data['quantity'] ) : 1;
					
					error_log( 'Link Wizard for Composites: Setting $_GET[wccps_' . $component_id . '] = ' . $component_data['product_id'] );
					error_log( 'Link Wizard for Composites: Setting $_GET[wccpq_' . $component_id . '] = ' . ( isset( $component_data['quantity'] ) ? $component_data['quantity'] : 1 ) );
					
					// Add variation attributes if present (for variable products with "Any" attributes).
					if ( isset( $component_data['attributes'] ) && ! empty( $component_data['attributes'] ) ) {
						error_log( 'Link Wizard for Composites: Adding attributes for component ' . $component_id . ': ' . wp_json_encode( $component_data['attributes'] ) );
						
						// Set variation ID parameter (wccpv_c1=82)
						// Use variation_id if available, otherwise use product_id
						$variation_id = isset( $component_data['variation_id'] ) ? absint( $component_data['variation_id'] ) : absint( $component_data['product_id'] );
						$_GET['wccpv_' . $component_id ] = $variation_id;
						error_log( 'Link Wizard for Composites: Setting $_GET[wccpv_' . $component_id . '] = ' . $variation_id );
						
						// Add variation_id to component data (use variation_id if available)
						$component_cart_data['variation_id'] = $variation_id;
						
						// Format attributes with 'attribute_' prefix (WooCommerce format)
						$formatted_attributes = array();
						foreach ( $component_data['attributes'] as $attr_name => $attr_value ) {
							// Add 'attribute_' prefix if not already present
							$formatted_key = strpos( $attr_name, 'attribute_' ) === 0 ? $attr_name : 'attribute_' . $attr_name;
							$formatted_attributes[ $formatted_key ] = sanitize_text_field( $attr_value );
						}
						$component_cart_data['attributes'] = $formatted_attributes;
						
						error_log( 'Link Wizard for Composites: Formatted attributes: ' . wp_json_encode( $formatted_attributes ) );
						
						// Set attribute $_GET parameters (wccp_attribute_pa_color_c1=blue, etc.)
						foreach ( $component_data['attributes'] as $attr_name => $attr_value ) {
							$_GET[ 'wccp_attribute_' . $attr_name . '_' . $component_id ] = sanitize_text_field( $attr_value );
							error_log( 'Link Wizard for Composites: Setting $_GET[wccp_attribute_' . $attr_name . '_' . $component_id . '] = ' . $attr_value );
						}
					}
					
					$cart_item_data['composite_data'][ $component_id ] = $component_cart_data;
				}
			}
		}
		
		error_log( 'Link Wizard for Composites: Cart item data: ' . wp_json_encode( $cart_item_data ) );
		error_log( 'Link Wizard for Composites: $_GET parameters: ' . wp_json_encode( $_GET ) );
		
		// Add to cart using WooCommerce's method.
		// WooCommerce Composite Products will read the $_GET parameters we just set.
		try {
			$cart_item_key = WC()->cart->add_to_cart(
				$product->get_id(),
				$quantity,
				0,  // Variation ID (not used for composites).
				array(),  // Variation attributes (not used for composites).
				$cart_item_data  // This contains our composite_data.
			);
			
			error_log( 'Link Wizard for Composites: Cart item key: ' . ( $cart_item_key ? $cart_item_key : 'FALSE' ) );
			
			return $cart_item_key;
		} catch ( Exception $e ) {
			error_log( 'Link Wizard for Composites: Error adding to cart - ' . $e->getMessage() );
			return false;
		}
	}

}








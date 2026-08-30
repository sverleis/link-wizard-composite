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
		if ( LWWC_COMPOSITE_VERSION === get_option( 'lwwc_composite_db_version' ) ) {
			return;
		}

		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
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
		update_option( 'lwwc_composite_db_version', LWWC_COMPOSITE_VERSION, false );
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

		$cache_key = 'mapping_' . $mapping_id;
		if ( false !== wp_cache_get( $cache_key, 'lwwc_composite' ) ) {
			return $mapping_id;
		}

		// A custom table is required because mappings are not WordPress content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name from $wpdb->prefix and a class constant; object cache is checked first.
		$existing_mapping = $wpdb->get_var( $wpdb->prepare( "SELECT mapping_id FROM $table_name WHERE mapping_id = %s", $mapping_id ) );

		if ( ! $existing_mapping ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to the plugin's custom mapping table.
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
		}

		wp_cache_set(
			$cache_key,
			array(
				'product_id'    => (int) $product_id,
				'configuration' => $configuration,
			),
			'lwwc_composite'
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
		$cache_key = 'mapping_' . $mapping_id;
		$cached    = wp_cache_get( $cache_key, 'lwwc_composite' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// A custom table is required because mappings are not WordPress content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name from $wpdb->prefix and a class constant; result is cached below.
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT configuration, product_id FROM $table_name WHERE mapping_id = %s", $mapping_id ), ARRAY_A );

		if ( ! $result ) {
			return null;
		}

		$configuration = array(
			'product_id'    => (int) $result['product_id'],
			'configuration' => json_decode( $result['configuration'], true ),
		);
		wp_cache_set( $cache_key, $configuration, 'lwwc_composite' );

		return $configuration;
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

		// Check if this contains a composite mapping (cp prefix).
		if ( false === strpos( $products_param, 'cp' ) ) {
			return; // No composite mapping, do nothing.
		}

		$processed = true;

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
			foreach ( $simple_products as $simple_product ) {
				$product_id = $simple_product['id'];
				$quantity   = $simple_product['quantity'];
				
				// Validate product exists.
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				// Add to cart using WooCommerce's standard method.
				WC()->cart->add_to_cart( $product_id, $quantity );
			}
		}

		// STEP 2: Process composite products.
		foreach ( $composite_products as $composite_product ) {
			$id       = $composite_product['id'];
			$quantity = $composite_product['quantity'];
			
			// Process the composite mapping.
			$config = $this->get_configuration( $id );
			if ( ! $config ) {
				continue;
			}

			// Get the composite product.
			$product = wc_get_product( $config['product_id'] );
			if ( ! $product || ! $product->is_type( 'composite' ) ) {
				continue;
			}

			$added = $this->add_composite_to_cart( $product, $config['configuration'], $quantity );

			if ( $added ) {
				
				// Calculate cart totals to ensure everything is ready.
				WC()->cart->calculate_totals();
				
				// Apply coupon if provided in configuration.
				if ( isset( $config['configuration']['coupon'] ) && ! empty( $config['configuration']['coupon'] ) ) {
					WC()->cart->apply_coupon( sanitize_text_field( $config['configuration']['coupon'] ) );
				}
				
				// Also check for a coupon in the public checkout URL.
				$coupon_code = '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public checkout links cannot include a user-specific nonce.
				if ( isset( $_GET['coupon'] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public checkout links cannot include a user-specific nonce.
					$coupon_code = sanitize_text_field( wp_unslash( $_GET['coupon'] ) );
				}
				if ( $coupon_code ) {
					WC()->cart->apply_coupon( $coupon_code );
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
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
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
		$components = isset( $configuration['components'] ) && is_array( $configuration['components'] )
			? $configuration['components']
			: array();
		$components = $this->normalize_component_configuration( $components );

		if ( is_wp_error( $components ) || ! class_exists( 'WC_CP_Cart' ) ) {
			return false;
		}

		try {
			$cart_item_key = WC_CP_Cart::instance()->add_composite_to_cart(
				$product->get_id(),
				absint( $quantity ),
				$components
			);

			if ( is_wp_error( $cart_item_key ) ) {
				return false;
			}

			return $cart_item_key;
		} catch ( Exception $e ) {
			wc_get_logger()->error(
				$e->getMessage(),
				array(
					'source'     => 'link-wizard-composite',
					'product_id' => $product->get_id(),
				)
			);
			return false;
		}
	}

	/**
	 * Normalize stored component selections for the Composite Products cart API.
	 *
	 * Older mappings may contain a variable parent without variation data. In
	 * that case, resolve the product's default variation so existing links remain
	 * usable without passing incomplete data to WooCommerce.
	 *
	 * @param array $components Stored component configuration.
	 * @return array|WP_Error Normalized configuration, or an error when invalid.
	 */
	private function normalize_component_configuration( $components ) {
		$normalized = array();

		foreach ( $components as $component_id => $component_data ) {
			$product_id = isset( $component_data['product_id'] ) ? absint( $component_data['product_id'] ) : 0;
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				return new WP_Error( 'invalid_composite_component', __( 'A selected composite component is unavailable.', 'link-wizard-composite' ) );
			}

			$item = array(
				'product_id' => $product_id,
				'quantity'   => isset( $component_data['quantity'] ) ? absint( $component_data['quantity'] ) : 1,
			);

			if ( $product->is_type( 'variable' ) ) {
				$attributes = array();
				if ( ! empty( $component_data['attributes'] ) && is_array( $component_data['attributes'] ) ) {
					foreach ( $component_data['attributes'] as $attribute_name => $attribute_value ) {
						$key                = 0 === strpos( $attribute_name, 'attribute_' ) ? $attribute_name : 'attribute_' . $attribute_name;
						$attributes[ $key ] = sanitize_text_field( $attribute_value );
					}
				}

				$variation_id = isset( $component_data['variation_id'] ) ? absint( $component_data['variation_id'] ) : 0;
				$variation    = $variation_id ? wc_get_product( $variation_id ) : false;

				if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product_id ) {
					$match_attributes = $attributes;
					if ( empty( $match_attributes ) ) {
						foreach ( $product->get_default_attributes() as $attribute_name => $attribute_value ) {
							$match_attributes[ 'attribute_' . $attribute_name ] = $attribute_value;
						}
					}

					$data_store   = WC_Data_Store::load( 'product' );
					$variation_id = $data_store->find_matching_product_variation( $product, $match_attributes );
					$variation    = $variation_id ? wc_get_product( $variation_id ) : false;
				}

				if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
					return new WP_Error( 'missing_composite_variation', __( 'Choose a variation for each variable composite component.', 'link-wizard-composite' ) );
				}

				$item['variation_id'] = $variation->get_id();
				$item['attributes']   = array_merge( $variation->get_variation_attributes(), $attributes );
			}

			$normalized[ sanitize_key( $component_id ) ] = $item;
		}

		return $normalized;
	}

}








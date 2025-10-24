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

		// Intercept checkout-link requests to process our mapped URLs.
		// Use 'wp' hook with priority 0 to run before WooCommerce processes the request.
		// This is critical - we must set $_GET parameters before WooCommerce reads them.
		add_action( 'wp', array( $this, 'handle_checkout_link' ), 0 );

		error_log( 'Link Wizard for Composites: URL Mapper initialized' );
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
		// IMPORTANT: WooCommerce's /checkout-link/ feature does NOT support composite products.
		// It only works for simple products with no configuration needed.
		//
		// Instead, we use the ?add-to-cart=ID format with component parameters.
		// This format:
		// - Works with composite products
		// - Adds product to cart with correct configuration
		// - Redirects to cart/checkout based on WooCommerce settings
		
		// Build add-to-cart URL with component parameters.
		$url = home_url( '/?add-to-cart=' . $product_id );
		
		// Add component selections.
		if ( isset( $configuration['components'] ) && ! empty( $configuration['components'] ) ) {
			foreach ( $configuration['components'] as $component_id => $component_data ) {
				if ( isset( $component_data['product_id'] ) ) {
					$url .= '&wccp_component_selection%5B' . $component_id . '%5D=' . $component_data['product_id'];
					$url .= '&wccp_component_quantity%5B' . $component_id . '%5D=' . ( $component_data['quantity'] ?? 1 );
				}
			}
		}
		
		// Add main product quantity.
		$url .= '&quantity=' . $quantity;

		return $url;
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
		// Check if this is a checkout-link request.
		if ( ! isset( $_GET['products'] ) ) {
			return; // Not a checkout-link, do nothing.
		}

		$products_param = sanitize_text_field( wp_unslash( $_GET['products'] ) );

		// Check if this contains a composite mapping (cp prefix).
		if ( strpos( $products_param, 'cp' ) === false ) {
			return; // No composite mapping, do nothing.
		}

		// Parse products parameter (could have multiple products: "cp139_abc:1,18:2").
		$products = explode( ',', $products_param );
		$new_products = array();

		foreach ( $products as $product_string ) {
			list( $id, $quantity ) = explode( ':', $product_string );

			// Check if this is a composite mapping.
			if ( strpos( $id, 'cp' ) === 0 ) {
				// This is a composite mapping - process it.
				$this->setup_composite_data( $id );

				// Replace mapping ID with actual product ID.
				$config = $this->get_configuration( $id );
				if ( $config ) {
					$new_products[] = $config['product_id'] . ':' . $quantity;
				}
			} else {
				// Regular product, keep as is.
				$new_products[] = $product_string;
			}
		}

		// Update the products parameter with actual product IDs.
		if ( ! empty( $new_products ) ) {
			$_GET['products'] = implode( ',', $new_products );
		}
	}

	/**
	 * Set up composite product data from a mapping ID.
	 *
	 * THE CRITICAL STEP:
	 * When someone visits checkout-link/?products=cp139_3e3a7ecc:1
	 * We need to tell WooCommerce Composite Products:
	 * "Hey, for component 1, use product 72 with quantity 2"
	 * "And for component 2, use product 86 with quantity 1"
	 *
	 * HOW WE DO IT:
	 * WooCommerce Composite Products reads $_GET parameters like:
	 * - wccp_component_{component_id} = product_id
	 * - wccp_quantity_{component_id} = quantity
	 *
	 * We set these parameters so WooCommerce sees them and configures the composite.
	 *
	 * @param string $mapping_id The mapping ID.
	 */
	private function setup_composite_data( $mapping_id ) {
		// Get configuration from mapping.
		$config = $this->get_configuration( $mapping_id );

		if ( ! $config ) {
			error_log( 'Link Wizard for Composites: No configuration found for ' . $mapping_id );
			return; // Configuration not found.
		}

		$product_id = $config['product_id'];
		$configuration = $config['configuration'];

		error_log( 'Link Wizard for Composites: Processing mapping ' . $mapping_id . ' for product ' . $product_id );

		// Get the components from the configuration.
		if ( ! isset( $configuration['components'] ) ) {
			error_log( 'Link Wizard for Composites: No components in configuration!' );
			return; // No components configured.
		}

		$components = $configuration['components'];
		error_log( 'Link Wizard for Composites: Found ' . count( $components ) . ' components to configure' );

		// Set up $_GET parameters for each component.
		// WooCommerce Composite Products will read these and configure the product.
		foreach ( $components as $component_id => $component_data ) {
			if ( ! isset( $component_data['product_id'] ) ) {
				continue;
			}

			$selected_product_id = $component_data['product_id'];
			$quantity = isset( $component_data['quantity'] ) ? $component_data['quantity'] : 1;

			// Set component selection parameter.
			// WooCommerce Composite Products expects: wccp_component_selection[{component_id}] = product_id
			if ( ! isset( $_REQUEST['wccp_component_selection'] ) ) {
				$_REQUEST['wccp_component_selection'] = array();
			}
			$_REQUEST['wccp_component_selection'][ $component_id ] = $selected_product_id;
			$_GET['wccp_component_selection'][ $component_id ] = $selected_product_id;

			// Set quantity parameter.
			// WooCommerce Composite Products expects: wccp_component_quantity[{component_id}] = quantity
			if ( ! isset( $_REQUEST['wccp_component_quantity'] ) ) {
				$_REQUEST['wccp_component_quantity'] = array();
			}
			$_REQUEST['wccp_component_quantity'][ $component_id ] = $quantity;
			$_GET['wccp_component_quantity'][ $component_id ] = $quantity;

			error_log( "Link Wizard for Composites: Set component {$component_id} to product {$selected_product_id} with quantity {$quantity}" );
		}

		error_log( 'Link Wizard for Composites: Composite data configured for checkout' );
	}
}



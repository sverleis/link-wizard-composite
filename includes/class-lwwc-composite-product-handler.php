<?php
/**
 * Composite Product Handler.
 *
 * This class handles the logic for composite products:
 * - Getting product data (components, options, prices)
 * - Generating checkout URLs
 * - Calculating prices based on component selections
 *
 * @package Link_Wizard_Composite
 * @subpackage Link_Wizard_Composite/includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Composite Product Handler class.
 *
 * WHAT IT DOES:
 * - Retrieves composite product data (components, options)
 * - Generates checkout-link URLs for configured composites
 * - Calculates total price based on selected components
 * - Validates composite product configurations
 */
class LWWC_Composite_Product_Handler implements LWWC_Product_Handler_Interface {

	/**
	 * Check if we can handle this product.
	 *
	 * @param WC_Product $product The product to check.
	 * @return bool True if this is a composite product.
	 */
	public function can_handle( $product ) {
		return $product && $product->is_type( 'composite' );
	}

	/**
	 * Get composite product data for the frontend.
	 *
	 * THE FRONTEND NEEDS:
	 * - List of components (e.g., "Choose a T-Shirt", "Choose a Cap")
	 * - Options for each component (available products)
	 * - Quantity limits (min/max for each component)
	 * - Pricing information
	 *
	 * RETURNS:
	 * Array with all the data the frontend needs to display the configuration UI.
	 *
	 * @param WC_Product $product The composite product.
	 * @return array Product data including components and options.
	 */
	public function get_product_data( $product ) {
		if ( ! $this->can_handle( $product ) ) {
			return array();
		}

		// Get basic product info.
		$data = array(
			'id'    => $product->get_id(),
			'name'  => $product->get_name(),
			'price' => $product->get_price_html(),
			'type'  => 'composite',
		);

		// Get components (the parts user can configure).
		$components = $this->get_components( $product );
		if ( ! empty( $components ) ) {
			$data['components'] = $components;
		}

		// Generate checkout URL (will be updated when user configures).
		$data['checkout_url'] = $this->generate_checkout_url( $product, array() );

		return $data;
	}

	/**
	 * Get components for a composite product.
	 *
	 * COMPONENTS ARE:
	 * Each configurable part of the composite (e.g., "T-Shirt", "Cap", "Belt")
	 *
	 * FOR EACH COMPONENT WE NEED:
	 * - ID (unique identifier)
	 * - Title (display name like "Choose a T-Shirt")
	 * - Options (available products user can select)
	 * - Quantity limits (min/max)
	 * - Whether it's optional or required
	 *
	 * @param WC_Product $product The composite product.
	 * @return array List of components with their options.
	 */
	private function get_components( $product ) {
		$components = array();

		// Get components from WooCommerce Composite Products.
		if ( ! method_exists( $product, 'get_composite_data' ) ) {
			return $components; // Not a valid composite product.
		}

		$composite_data = $product->get_composite_data();

		foreach ( $composite_data as $component_id => $component ) {
			// Get the component object.
			$component_obj = $product->get_component( $component_id );

			if ( ! $component_obj ) {
				continue;
			}

			// Get component details.
			$components[] = array(
				'id'          => $component_id,
				'title'       => $component_obj->get_title(),
				'description' => $component_obj->get_description(),
				'optional'    => $component_obj->is_optional(),
				'quantity'    => array(
					'min'     => $component_obj->get_quantity( 'min' ),
					'max'     => $component_obj->get_quantity( 'max' ),
					'default' => $component_obj->get_quantity( 'min' ), // Default to minimum.
				),
				'options'     => $this->get_component_options( $component_obj ),
			);
		}

		return $components;
	}

	/**
	 * Get available options (products) for a component.
	 *
	 * OPTIONS ARE:
	 * The products a user can choose from for this component.
	 * For example, for "Choose a T-Shirt" component, options might be:
	 * - V-Neck T-Shirt (Product ID 72)
	 * - Crew Neck T-Shirt (Product ID 73)
	 *
	 * @param WC_CP_Component $component The component object.
	 * @return array List of available products for this component.
	 */
	private function get_component_options( $component ) {
		$options = array();

		// Get product IDs that can be used for this component.
		$option_ids = $component->get_options();

		foreach ( $option_ids as $option_id ) {
			$option_product = wc_get_product( $option_id );

			if ( ! $option_product ) {
				continue;
			}

			$options[] = array(
				'id'    => $option_product->get_id(),
				'name'  => $option_product->get_name(),
				'price' => $option_product->get_price_html(),
				'type'  => $option_product->get_type(),
			);
		}

		return $options;
	}

	/**
	 * Generate a checkout-link URL for a configured composite product.
	 *
	 * SMART URL GENERATION:
	 * - If composite uses default values: Return simple URL (products=139:1)
	 * - If composite has custom selections: Use URL mapping system (products=cp139_3e3a7ecc:1)
	 *
	 * WHY THIS MATTERS:
	 * - Simpler URLs when possible (better for users, SEO, debugging)
	 * - Only use mapping when actually needed (custom configurations)
	 * - Reduces database storage (no mappings for default configs)
	 *
	 * HOW IT WORKS:
	 * 1. Check if user made custom selections
	 * 2. If yes: Use URL mapping system
	 * 3. If no: Return simple product URL
	 *
	 * PARAMETERS:
	 * @param WC_Product $product             The composite product.
	 * @param array      $component_selections Array of component selections.
	 *
	 * RETURNS:
	 * - Default config: checkout-link/?products=139:1
	 * - Custom config: checkout-link/?products=cp139_3e3a7ecc:1
	 */
	public function generate_checkout_url( $product, $component_selections = array() ) {
		if ( ! $this->can_handle( $product ) ) {
			return '';
		}

		// IMPORTANT: Composite products ALWAYS need the URL mapping system,
		// even for default configurations, because WooCommerce's native
		// checkout-link handler doesn't understand composite product components.
		//
		// Our URL mapper will intercept the request and set the proper
		// component configuration ($_GET parameters) that WooCommerce Composite Products expects.

		// If no selections provided, use default component selections.
		if ( empty( $component_selections ) ) {
			$component_selections = $this->get_default_component_selections( $product );
		}

		// Prepare configuration for URL mapper.
		$configuration = array(
			'product_id' => $product->get_id(),
			'components' => $this->format_component_selections( $component_selections ),
		);

		// Use URL mapper to create the mapped URL.
		require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-url-mapper.php';
		$url_mapper = new LWWC_Composite_URL_Mapper();

		return $url_mapper->generate_facebook_url( $product->get_id(), $configuration, 1 );
	}

	/**
	 * Get default component selections for a composite product.
	 *
	 * This retrieves the default options and quantities for each component,
	 * which allows us to create a "default configuration" URL.
	 *
	 * @param WC_Product $product The composite product.
	 * @return array Default component selections.
	 */
	private function get_default_component_selections( $product ) {
		$default_selections = array();

		// Get composite data.
		if ( ! method_exists( $product, 'get_composite_data' ) ) {
			return $default_selections;
		}

		$composite_data = $product->get_composite_data();

		// Get default selection for each component.
		foreach ( $composite_data as $component_id => $component_data ) {
			$component_obj = $product->get_component( $component_id );

			if ( ! $component_obj ) {
				continue;
			}

			// Get the default selected option.
			$default_option = $component_obj->get_default_option();

			if ( $default_option ) {
				$default_selections[ $component_id ] = array(
					'product_id' => $default_option,
					'quantity'   => $component_obj->get_quantity( 'min' ), // Use minimum quantity as default.
				);
			}
		}

		return $default_selections;
	}

	/**
	 * Check if component selections match the default configuration.
	 *
	 * WHY WE CHECK:
	 * If user selected exactly what the composite would use by default,
	 * we don't need a special URL - just use products=139:1
	 *
	 * HOW WE CHECK:
	 * 1. Get the default component selections from WooCommerce
	 * 2. Compare with user's selections
	 * 3. If they match: Return true (use simple URL)
	 * 4. If different: Return false (use mapped URL)
	 *
	 * @param WC_Product $product             The composite product.
	 * @param array      $component_selections User's component selections.
	 * @return bool True if selections match defaults.
	 */
	private function is_default_configuration( $product, $component_selections ) {
		// Get composite data.
		if ( ! method_exists( $product, 'get_composite_data' ) ) {
			return false;
		}

		$composite_data = $product->get_composite_data();

		// Check each component selection against defaults.
		foreach ( $component_selections as $selection ) {
			if ( ! isset( $selection['id'] ) || ! isset( $selection['selected_option'] ) ) {
				continue;
			}

			$component_id = $selection['id'];
			$selected_product_id = $selection['selected_option']['id'];
			$selected_quantity = isset( $selection['quantity'] ) ? $selection['quantity'] : 1;

			// Get component object.
			$component_obj = $product->get_component( $component_id );
			if ( ! $component_obj ) {
				continue;
			}

			// Get default product for this component.
			$default_option_id = $component_obj->get_default_option();
			$default_quantity = $component_obj->get_quantity( 'min' );

			// If selected product or quantity differs from default, it's custom.
			if ( $selected_product_id != $default_option_id || $selected_quantity != $default_quantity ) {
				return false; // Custom configuration detected.
			}
		}

		// All selections match defaults.
		return true;
	}

	/**
	 * Format component selections for storage.
	 *
	 * CONVERTS FRONTEND FORMAT TO STORAGE FORMAT:
	 * 
	 * Frontend sends:
	 * [
	 *   {id: "1", selected_option: {id: 72}, quantity: 2},
	 *   {id: "2", selected_option: {id: 86}, quantity: 1}
	 * ]
	 *
	 * We convert to:
	 * {
	 *   "1": {product_id: 72, quantity: 2},
	 *   "2": {product_id: 86, quantity: 1}
	 * }
	 *
	 * @param array $component_selections Component selections from frontend.
	 * @return array Formatted configuration.
	 */
	private function format_component_selections( $component_selections ) {
		$formatted = array();

		foreach ( $component_selections as $selection ) {
			if ( ! isset( $selection['id'] ) || ! isset( $selection['selected_option'] ) ) {
				continue;
			}

			$component_id = $selection['id'];
			$formatted[ $component_id ] = array(
				'product_id' => $selection['selected_option']['id'],
				'quantity'   => isset( $selection['quantity'] ) ? $selection['quantity'] : 1,
			);
		}

		return $formatted;
	}

	/**
	 * Calculate the price of a composite product based on component selections.
	 *
	 * WHY WE NEED THIS:
	 * - Composite products show "From: $X" by default
	 * - When user selects components, we calculate the actual price
	 * - This gives accurate pricing before checkout
	 *
	 * HOW IT WORKS:
	 * 1. Get each selected component product
	 * 2. Multiply product price × quantity
	 * 3. Add all component prices together
	 * 4. Return formatted price HTML
	 *
	 * TODO: This will be enhanced in Step 6 with WooCommerce's native calculation.
	 *
	 * @param WC_Product $product             The composite product.
	 * @param array      $component_selections Component selections.
	 * @return string|false Formatted price HTML or false on error.
	 */
	public function calculate_price( $product, $component_selections ) {
		if ( ! $this->can_handle( $product ) ) {
			return false;
		}

		$total_price = 0;

		// Add up component prices.
		foreach ( $component_selections as $selection ) {
			if ( ! isset( $selection['selected_option']['id'] ) || ! isset( $selection['quantity'] ) ) {
				continue;
			}

			$component_product = wc_get_product( $selection['selected_option']['id'] );
			if ( $component_product ) {
				$component_price = $component_product->get_price();
				$quantity = $selection['quantity'];
				$total_price += ( $component_price * $quantity );
			}
		}

		// Return formatted price.
		return $total_price > 0 ? wc_price( $total_price ) : false;
	}

	/**
	 * Get the product type this handler supports.
	 *
	 * @return string
	 */
	public function get_product_type() {
		return 'composite';
	}

	/**
	 * Get search results for this product type.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	public function get_search_results( $product ) {
		if ( ! $this->can_handle( $product ) ) {
			return array();
		}

		// Generate default checkout URL (uses default component selections).
		$default_checkout_url = $this->generate_checkout_url( $product, array() );

		// Return basic product data for search results.
		return array(
			array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'price'        => $product->get_price_html(),
				'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'parent_id'    => '',
				'parent_name'  => '',
				'attributes'   => array(),
				'type'         => 'composite',
				'slug'         => $product->get_slug(),
				'status'       => $product->get_status(),
				'checkout_url' => $default_checkout_url,  // Default configuration URL.
				'url'          => $default_checkout_url,  // Alias for compatibility.
			),
		);
	}

	/**
	 * Validate if the product can be used in links.
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	public function is_valid_for_links( $product ) {
		if ( ! $this->can_handle( $product ) ) {
			return false;
		}

		// Check if product is published and has components.
		if ( 'publish' !== $product->get_status() ) {
			return false;
		}

		// Check if composite has components.
		$components = $this->get_components( $product );
		return ! empty( $components );
	}

	/**
	 * Get validation errors for the product.
	 *
	 * @param WC_Product $product
	 * @return array Array of validation errors.
	 */
	public function get_validation_errors( $product ) {
		$errors = array();

		if ( ! $this->can_handle( $product ) ) {
			return $errors;
		}

		// Check if product is published.
		if ( 'publish' !== $product->get_status() ) {
			$errors[] = __( 'Composite product is not published.', 'link-wizard-composite' );
		}

		// Check if composite has components.
		$components = $this->get_components( $product );
		if ( empty( $components ) ) {
			$errors[] = __( 'Composite product has no components configured.', 'link-wizard-composite' );
		}

		return $errors;
	}

	/**
	 * Get validation data for frontend display.
	 *
	 * @param WC_Product $product
	 * @return array Validation data including errors and warnings.
	 */
	public function get_validation_data( $product ) {
		$errors = $this->get_validation_errors( $product );
		$warnings = array();

		// Add warnings for composite-specific issues.
		if ( $this->can_handle( $product ) ) {
			$components = $this->get_components( $product );
			
			foreach ( $components as $component ) {
				// Check if component has options by looking at its configuration.
				$component_options = $component['options'] ?? array();
				if ( empty( $component_options ) ) {
					$warnings[] = sprintf(
						__( 'Component "%s" has no available options.', 'link-wizard-composite' ),
						$component['title'] ?? 'Unknown'
					);
				}
			}
		}

		return array(
			'is_valid' => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}


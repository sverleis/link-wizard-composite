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
class LWWC_Composite_Product_Handler {

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
	 * HOW IT WORKS:
	 * 1. User selects components (e.g., Component 1 = Product 72, Qty 2)
	 * 2. We create a configuration array with all selections
	 * 3. We pass it to the URL mapper
	 * 4. URL mapper stores it and returns a mapping ID
	 * 5. We build the final URL with that mapping ID
	 *
	 * PARAMETERS:
	 * @param WC_Product $product             The composite product.
	 * @param array      $component_selections Array of component selections.
	 *
	 * RETURNS:
	 * Simple URL like: checkout-link/?products=cp139_3e3a7ecc:1
	 */
	public function generate_checkout_url( $product, $component_selections = array() ) {
		if ( ! $this->can_handle( $product ) ) {
			return '';
		}

		// If no selections provided, return a basic checkout-link.
		if ( empty( $component_selections ) ) {
			return home_url( '/checkout-link/?products=' . $product->get_id() . ':1' );
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
}


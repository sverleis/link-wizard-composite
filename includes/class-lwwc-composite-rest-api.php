<?php
/**
 * REST API Handler for Composite Products.
 *
 * This class registers and handles REST API endpoints that allow the frontend
 * to communicate with the backend for composite product functionality.
 *
 * @package Link_Wizard_Composite
 * @subpackage Link_Wizard_Composite/includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Composite Products REST API class.
 *
 * WHAT IT DOES:
 * - Registers REST API endpoints for composite products
 * - Handles frontend requests for product data
 * - Generates checkout URLs based on component selections
 * - Calculates prices for configured composites
 *
 * ENDPOINTS WE CREATE:
 * - GET  /lwwc-composite/v1/product/{id}           → Get composite product data
 * - POST /lwwc-composite/v1/generate-url           → Generate checkout URL
 * - POST /lwwc-composite/v1/calculate-price        → Calculate composite price
 */
class LWWC_Composite_REST_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'lwwc-composite/v1';

	/**
	 * Product handler instance.
	 *
	 * @var LWWC_Composite_Product_Handler
	 */
	private $product_handler;

	/**
	 * Initialize the REST API.
	 */
	public function init() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		error_log( 'Link Wizard for Composites: REST API initialized' );
	}

	/**
	 * Get product handler instance.
	 *
	 * @return LWWC_Composite_Product_Handler
	 */
	private function get_product_handler() {
		if ( null === $this->product_handler ) {
			require_once LWWC_COMPOSITE_PATH . 'includes/class-lwwc-composite-product-handler.php';
			$this->product_handler = new LWWC_Composite_Product_Handler();
		}
		return $this->product_handler;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Endpoint 1: Get composite product data.
		register_rest_route(
			self::NAMESPACE,
			'/product/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_data' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Endpoint 2: Generate checkout URL.
		register_rest_route(
			self::NAMESPACE,
			'/generate-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate_url' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'product_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'component_selections' => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
					'quantity'            => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		// Endpoint 3: Calculate price.
		register_rest_route(
			self::NAMESPACE,
			'/calculate-price',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'calculate_price' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'product_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'component_selections' => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
				),
			)
		);

		// Endpoint 4: Get filtered variations for variable products.
		register_rest_route(
			self::NAMESPACE,
			'/variations/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_filtered_variations' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'         => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'attributes' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'validate_callback' => function ( $param ) {
							// Should be JSON string or empty.
							return is_string( $param );
						},
					),
				),
			)
		);

		error_log( 'Link Wizard for Composites: REST API routes registered' );
	}

	/**
	 * Check if user has permission to use these endpoints.
	 *
	 * @return bool
	 */
	public function check_permission() {
		// Only users who can manage WooCommerce should use these endpoints.
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get composite product data.
	 *
	 * Endpoint: GET /lwwc-composite/v1/product/{id}
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_data( $request ) {
		$product_id = $request->get_param( 'id' );

		// Get the product.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found.', 'link-wizard-composite' ),
				array( 'status' => 404 )
			);
		}

		// Check if it's a composite product.
		$handler = $this->get_product_handler();
		if ( ! $handler->can_handle( $product ) ) {
			return new WP_Error(
				'not_composite',
				__( 'Product is not a composite product.', 'link-wizard-composite' ),
				array( 'status' => 400 )
			);
		}

		// Get product data.
		$product_data = $handler->get_product_data( $product );

		return rest_ensure_response( $product_data );
	}

	/**
	 * Generate checkout URL for composite product.
	 *
	 * Endpoint: POST /lwwc-composite/v1/generate-url
	 *
	 * Request body:
	 * {
	 *   "product_id": 139,
	 *   "component_selections": {
	 *     "1757251096": { "product_id": 72, "quantity": 2 },
	 *     "1757251203": { "product_id": 86, "quantity": 1 }
	 *   },
	 *   "quantity": 1
	 * }
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_url( $request ) {
		$product_id          = $request->get_param( 'product_id' );
		$component_selections = $request->get_param( 'component_selections' );
		$quantity            = $request->get_param( 'quantity' ) ?: 1;

		// Get the product.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found.', 'link-wizard-composite' ),
				array( 'status' => 404 )
			);
		}

		// Check if it's a composite product.
		$handler = $this->get_product_handler();
		if ( ! $handler->can_handle( $product ) ) {
			return new WP_Error(
				'not_composite',
				__( 'Product is not a composite product.', 'link-wizard-composite' ),
				array( 'status' => 400 )
			);
		}

		// Generate checkout URL.
		$checkout_url = $handler->generate_checkout_url( $product, $component_selections );

		if ( empty( $checkout_url ) ) {
			return new WP_Error(
				'url_generation_failed',
				__( 'Failed to generate checkout URL.', 'link-wizard-composite' ),
				array( 'status' => 500 )
			);
		}

		// Return the URL and related data.
		return rest_ensure_response(
			array(
				'success'      => true,
				'checkout_url' => $checkout_url,
				'product_id'   => $product_id,
				'quantity'     => $quantity,
			)
		);
	}

	/**
	 * Calculate price for composite product with selections.
	 *
	 * Endpoint: POST /lwwc-composite/v1/calculate-price
	 *
	 * Request body:
	 * {
	 *   "product_id": 139,
	 *   "component_selections": {
	 *     "1757251096": { "product_id": 72, "quantity": 2 },
	 *     "1757251203": { "product_id": 86, "quantity": 1 }
	 *   }
	 * }
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function calculate_price( $request ) {
		$product_id          = $request->get_param( 'product_id' );
		$component_selections = $request->get_param( 'component_selections' );

		// Get the product.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found.', 'link-wizard-composite' ),
				array( 'status' => 404 )
			);
		}

		// Check if it's a composite product.
		$handler = $this->get_product_handler();
		if ( ! $handler->can_handle( $product ) ) {
			return new WP_Error(
				'not_composite',
				__( 'Product is not a composite product.', 'link-wizard-composite' ),
				array( 'status' => 400 )
			);
		}

		// Calculate price.
		$price = $handler->calculate_price( $product, $component_selections );

		if ( false === $price ) {
			return new WP_Error(
				'price_calculation_failed',
				__( 'Failed to calculate price.', 'link-wizard-composite' ),
				array( 'status' => 500 )
			);
		}

		// Return the price and related data.
		return rest_ensure_response(
			array(
				'success'    => true,
				'price'      => $price,
				'price_html' => $price, // Already formatted by calculate_price().
				'product_id' => $product_id,
			)
		);
	}

	/**
	 * Get filtered variations for a variable product.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_filtered_variations( $request ) {
		$product_id  = $request->get_param( 'id' );
		$attributes_json = $request->get_param( 'attributes' );

		// Get the product.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found.', 'link-wizard-composite' ),
				array( 'status' => 404 )
			);
		}

		// Check if it's a variable product.
		if ( ! $product->is_type( 'variable' ) ) {
			return new WP_Error(
				'not_variable',
				__( 'Product is not a variable product.', 'link-wizard-composite' ),
				array( 'status' => 400 )
			);
		}

		// Parse attributes if provided.
		$selected_attributes = array();
		if ( ! empty( $attributes_json ) ) {
			$selected_attributes = json_decode( $attributes_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error(
					'invalid_attributes',
					__( 'Invalid attributes format.', 'link-wizard-composite' ),
					array( 'status' => 400 )
				);
			}
		}

		// Get filtered variations using the core plugin's logic.
		$handler = $this->get_product_handler();
		$variations = $handler->get_filtered_variations( $product, $selected_attributes );

		return rest_ensure_response( $variations );
	}
}


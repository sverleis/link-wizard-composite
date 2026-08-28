/**
 * Link Wizard Composite - Integration with Link Wizard Core
 *
 * This file provides the integration layer between Link Wizard's ProductSelect
 * component and the composite product configuration UI.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	/**
	 * Initialize the complex products integration.
	 */
	function initCompositeIntegration() {
		if ( typeof window.LWWCAddons === 'undefined' ) {
			window.LWWCAddons = {};
		}

		if ( typeof window.LWWCAddons.complexProducts === 'undefined' ) {
			window.LWWCAddons.complexProducts = {};
		}

		window.LWWCAddons.complexProducts.toggleProductExpansion = function (
			productId
		) {
			if ( ! window.lwwcCompositeExpandedProducts ) {
				window.lwwcCompositeExpandedProducts = new Set();
			}

			if ( window.lwwcCompositeExpandedProducts.has( productId ) ) {
				window.lwwcCompositeExpandedProducts.delete( productId );
			} else {
				window.lwwcCompositeExpandedProducts.add( productId );
			}

			if ( window.LWWCAddons.complexProducts.onStateChange ) {
				window.LWWCAddons.complexProducts.onStateChange();
			}

			return window.lwwcCompositeExpandedProducts.has( productId );
		};

		window.LWWCAddons.complexProducts.isProductExpanded = function (
			productId
		) {
			if ( ! window.lwwcCompositeExpandedProducts ) {
				return false;
			}

			return window.lwwcCompositeExpandedProducts.has( productId );
		};

		window.LWWCAddons.complexProducts.addCompositeProduct = function (
			product,
			_componentSelections,
			setSelectedProducts
		) {
			if ( typeof setSelectedProducts !== 'function' ) {
				return;
			}

			const compositeProduct = {
				...product,
				quantity: 1,
			};
			const currentProducts = window.lwwcSelectedProducts || [];

			setSelectedProducts( [ ...currentProducts, compositeProduct ] );
		};
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener(
			'DOMContentLoaded',
			initCompositeIntegration
		);
	} else {
		initCompositeIntegration();
	}
} )();

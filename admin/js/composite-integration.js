/**
 * Link Wizard Composite - Integration with Link Wizard Core
 *
 * This file provides the integration layer between Link Wizard's ProductSelect
 * component and our composite product configuration UI.
 *
 * @package Link_Wizard_Composite
 * @since 1.0.0
 */

(function() {
    'use strict';

    console.log('Link Wizard Composite: Integration script loaded');

    /**
     * Initialize the complex products integration.
     */
    function initCompositeIntegration() {
        // Initialize the LWWCAddons global object if it doesn't exist.
        if (typeof window.LWWCAddons === 'undefined') {
            window.LWWCAddons = {};
        }

        // Initialize complexProducts if it doesn't exist.
        if (typeof window.LWWCAddons.complexProducts === 'undefined') {
            window.LWWCAddons.complexProducts = {};
        }

        // Add our composite product functionality to the global object.
        window.LWWCAddons.complexProducts.toggleProductExpansion = function(productId) {
            console.log('Link Wizard Composite: toggleProductExpansion called for product', productId);
            
            // Manage expanded state.
            if (!window.lwwcCompositeExpandedProducts) {
                window.lwwcCompositeExpandedProducts = new Set();
            }

            if (window.lwwcCompositeExpandedProducts.has(productId)) {
                window.lwwcCompositeExpandedProducts.delete(productId);
                console.log('Link Wizard Composite: Collapsed product', productId);
            } else {
                window.lwwcCompositeExpandedProducts.add(productId);
                console.log('Link Wizard Composite: Expanded product', productId);
            }

            // Trigger re-render if callback is set.
            if (window.LWWCAddons.complexProducts.onStateChange) {
                window.LWWCAddons.complexProducts.onStateChange();
            }

            return window.lwwcCompositeExpandedProducts.has(productId);
        };

        window.LWWCAddons.complexProducts.isProductExpanded = function(productId) {
            if (!window.lwwcCompositeExpandedProducts) {
                return false;
            }
            return window.lwwcCompositeExpandedProducts.has(productId);
        };

        window.LWWCAddons.complexProducts.addCompositeProduct = function(product, componentSelections, setSelectedProducts) {
            console.log('Link Wizard Composite: addCompositeProduct called with product:', product.id);
            console.log('Link Wizard Composite: componentSelections:', componentSelections);

            if (!setSelectedProducts || typeof setSelectedProducts !== 'function') {
                console.error('Link Wizard Composite: setSelectedProducts is not a function');
                return;
            }

            // The product should already have the checkout_url and url from the React component
            const compositeProduct = {
                ...product,
                quantity: 1
            };

            // Get current selected products
            const currentProducts = window.lwwcSelectedProducts || [];
            console.log('Link Wizard Composite: Current products:', currentProducts);

            // In checkout-link mode, add as new product (allow multiple composites)
            // In add-to-cart mode, replace existing composite
            const updatedProducts = [...currentProducts, compositeProduct];
            
            console.log('Link Wizard Composite: Updated products:', updatedProducts);
            setSelectedProducts(updatedProducts);
        };

        console.log('Link Wizard Composite: Complex products integration initialized');
        console.log('Link Wizard Composite: toggleProductExpansion available:', typeof window.LWWCAddons.complexProducts.toggleProductExpansion);
        console.log('Link Wizard Composite: addCompositeProduct available:', typeof window.LWWCAddons.complexProducts.addCompositeProduct);
    }

    // Initialize when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCompositeIntegration);
    } else {
        initCompositeIntegration();
    }

    // Also initialize immediately (in case DOM is already loaded).
    initCompositeIntegration();
})();


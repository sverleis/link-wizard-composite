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
        // Wait for Link Wizard to be ready.
        if (typeof window.lwwcComplexProducts === 'undefined') {
            window.lwwcComplexProducts = {};
        }

        // Add our composite product functionality to the global object.
        window.lwwcComplexProducts.toggleProductExpansion = function(productId) {
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
            if (window.lwwcComplexProducts.onStateChange) {
                window.lwwcComplexProducts.onStateChange();
            }

            return window.lwwcCompositeExpandedProducts.has(productId);
        };

        window.lwwcComplexProducts.isProductExpanded = function(productId) {
            if (!window.lwwcCompositeExpandedProducts) {
                return false;
            }
            return window.lwwcCompositeExpandedProducts.has(productId);
        };

        console.log('Link Wizard Composite: Complex products integration initialized');
        console.log('Link Wizard Composite: toggleProductExpansion available:', typeof window.lwwcComplexProducts.toggleProductExpansion);
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


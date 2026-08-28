/**
 * Link Wizard Composite - Admin UI Entry Point
 *
 * This file exports the CompositeProductConfig component globally
 * so Link Wizard's ProductSelect component can use it when the
 * "Configure" button is clicked for composite products.
 */

import CompositeProductConfig from './components/CompositeProductConfig';

// Make the component available globally for Link Wizard.
// Link Wizard expects window.LWWCAddons.ComplexProductUI.
if ( typeof window.LWWCAddons === 'undefined' ) {
	window.LWWCAddons = {};
}

// Register our composite configuration UI as the ComplexProductUI.
window.LWWCAddons.ComplexProductUI = CompositeProductConfig;

/**
 * Link Wizard Composite - Admin UI Entry Point
 *
 * This file exports the CompositeProductConfig component globally
 * so Link Wizard's ProductSelect component can use it when the
 * "Configure" button is clicked for composite products.
 */

import CompositeProductConfig from './components/CompositeProductConfig';

// Make the component available globally for Link Wizard.
window.LWWCCompositeUI = window.LWWCCompositeUI || {};
window.LWWCCompositeUI.CompositeProductConfig = CompositeProductConfig;

console.log('Link Wizard Composite: UI components loaded');


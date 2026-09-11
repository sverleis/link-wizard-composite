=== Link Wizard for Composites ===
Contributors: sverleis
Tags: woocommerce, composite products, checkout links, add to cart
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add WooCommerce Composite Products support to Link Wizard for WooCommerce.

== Description ==

Link Wizard for Composites integrates Link Wizard for WooCommerce with the WooCommerce Composite Products extension.

Features include:

* Composite products in Link Wizard product search.
* Default composite configurations for immediate use.
* Custom component selections and quantities.
* Add-to-cart and checkout-link URL generation.
* Short mapped URLs for complex composite configurations.
* Price calculation through authenticated REST API endpoints.

This plugin requires Link Wizard for WooCommerce 2.0.0 or newer with add-on API 2.0, WooCommerce, and WooCommerce Composite Products to be installed and active.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate WooCommerce Composite Products.
3. Install and activate Link Wizard for WooCommerce 2.0.0 or newer.
4. Download the versioned ZIP from https://github.com/sverleis/link-wizard-composite/releases.
5. Upload and activate the ZIP through Plugins > Add New > Upload Plugin.
6. Open Products > Link Wizard.

== Frequently Asked Questions ==

= Does this plugin include WooCommerce Composite Products? =

No. WooCommerce Composite Products is a separate extension and must be installed and active.

= Why are composite configurations stored as mappings? =

Composite configurations contain component selections and quantities that cannot be represented by the standard WooCommerce checkout-link product token. The mapping system creates a stable short identifier and resolves it when the link is opened.

= Are the generated checkout links public? =

Yes. Generated checkout links are designed to be shared publicly. Administrative configuration and REST operations remain permission protected.

== Changelog ==

= 1.0.1 =

* Align plugin authorship metadata with Link Wizard for WooCommerce.

= 1.0.0 =

* Add production support for WooCommerce Composite Products in Link Wizard 2.0.
* Add composite discovery, validation, component configuration, mapped URLs, and checkout integration.
* Use the Composite Products cart API for validated cart insertion.
* Declare add-on API 2.0 compatibility and automate versioned release packages.

= 1.0.0-beta2 =

* Declare Link Wizard add-on API 2.0 and minimum compatible core version.
* Prevent initialization against incompatible core versions.
* Improve the core dependency notice.
* Add automated build and versioned GitHub release packages.
* Document installation from the official GitHub Releases page.

= 1.0.0-beta1 =

* Add composite product search integration.
* Add default and custom component configuration support.
* Add mapped checkout-link generation.
* Add REST endpoints for product data, URL generation, variation lookup, and price calculation.
* Add WooCommerce HPOS compatibility declaration.

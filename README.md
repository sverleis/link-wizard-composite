# Link Wizard Composite

**Version**: 1.0.0  
**Author**: Sven Leisegang  
**License**: GPL v2 or later

## What is This Plugin?

Link Wizard Composite is a focused addon for **Link Wizard for WooCommerce** that adds support for **WooCommerce Composite Products**. It enables you to create custom checkout-links with specific component selections and quantities.

## Why a Separate Plugin?

This plugin focuses solely on **Composite Products**. 

**Important Note**: WooCommerce Composite Products and Product Bundles do **not** natively support checkout-links. This plugin adds that functionality specifically for composite products, enabling:
- Custom component selections
- Custom quantities per component
- Facebook Commerce compatible URLs
- Direct checkout-link support

Bundle products and other product types are not supported by this plugin.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- **Link Wizard for WooCommerce** (core plugin)
- **WooCommerce**
- **WooCommerce Composite Products** (premium plugin)

## What We Built (Step by Step)

### Step 1: Plugin Foundation ✅

**File**: `link-wizard-composite.php`

**What it does**:
- Defines the plugin metadata (name, version, author, etc.)
- Sets up plugin constants for paths and URLs
- Checks for required dependencies (Link Wizard, WooCommerce, Composite Products)
- Shows admin notices if dependencies are missing
- Initializes the main handler class when all dependencies are met

**Key Learning Points**:
1. **Plugin Headers**: WordPress reads the comment block at the top to identify the plugin
2. **Constants**: We define paths and URLs once so we can use them throughout the plugin
3. **Dependency Checking**: Always check if required plugins are active before running your code
4. **Admin Notices**: Helpful messages guide users if something is missing

### Step 2: Main Handler Class ✅

**File**: `includes/class-lwwc-composite-handler.php`

**What it does**:
- Acts as the central coordinator for all composite product functionality
- Registers the plugin with Link Wizard's addon system
- Manages initialization of all components
- Tells Link Wizard what capabilities this plugin provides

**Key Learning Points**:
1. **Handler Pattern**: A single class coordinates all plugin functionality
2. **Capability Registration**: Using filters to tell Link Wizard what we support
3. **Plugin Slug Checking**: Only respond when Link Wizard asks about our specific plugin
4. **Capabilities Array**: Declare product types and features we support

**Code Highlights**:
```php
// Register what we can do
'product_types' => array( 'composite' ),  // We handle composite products
'features' => array(
    'checkout_links',     // We enable checkout-links
    'custom_components',  // Custom component selections
    'price_calculation',  // Calculate composite prices
)
```

### Next Steps

We'll continue building:
- **Step 3**: URL mapping system for composite products
- **Step 4**: Component selection handling
- **Step 5**: Price calculation
- **Step 6**: Admin UI integration
- **Step 7**: REST API endpoints

Each step will be explained clearly so you understand exactly what's happening!

## Installation

1. Upload the `link-wizard-composite` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Link Wizard and WooCommerce Composite Products are also active

## Current Status

**Completed**:
- ✅ Plugin foundation
- ✅ Dependency checking
- ✅ Admin notices

**Coming Next**:
- ⏳ URL mapping system
- ⏳ Composite product handler
- ⏳ Admin UI
- ⏳ REST API

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.


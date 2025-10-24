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

### Step 3: URL Mapping System ✅

**File**: `includes/class-lwwc-composite-url-mapper.php`

**What it does**:
- Creates a database table to store composite configurations
- Generates unique mapping IDs (e.g., `cp139_3e3a7ecc`)
- Converts complex configurations into simple URLs
- Intercepts checkout-link requests and applies configurations

**Key Learning Points**:
1. **Database Tables**: Creating custom tables to store plugin data
2. **Mapping IDs**: Using product ID + hash for unique, consistent IDs
3. **Template Redirect Hook**: Intercepting requests before WordPress loads the page
4. **Configuration Storage**: JSON encoding complex data for database storage

**The Magic**:
```
User configures: Component A = Product 72, Qty 2
↓
We store: cp139_3e3a7ecc → {"components": {"1": {"product_id": 72, "quantity": 2}}}
↓
We generate: checkout-link/?products=cp139_3e3a7ecc:1
↓
User visits URL
↓
We intercept: Look up cp139_3e3a7ecc in database
↓
We apply: Set up composite with saved configuration
↓
Checkout: User sees configured composite product
```

### Step 4: Composite Product Handler ✅

**File**: `includes/class-lwwc-composite-product-handler.php`

**What it does**:
- Gets composite product data (components, options, pricing)
- Generates checkout-link URLs with mapped configurations
- Calculates total price based on selected components
- Formats data for frontend display

**Key Learning Points**:
1. **Component Structure**: Each composite has configurable parts (components)
2. **Options**: Each component has selectable products (options)
3. **Data Formatting**: Converting between frontend format and storage format
4. **WooCommerce Integration**: Understanding how Composite Products expects data

**The Critical Parameters**:
```php
// These are the magic parameters WooCommerce Composite Products reads:
$_GET['wccp_component_1'] = 72;  // Component 1 uses Product 72
$_GET['wccp_quantity_1'] = 2;    // With quantity 2
```

**Enhanced URL Mapper**:
- Now actually applies configurations when URLs are visited
- Sets up `$_GET` parameters that WooCommerce understands
- Converts mapped IDs into real composite configurations

**Smart URL Generation**:
- ✅ Default configuration: `?products=139:1` (simple, no mapping needed)
- ✅ Custom configuration: `?products=cp139_3e3a7ecc:1` (uses mapping system)
- Benefits: Simpler URLs when possible, less database storage, better debugging

### Next Steps

We'll continue building:
- **Step 5**: REST API endpoints (for frontend communication)
- **Step 6**: Admin UI (component selection interface)
- **Step 7**: Price calculation enhancement (using WooCommerce's native methods)

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


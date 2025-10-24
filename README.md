# Link Wizard Composite

**Version**: 1.0.0  
**Author**: Sven Leisegang  
**License**: GPL v2 or later

## What is This Plugin?

Link Wizard Composite is a focused addon for **Link Wizard for WooCommerce** that adds support for **WooCommerce Composite Products**. It enables you to:
- **Add composite products immediately** with default configuration (no setup required)
- **Create custom checkout-links** with specific component selections and quantities
- **Use both checkout-links and add-to-cart links** with composite products

## Why a Separate Plugin?

This plugin focuses solely on **Composite Products**. 

**Important Note**: WooCommerce Composite Products and Product Bundles do **not** natively support checkout-links. This plugin adds that functionality specifically for composite products, enabling:
- ✅ **Immediate Use**: Composite products appear in search results with default configuration ready
- ✅ **Custom component selections**: Choose specific products for each component
- ✅ **Custom quantities per component**: Set exact quantities for each part
- ✅ **Facebook Commerce compatible URLs**: Works with Meta/Facebook shops
- ✅ **Direct checkout-link support**: Links go straight to cart/checkout with product configured

**How It Works**:
1. Search for a composite product in Link Wizard
2. Click to add it → Uses default component configuration automatically
3. OR: Configure custom components → Creates a custom URL for your specific setup
4. Works for both checkout-links and add-to-cart links

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

**Smart URL Generation** (Updated):
- ✅ **All composite products use mapping system**: `?products=cp139_3e3a7ecc:1`
- ✅ Why? WooCommerce's checkout-link doesn't understand composite components natively
- ✅ Our URL mapper intercepts and applies component configuration automatically
- ✅ Works for both default and custom configurations seamlessly

### Step 4.5: Default Configuration Support ✅

**File**: `includes/class-lwwc-composite-product-handler.php` (enhanced)

**What it does**:
- Enables immediate addition of composite products without configuration
- Retrieves default component selections automatically
- Creates mapped URLs even for default configurations
- Adds `checkout_url` to search results so products work immediately

**Key Methods Added**:
```php
get_default_component_selections( $product )  // Gets default options for each component
get_search_results( $product )                // Now includes checkout_url for instant use
```

**How It Works**:
1. When a composite product is searched, we generate a default checkout URL
2. URL uses the mapping system with default component selections
3. User clicks product → It's immediately added to selected products
4. Works for both checkout-links and add-to-cart links
5. No UI configuration required for basic use

**User Experience**:
- **Before**: Composite products appeared in search but couldn't be added
- **After**: Composite products work like simple products (click and add!)
- **Custom Config**: Still available when user needs specific component choices

### Step 5: REST API Endpoints ✅

**File**: `includes/class-lwwc-composite-rest-api.php`

**What it does**:
- Creates the communication bridge between React frontend and PHP backend
- Registers three REST API endpoints for composite product functionality
- Handles authentication and permission checking
- Enables frontend to request data and perform actions

**The Three Endpoints**:

1. **GET `/lwwc-composite/v1/product/{id}`** - Get Product Data
   - Returns complete composite product information
   - Includes all components with their options
   - Provides quantity limits and default selections
   - Used when user expands a composite product in UI

2. **POST `/lwwc-composite/v1/generate-url`** - Generate Checkout URL
   - Accepts component selections from frontend
   - Creates mapped URL with configuration
   - Returns Facebook/Meta-compatible checkout link
   - Used when user configures components and clicks "Add"

3. **POST `/lwwc-composite/v1/calculate-price`** - Calculate Price
   - Accepts component selections
   - Calculates total price based on selected options
   - Returns formatted price HTML
   - Used to update price as user changes selections

**Frontend Usage Example**:
```javascript
// Get composite product data
const productData = await apiFetch({
  path: '/lwwc-composite/v1/product/139'
});

// Generate checkout URL
const urlData = await apiFetch({
  path: '/lwwc-composite/v1/generate-url',
  method: 'POST',
  data: {
    product_id: 139,
    component_selections: {
      '1757251116': { product_id: 72, quantity: 1 },
      '1757251203': { product_id: 86, quantity: 2 }
    },
    quantity: 1
  }
});

// Calculate price
const priceData = await apiFetch({
  path: '/lwwc-composite/v1/calculate-price',
  method: 'POST',
  data: {
    product_id: 139,
    component_selections: {
      '1757251116': { product_id: 72, quantity: 1 },
      '1757251203': { product_id: 86, quantity: 2 }
    }
  }
});
```

**Key Features**:
- ✅ Permission checking (requires `manage_woocommerce` capability)
- ✅ Input validation for all parameters
- ✅ Error handling with proper HTTP status codes
- ✅ Supports both REST API and UI data formats
- ✅ Returns consistent JSON responses

**Integration**:
- Initialized in main handler class
- Automatically registered when plugin loads
- Uses existing product handler for business logic
- Ready for frontend React components to consume

### Next Steps

We'll continue building:
- **Step 6**: Admin UI (component selection interface)
- **Step 7**: Price calculation enhancement (using WooCommerce's native methods)

Each step will be explained clearly so you understand exactly what's happening!

## Installation

1. Upload the `link-wizard-composite` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Link Wizard and WooCommerce Composite Products are also active

## Current Status

**Completed**:
- ✅ Plugin foundation with dependency checking and admin notices
- ✅ Main handler class with capability registration
- ✅ URL mapping system with database storage
- ✅ Composite product handler with product interface implementation
- ✅ **Search functionality - Composite products now appear in Link Wizard!**
- ✅ **Default configuration support - Products work immediately without setup!**
- ✅ **REST API endpoints - Frontend can now communicate with backend!**

**Coming Next**:
- ⏳ Admin UI (component selection interface)
- ⏳ Frontend integration with Link Wizard
- ⏳ Price calculation enhancement

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.


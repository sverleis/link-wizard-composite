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

### Next Steps

We'll build this plugin piece by piece, adding:
- URL mapping system for composite products
- Component selection handling
- Price calculation
- Admin UI integration
- REST API endpoints

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


# GitHub Repository Setup for Link Wizard Composite

## Quick Setup Instructions

### 1. Create GitHub Repository

Go to: https://github.com/new

**Repository Details:**
- **Name**: `link-wizard-composite`
- **Description**: Addon for Link Wizard that adds support for WooCommerce Composite Products with checkout-links and custom component configurations
- **Visibility**: Public (for WordPress.org distribution)
- **Initialize**: Do NOT initialize with README (we already have one)

### 2. Add Remote and Push

Once you've created the repository on GitHub, run these commands:

```bash
cd /path/to/link-wizard-composite
git remote add origin https://github.com/YOUR_USERNAME/link-wizard-composite.git
git push -u origin trunk
```

Replace `YOUR_USERNAME` with your GitHub username.

### 3. Verify

After pushing, visit your repository and verify:
- ✅ All files are present
- ✅ README.md displays correctly
- ✅ Commit history is intact

---

## Repository Information

**Current Branch**: `trunk` (main development branch)

**Key Files:**
- `link-wizard-composite.php` - Main plugin file
- `README.md` - Documentation and step-by-step guide
- `includes/class-lwwc-composite-handler.php` - Main handler
- `includes/class-lwwc-composite-product-handler.php` - Product handler with URL generation
- `includes/class-lwwc-composite-url-mapper.php` - URL mapping system
- `includes/class-lwwc-composite-rest-api.php` - REST API endpoints

**Recent Features:**
- ✅ Product handler interface implementation
- ✅ Search functionality (composite products appear in Link Wizard)
- ✅ Default configuration support (click + Add without setup)
- ✅ REST API endpoints (3 endpoints for frontend communication)
- ✅ URL generation with proper format
- ✅ Add-to-cart and checkout-link support
- ✅ Product replacement in add-to-cart mode

**Total Commits**: 12 commits on trunk branch

---

## Working with Both Repositories

Since you'll be working on both repositories simultaneously:

### Core Plugin (link-wizard-for-woocommerce)
- **Branch**: `feature/product-bundles`
- **Remote**: https://github.com/sverleis/link-wizard.git
- **Purpose**: Testing ground for addon architecture improvements

### Composite Plugin (link-wizard-composite)
- **Branch**: `trunk`
- **Remote**: To be set up
- **Purpose**: Reference implementation for addon developers

### Workflow

1. **Make changes to core** → Test with composite plugin
2. **Composite plugin exposes issues** → Fix in core
3. **Core improvements** → Update composite to use new features
4. **Push both** → Keep them in sync

This ensures the core plugin's addon system is battle-tested with a real-world addon!

---

## Next Steps

After setting up the GitHub repository:

1. Add repository topics on GitHub:
   - `wordpress`
   - `woocommerce`
   - `composite-products`
   - `link-wizard`
   - `checkout-links`

2. Update plugin header (if needed) with repository URL

3. Consider adding:
   - `.github/workflows/` for CI/CD
   - `CONTRIBUTING.md` for contributors
   - `CHANGELOG.md` for version history

---

**Questions?** The core plugin is already pushed and ready. Just create the composite plugin repository and run the commands above!


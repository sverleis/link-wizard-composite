import React, { Component } from 'react';
import apiFetch from '@wordpress/api-fetch';

// Access shared VariableProductSelector from core plugin
// Available as window.LWWCComponents.VariableProductSelector

/**
 * Composite Product Configuration Component.
 *
 * This component displays when a user clicks "Configure" on a composite product.
 * It shows:
 * - Dropdowns for each component (showing available products)
 * - Quantity selectors (with min/max validation)
 * - Real-time price calculation
 * - "Add to Cart" or "Add Product" button to apply configuration
 *
 * Props from Link Wizard:
 * @param {Object} product - The composite product
 * @param {string} linkType - 'addToCart' or 'checkoutLink'
 * @param {Function} handleAddCompositeProduct - Callback to add composite with selections
 * @param {Function} toggleProductExpansion - Callback to close configuration panel
 * @param {Function} isProductExpanded - Check if product is expanded
 * @param {Function} setSelectedProducts - Update selected products (for replacement in add-to-cart)
 */
class CompositeProductConfig extends Component {
    constructor(props) {
        super(props);
        this.state = {
            components: [],
            selections: {},
            isLoading: true,
            calculatedPrice: null,
            isCalculating: false,
        };
    }

    componentDidMount() {
        this.loadCompositeData();
    }

    componentDidUpdate(prevProps, prevState) {
        // Calculate price when selections change.
        if (prevState.selections !== this.state.selections && Object.keys(this.state.selections).length > 0) {
            this.calculatePrice();
        }
    }

    /**
     * Load composite product data from REST API.
     */
    loadCompositeData = async () => {
        this.setState({ isLoading: true });

        try {
            const data = await apiFetch({
                path: `/lwwc-composite/v1/product/${this.props.product.id}`
            });

            console.log('Composite product data loaded:', data);

            if (data.components) {
                this.setState({ components: data.components });

                // Initialize selections with first/default option for each component.
                const initialSelections = {};
                data.components.forEach(component => {
                    if (component.options && component.options.length > 0) {
                        const defaultOption = component.options[0];
                        initialSelections[component.id] = {
                            product_id: defaultOption.id,
                            name: defaultOption.name, // Store the product name
                            quantity: component.quantity.min || 1
                        };
                    }
                });

                this.setState({ selections: initialSelections });
            }
        } catch (error) {
            console.error('Error loading composite product data:', error);
        } finally {
            this.setState({ isLoading: false });
        }
    };

    /**
     * Calculate price based on current selections.
     */
    calculatePrice = async () => {
        this.setState({ isCalculating: true });

        try {
            const response = await apiFetch({
                path: '/lwwc-composite/v1/calculate-price',
                method: 'POST',
                data: {
                    product_id: this.props.product.id,
                    component_selections: this.state.selections
                }
            });

            if (response.price_html) {
                this.setState({ calculatedPrice: response.price_html });
            }
        } catch (error) {
            console.error('Error calculating price:', error);
        } finally {
            this.setState({ isCalculating: false });
        }
    };

    /**
     * Handle component option selection change.
     * 
     * @param {String} componentId - The component ID
     * @param {Number|Object} productIdOrVariation - Product ID (from dropdown) or variation object (from VariableProductSelector)
     */
    handleOptionChange = (componentId, productIdOrVariation) => {
        // Check if we received a variation object (from VariableProductSelector)
        if (typeof productIdOrVariation === 'object' && productIdOrVariation !== null) {
            // This is a variation object from VariableProductSelector
            const variation = productIdOrVariation;
            console.log('Composite: handleOptionChange received variation object:', variation);
            
            this.setState(prevState => ({
                selections: {
                    ...prevState.selections,
                    [componentId]: {
                        ...prevState.selections[componentId],
                        product_id: parseInt(variation.id),
                        name: variation.name || '' // Use variation name
                    }
                }
            }));
        } else {
            // This is a product ID from the dropdown
            const productId = productIdOrVariation;
            
            // Find the selected option to get its name
            const component = this.state.components.find(c => c.id === componentId);
            const selectedOption = component?.options?.find(o => o.id === parseInt(productId));
            
            this.setState(prevState => ({
                selections: {
                    ...prevState.selections,
                    [componentId]: {
                        ...prevState.selections[componentId],
                        product_id: parseInt(productId),
                        name: selectedOption?.name || '' // Store the product name
                    }
                }
            }));
        }
    };

    /**
     * Handle component quantity change.
     */
    handleQuantityChange = (componentId, quantity) => {
        this.setState(prevState => ({
            selections: {
                ...prevState.selections,
                [componentId]: {
                    ...prevState.selections[componentId],
                    quantity: parseInt(quantity)
                }
            }
        }));
    };

    /**
     * Strip HTML tags and get clean price text.
     * Handles sale prices by showing: Original price (strikethrough) → Sale price
     */
    cleanPriceHtml = (priceHtml) => {
        if (!priceHtml) return '';
        
        // Create a temporary div to parse HTML
        const div = document.createElement('div');
        div.innerHTML = priceHtml;
        
        // Check if this is a sale price (has <del> and <ins> tags)
        const delElement = div.querySelector('del');
        const insElement = div.querySelector('ins');
        
        if (delElement && insElement) {
            // Sale price: extract both original and sale prices
            const originalPrice = delElement.textContent.trim();
            const salePrice = insElement.textContent.trim();
            
            // Calculate discount percentage
            const originalValue = parseFloat(originalPrice.replace(/[^\d.,]/g, '').replace(',', '.'));
            const saleValue = parseFloat(salePrice.replace(/[^\d.,]/g, '').replace(',', '.'));
            
            if (originalValue && saleValue && originalValue > saleValue) {
                const discount = ((originalValue - saleValue) / originalValue * 100).toFixed(1);
                // Format: "R16,00 (11.1% off)"
                return `${salePrice} (${discount}% off)`;
            }
            
            // Fallback: just show sale price if calculation fails
            return salePrice;
        }
        
        // Not a sale price - just clean up the text
        let text = div.textContent || div.innerText || '';
        
        // Clean up extra whitespace
        text = text.replace(/\s+/g, ' ').trim();
        
        // Remove screen reader text
        text = text.replace(/Original price was:\s*/gi, '');
        text = text.replace(/Current price is:\s*/gi, '');
        
        // Remove duplicate "Price range:" text for variable products
        text = text.replace(/Price range:.*$/i, '');
        
        // Clean up any remaining extra whitespace
        text = text.replace(/\s+/g, ' ').trim();
        
        return text;
    };

    /**
     * Handle Add/Update Product button click.
     */
    handleUpdate = async () => {
        const { product, linkType, handleAddCompositeProduct, toggleProductExpansion, setSelectedProducts, isProductSelected } = this.props;

        try {
            const response = await apiFetch({
                path: '/lwwc-composite/v1/generate-url',
                method: 'POST',
                data: {
                    product_id: product.id,
                    component_selections: this.state.selections,
                    quantity: 1
                }
            });

            if (response.checkout_url) {
                // Create a unique ID for this specific configuration
                // Use the checkout_url's hash to ensure each configuration is unique
                const urlMatch = response.checkout_url.match(/cp\d+_([a-f0-9]+)/);
                const configHash = urlMatch ? urlMatch[1] : Date.now();
                const uniqueId = `${product.id}_${configHash}`;
                
                const updatedProduct = {
                    ...product,
                    unique_id: uniqueId, // Unique identifier for this configuration
                    checkout_url: response.checkout_url,
                    url: response.checkout_url,
                    component_selections: this.state.selections,
                    calculated_price: this.state.calculatedPrice,
                    quantity: 1
                };

                if (linkType === 'addToCart') {
                    // In add-to-cart mode: Always replace existing composite product
                    console.log('Composite: Replacing product in add-to-cart mode');
                    setSelectedProducts(prev => {
                        // Remove any existing composite products
                        const filtered = prev.filter(p => p.type !== 'composite');
                        // Add the new configured composite
                        return [...filtered, updatedProduct];
                    });
                } else {
                    // In checkout-link mode: Check if we're editing or adding new
                    if (isProductSelected) {
                        // Editing existing composite: Replace it
                        console.log('Composite: Updating existing product in checkout-link mode');
                        setSelectedProducts(prev => {
                            return prev.map(p => {
                                // Replace the composite product with the same unique_id
                                // (or same id if no unique_id exists for backwards compatibility)
                                if (p.type === 'composite' && 
                                    ((p.unique_id && p.unique_id === product.unique_id) || 
                                     (!p.unique_id && p.id === product.id))) {
                                    return updatedProduct;
                                }
                                return p;
                            });
                        });
                    } else {
                        // Adding new composite: Add it to the list
                        console.log('Composite: Adding new product in checkout-link mode');
                        console.log('Composite: Updated product with URL:', updatedProduct.checkout_url);
                        
                        // Convert selections to component selections format for handleAddCompositeProduct
                        const componentSelections = Object.keys(this.state.selections).map(componentId => {
                            const selection = this.state.selections[componentId];
                            const component = this.state.components.find(c => c.id === componentId);
                            const option = component?.options?.find(o => o.id === selection.product_id);
                            
                            return {
                                id: componentId,
                                selected_option: option || { id: selection.product_id },
                                quantity: selection.quantity
                            };
                        });

                        // Pass the updatedProduct with the new checkout_url, not the original product
                        handleAddCompositeProduct(updatedProduct, componentSelections);
                    }
                }

                // Close the configuration panel
                toggleProductExpansion(product.id);
            }
        } catch (error) {
            console.error('Error generating URL:', error);
        }
    };

    render() {
        const { product, linkType, toggleProductExpansion, isProductExpanded, isProductSelected } = this.props;
        const { components, selections, isLoading, calculatedPrice, isCalculating } = this.state;

        // Only render if product is expanded
        if (!isProductExpanded || !isProductExpanded(product.id)) {
            return null;
        }

        if (isLoading) {
            return (
                <div className="lwwc-composite-config-loading">
                    <span className="spinner is-active"></span>
                    Loading composite product configuration...
                </div>
            );
        }

        // Determine button text based on link type and whether product is already selected
        const isEditing = isProductSelected;
        let buttonText;
        if (linkType === 'addToCart') {
            buttonText = isEditing ? 'Update Product' : 'Add to Cart';
        } else {
            buttonText = isEditing ? 'Update Product' : 'Add Product';
        }

        return (
            <div className="lwwc-composite-config">
                <div className="lwwc-composite-config-header">
                    <h3>Configure: {product.name}</h3>
                    {calculatedPrice && (
                        <div className="lwwc-composite-config-price">
                            <strong>Total Price: </strong>
                            <span dangerouslySetInnerHTML={{ __html: calculatedPrice }} />
                            {isCalculating && (
                                <span className="spinner is-active" style={{ float: 'none', marginLeft: '8px' }}></span>
                            )}
                        </div>
                    )}
                </div>

                <div className="lwwc-composite-config-components">
                    {components.map(component => (
                        <div key={component.id} className="lwwc-composite-config-component">
                            <label className="lwwc-composite-config-component-label">
                                <strong>{component.title}</strong>
                                {component.description && (
                                    <span className="lwwc-composite-config-component-description">
                                        {component.description}
                                    </span>
                                )}
                            </label>

                            <div className="lwwc-composite-config-component-controls">
                                {/* Component selection dropdown (if multiple options) */}
                                {component.options && component.options.length > 1 ? (
                                    <select
                                        className="lwwc-composite-config-component-select"
                                        value={selections[component.id]?.product_id || ''}
                                        onChange={(e) => this.handleOptionChange(component.id, e.target.value)}
                                    >
                                        {component.options.map(option => (
                                            <option key={option.id} value={option.id}>
                                                {option.name}
                                                {option.price && ` - ${this.cleanPriceHtml(option.price)}`}
                                            </option>
                                        ))}
                                    </select>
                                ) : component.options && component.options.length === 1 ? (
                                    <div className="lwwc-composite-config-single-option">
                                        {component.options[0].name}
                                        {component.options[0].price && ` - ${this.cleanPriceHtml(component.options[0].price)}`}
                                    </div>
                                ) : (
                                    <div className="lwwc-composite-config-no-options">
                                        No options available
                                    </div>
                                )}

                                {/* Quantity input */}
                                <div className="lwwc-composite-config-quantity">
                                    <span className="lwwc-composite-config-quantity-range">
                                        Min: {component.quantity.min}, Max: {component.quantity.max}
                                    </span>
                                    <label>Qty:</label>
                                    <input
                                        type="number"
                                        min={component.quantity.min}
                                        max={component.quantity.max}
                                        value={selections[component.id]?.quantity || component.quantity.min}
                                        onChange={(e) => this.handleQuantityChange(component.id, e.target.value)}
                                        className="lwwc-composite-config-quantity-input"
                                    />
                                </div>
                            </div>

                            {/* Variable Product Selector (full width, below dropdown and quantity) */}
                            {(() => {
                                // Check if the selected option is a variable product
                                const selectedProductId = selections[component.id]?.product_id;
                                const selectedOption = component.options?.find(opt => opt.id === parseInt(selectedProductId));
                                
                                // Only show variable product selector if:
                                // 1. An option is selected AND it's a variable product, OR
                                // 2. There's only one option AND it's a variable product
                                const shouldShowVariableSelector = selectedOption?.type === 'variable' || 
                                    (component.options?.length === 1 && component.options[0].type === 'variable');
                                
                                if (shouldShowVariableSelector && window.LWWCComponents && window.LWWCComponents.VariableProductSelector) {
                                    const variableProduct = selectedOption || component.options[0];
                                    console.log('Composite: Rendering VariableProductSelector for component', component.id, 'product:', variableProduct);
                                    
                                        return React.createElement(window.LWWCComponents.VariableProductSelector, {
                                            product: variableProduct,
                                            onVariationSelect: (variation) => {
                                                console.log('Composite: Variation selected', variation);
                                                this.handleOptionChange(component.id, variation); // Pass full variation object
                                            },
                                            componentId: component.id,
                                            i18n: window.lwwcI18n,
                                            allowAnyAttributes: true // Composite products can handle "Any" attributes
                                        });
                                }
                                return null;
                            })()}
                        </div>
                    ))}
                </div>

                <div className="lwwc-composite-config-actions">
                    <button
                        type="button"
                        className="button button-primary lwwc-composite-config-update"
                        onClick={this.handleUpdate}
                    >
                        <span className="dashicons dashicons-yes"></span>
                        {buttonText}
                    </button>
                    <button
                        type="button"
                        className="button button-secondary lwwc-composite-config-cancel"
                        onClick={() => toggleProductExpansion(product.id)}
                    >
                        <span className="dashicons dashicons-no"></span>
                        Cancel
                    </button>
                </div>
            </div>
        );
    }
}

export default CompositeProductConfig;

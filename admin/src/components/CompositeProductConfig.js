import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Composite Product Configuration Component.
 *
 * This component displays when a user clicks "Configure" on a composite product.
 * It shows:
 * - Dropdowns for each component (showing available products)
 * - Quantity selectors (with min/max validation)
 * - Real-time price calculation
 * - "Update Product" button to apply configuration
 *
 * @param {Object} product - The composite product
 * @param {Function} onUpdate - Callback when user updates configuration
 * @param {Function} onCancel - Callback when user cancels
 */
const CompositeProductConfig = ({ product, onUpdate, onCancel }) => {
    const [components, setComponents] = useState([]);
    const [selections, setSelections] = useState({});
    const [isLoading, setIsLoading] = useState(true);
    const [calculatedPrice, setCalculatedPrice] = useState(null);
    const [isCalculating, setIsCalculating] = useState(false);

    // Load composite product data when component mounts.
    useEffect(() => {
        loadCompositeData();
    }, [product.id]);

    // Calculate price whenever selections change.
    useEffect(() => {
        if (Object.keys(selections).length > 0) {
            calculatePrice();
        }
    }, [selections]);

    /**
     * Load composite product data from REST API.
     */
    const loadCompositeData = async () => {
        setIsLoading(true);

        try {
            const data = await apiFetch({
                path: `/lwwc-composite/v1/product/${product.id}`
            });

            console.log('Composite product data loaded:', data);

            if (data.components) {
                setComponents(data.components);

                // Initialize selections with default options.
                const initialSelections = {};
                data.components.forEach(component => {
                    if (component.options && component.options.length > 0) {
                        // Use first option as default.
                        const defaultOption = component.options[0];
                        initialSelections[component.id] = {
                            product_id: defaultOption.id,
                            quantity: component.quantity.min || 1
                        };
                    }
                });

                setSelections(initialSelections);
            }
        } catch (error) {
            console.error('Error loading composite product data:', error);
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * Calculate price based on current selections.
     */
    const calculatePrice = async () => {
        setIsCalculating(true);

        try {
            const response = await apiFetch({
                path: '/lwwc-composite/v1/calculate-price',
                method: 'POST',
                data: {
                    product_id: product.id,
                    component_selections: selections
                }
            });

            if (response.price_html) {
                setCalculatedPrice(response.price_html);
            }
        } catch (error) {
            console.error('Error calculating price:', error);
        } finally {
            setIsCalculating(false);
        }
    };

    /**
     * Handle component selection change.
     */
    const handleComponentChange = (componentId, productId) => {
        setSelections(prev => ({
            ...prev,
            [componentId]: {
                ...prev[componentId],
                product_id: parseInt(productId)
            }
        }));
    };

    /**
     * Handle quantity change.
     */
    const handleQuantityChange = (componentId, quantity) => {
        setSelections(prev => ({
            ...prev,
            [componentId]: {
                ...prev[componentId],
                quantity: parseInt(quantity)
            }
        }));
    };

    /**
     * Handle Update Product button click.
     */
    const handleUpdate = async () => {
        // Generate checkout URL with custom configuration.
        try {
            const response = await apiFetch({
                path: '/lwwc-composite/v1/generate-url',
                method: 'POST',
                data: {
                    product_id: product.id,
                    component_selections: selections,
                    quantity: 1
                }
            });

            if (response.checkout_url) {
                // Update the product with new URL and configuration.
                onUpdate({
                    ...product,
                    checkout_url: response.checkout_url,
                    url: response.checkout_url,
                    component_selections: selections,
                    calculated_price: calculatedPrice
                });
            }
        } catch (error) {
            console.error('Error generating URL:', error);
        }
    };

    if (isLoading) {
        return (
            <div className="lwwc-composite-config-loading">
                <span className="spinner is-active"></span>
                Loading composite product configuration...
            </div>
        );
    }

    return (
        <div className="lwwc-composite-config">
            <div className="lwwc-composite-config-header">
                <h3>Configure: {product.name}</h3>
                {calculatedPrice && (
                    <div className="lwwc-composite-config-price">
                        <strong>Total Price: </strong>
                        <span dangerouslySetInnerHTML={{ __html: calculatedPrice }}></span>
                        {isCalculating && <span className="spinner is-active" style={{ float: 'none', marginLeft: '8px' }}></span>}
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
                            {/* Component Product Dropdown */}
                            {component.options && component.options.length > 1 ? (
                                <select
                                    className="lwwc-composite-config-component-select"
                                    value={selections[component.id]?.product_id || ''}
                                    onChange={(e) => handleComponentChange(component.id, e.target.value)}
                                >
                                    {component.options.map(option => (
                                        <option key={option.id} value={option.id}>
                                            {option.name}
                                            {option.price && ` - ${option.price}`}
                                        </option>
                                    ))}
                                </select>
                            ) : component.options && component.options.length === 1 ? (
                                <div className="lwwc-composite-config-single-option">
                                    {component.options[0].name}
                                    {component.options[0].price && ` - ${component.options[0].price}`}
                                </div>
                            ) : (
                                <div className="lwwc-composite-config-no-options">
                                    No options available
                                </div>
                            )}

                            {/* Quantity Selector */}
                            <div className="lwwc-composite-config-quantity">
                                <label>Qty:</label>
                                <input
                                    type="number"
                                    min={component.quantity.min}
                                    max={component.quantity.max}
                                    value={selections[component.id]?.quantity || component.quantity.min}
                                    onChange={(e) => handleQuantityChange(component.id, e.target.value)}
                                    className="lwwc-composite-config-quantity-input"
                                />
                                <span className="lwwc-composite-config-quantity-range">
                                    (Min: {component.quantity.min}, Max: {component.quantity.max})
                                </span>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="lwwc-composite-config-actions">
                <button
                    type="button"
                    className="button button-primary lwwc-composite-config-update"
                    onClick={handleUpdate}
                >
                    <span className="dashicons dashicons-yes"></span>
                    Update Product
                </button>
                <button
                    type="button"
                    className="button button-secondary lwwc-composite-config-cancel"
                    onClick={onCancel}
                >
                    <span className="dashicons dashicons-no"></span>
                    Cancel
                </button>
            </div>
        </div>
    );
};

export default CompositeProductConfig;


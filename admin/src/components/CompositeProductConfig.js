import React, { Component } from 'react';
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
class CompositeProductConfig extends Component {
    constructor(props) {
        super(props);
        this.state = {
            components: [],
            selections: {},
            isLoading: true,
            calculatedPrice: null,
            isCalculating: false
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
     */
    handleOptionChange = (componentId, productId) => {
        this.setState(prevState => ({
            selections: {
                ...prevState.selections,
                [componentId]: {
                    ...prevState.selections[componentId],
                    product_id: parseInt(productId)
                }
            }
        }));
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
     * Handle Update Product button click.
     */
    handleUpdate = async () => {
        try {
            const response = await apiFetch({
                path: '/lwwc-composite/v1/generate-url',
                method: 'POST',
                data: {
                    product_id: this.props.product.id,
                    component_selections: this.state.selections,
                    quantity: 1
                }
            });

            if (response.checkout_url) {
                // Call the onUpdate callback with the updated product data.
                this.props.onUpdate({
                    ...this.props.product,
                    checkout_url: response.checkout_url,
                    url: response.checkout_url,
                    component_selections: this.state.selections,
                    calculated_price: this.state.calculatedPrice
                });
            }
        } catch (error) {
            console.error('Error generating URL:', error);
        }
    };

    render() {
        const { product, onCancel } = this.props;
        const { components, selections, isLoading, calculatedPrice, isCalculating } = this.state;

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

                                {/* Quantity input */}
                                <div className="lwwc-composite-config-quantity">
                                    <label>Qty:</label>
                                    <input
                                        type="number"
                                        min={component.quantity.min}
                                        max={component.quantity.max}
                                        value={selections[component.id]?.quantity || component.quantity.min}
                                        onChange={(e) => this.handleQuantityChange(component.id, e.target.value)}
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
                        onClick={this.handleUpdate}
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
    }
}

export default CompositeProductConfig;

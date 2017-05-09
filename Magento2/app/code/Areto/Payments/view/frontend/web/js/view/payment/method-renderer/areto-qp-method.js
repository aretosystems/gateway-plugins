/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Areto_Payments/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        additionalValidators,
        quote,
        customerData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'Areto_Payments/payment/qp'
            },
            /** Redirect to Areto */
            continueToAreto: function () {
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    setPaymentMethodAction(this.getData(), this.messageContainer).done(
                        function (response) {
                            if (response.hasOwnProperty('order_id')) {
                                customerData.invalidate(['cart']);
                                $.mage.redirect(
                                    window.checkoutConfig.payment.payex_cc.redirectUrl + '?order_id=' + response.order_id
                                );
                            }
                        }
                    );

                    return false;
                }
            }
        });
    }
);

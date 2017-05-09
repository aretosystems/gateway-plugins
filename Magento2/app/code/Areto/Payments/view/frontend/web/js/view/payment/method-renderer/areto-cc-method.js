/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Areto_Payments/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'jquery.payment',
        'mage/calendar',
        'jquery/validate'
    ],
    function (ko, $, Component, setPaymentMethodAction, selectPaymentMethodAction, quote, checkoutData) {
        'use strict';
        var validator = null;

        return Component.extend({
            defaults: {
                self: this,
                template: 'Areto_Payments/payment/cc'
            },
            initialize: function () {
                this._super();

                var self = this;
                var code = this.getCode();

                $.validator.addMethod("cardNumber", function (value, element) {
                    return this.optional(element) || $.payment.validateCardNumber(value);
                }, "Please specify a valid credit card number.");

                $.validator.addMethod("cardExpiry", function (value, element) {
                    var expiry = $.payment.cardExpiryVal(value);
                    return this.optional(element) || $.payment.validateCardExpiry(expiry['month'], expiry['year']);
                }, "Invalid expiration date.");

                $.validator.addMethod("cardCVC", function (value, element) {
                    return this.optional(element) || $.payment.validateCardCVC(value);
                }, "Invalid CVC.");

                $(document).ready(function () {
                    window.setTimeout(function () {
                        var form = $('#payment_form_' + code);
                        /* Fancy restrictive input formatting via jQuery.payment library*/
                        $('[name="payment[cc_number]"]', form).payment('formatCardNumber');
                        $('[name="payment[cc_cvc]"]', form).payment('formatCardCVC');
                        $('[name="payment[cc_expiry]"]', form).payment('formatCardExpiry');
                        $('[name="payment[cc_dob]"]', form).datepicker({
                            changeMonth: true,
                            changeYear: true,
                            dateFormat: 'mm/dd/yy',
                            yearRange: '1900:2014'
                        });

                        self.validator = form.closest('form').validate({
                            rules: {
                                "payment[cc_number]": {
                                    required: true,
                                    cardNumber: true
                                },
                                "payment[cc_expiry]": {
                                    required: true,
                                    cardExpiry: true
                                },
                                "payment[cc_cvc]": {
                                    required: true,
                                    cardCVC: true
                                },
                                "payment[cc_dob]": {
                                    required: true,
                                    date: true
                                }
                            },
                            highlight: function (element) {
                                $(element).closest('.control').removeClass('success').addClass('error');
                            },
                            unhighlight: function (element) {
                                $(element).closest('.control').removeClass('error').addClass('success');
                            },
                            errorPlacement: function (error, element) {
                                $(element).closest('.control').append(error);
                            }
                        });
                    }, 1000);
                });

                return this;
            },
            /**
             * @override
             */
            getData: function () {
                var form = $('#payment_form_' + this.getCode());

                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'cc_number': $('[name="payment[cc_number]"]', form).val(),
                        'cc_expiry': $('[name="payment[cc_expiry]"]', form).val(),
                        'cc_cvc': $('[name="payment[cc_cvc]"]', form).val(),
                        'cc_dob': $('[name="payment[cc_dob]"]', form).val()
                    }
                };
            },
            continueToPay: function () {
                if (!this.validator.form()) {
                    return false;
                }

                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentMethodAction();
                return false;
            }
        });
    }
);

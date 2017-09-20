jQuery(document).ready(function ($) {
    $('#cc_form').hide();

    $(document).on('click', '#areto_pay', function(e) {
        $('#cc_form').show();
        e.preventDefault();
        return false;
    });

    /* Fancy restrictive input formatting via jQuery.payment library*/
    $('input[name=cardNumber]').payment('formatCardNumber');
    $('input[name=cardCVC]').payment('formatCardCVC');
    $('input[name=cardExpiry]').payment('formatCardExpiry').mask("00/00", {clearIfNotMatch: true});

    // Datepicker
    $('input[name=date_of_birth]').datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: 'mm/dd/yy',
        yearRange: '1900:2014'
    });

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

    var form = $('#payment-form');
    var validator = form.validate({
        rules: {
            cardNumber: {
                required: true,
                cardNumber: true
            },
            cardExpiry: {
                required: true,
                cardExpiry: true
            },
            cardCVC: {
                required: true,
                cardCVC: true
            },
            date_of_birth: {
                required: true,
                date: true
            },
            phone: {
                required: true,
                digits: true,
                minlength: 4
            }
        },
        highlight: function (element) {
            $(element).closest('.form-control').removeClass('success').addClass('error');
        },
        unhighlight: function (element) {
            $(element).closest('.form-control').removeClass('error').addClass('success');
        },
        errorPlacement: function (error, element) {
            $(element).closest('.form-group').append(error);
        }
    });

    form.on('submit', function(e) {
        /* Abort if invalid form data */
        if (!validator.form()) {
            e.preventDefault();
            return false;
        }

        //form.submit();
    });
});

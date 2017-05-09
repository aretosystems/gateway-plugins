<!-- Vendor libraries -->
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
<script type="text/javascript" src="catalog/view/javascript/aretocc/jquery.validate.min.js"></script>
<script type="text/javascript" src="catalog/view/javascript/aretocc/additional-methods.min.js"></script>
<script type="text/javascript" src="catalog/view/javascript/aretocc/jquery.payment.min.js"></script>

<h2><?php echo $text_title; ?></h2>
<p><?php echo $text_description; ?></p>

<div class="col-xs-12 col-md-4">
    <!-- CREDIT CARD FORM STARTS HERE -->
    <div class="panel panel-default credit-card-box">
        <div class="panel-heading display-table">
            <div class="row display-tr">
                <!-- <h3 class="panel-title display-td" >Payment Details</h3> -->
                <div class="display-td">
                    <img class="img-responsive pull-right" src="catalog/view/javascript/aretocc/creditcards_logo.png">
                </div>
            </div>
        </div>
        <div class="panel-body">
            <form role="form" id="payment-form" method="POST" action="<?php echo $action; ?>">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="cardNumber">Card Number</label>
                            <div class="input-group">
                                <input
                                    type="tel"
                                    class="form-control"
                                    name="cardNumber"
                                    placeholder="Valid Card Number"
                                    autocomplete="cc-number"
                                    required autofocus
                                />
                                <span class="input-group-addon"><i class="fa fa-credit-card"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-7 col-md-7">
                        <div class="form-group">
                            <label for="cardExpiry">Expiration Date</label>
                            <input
                                type="tel"
                                class="form-control"
                                name="cardExpiry"
                                placeholder="MM / YY"
                                autocomplete="cc-exp"
                                required
                            />
                        </div>
                    </div>
                    <div class="col-xs-5 col-md-5 pull-right">
                        <div class="form-group">
                            <label for="cardCVC">CV Code</label>
                            <input
                                type="tel"
                                class="form-control"
                                name="cardCVC"
                                placeholder="CVC"
                                autocomplete="cc-csc"
                                required
                            />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="date_of_birth">Date of birth</label>
                            <input
                                id="date_of_birth"
                                type="text"
                                class="form-control"
                                name="date_of_birth"
                                placeholder="YYYY-MM-DD"
                                data-format="MM/dd/yyyy"
                                required
                                readonly
                                style="background-color: white;"
                            />
                        </div>
                    </div>
                </div>
                <div class="row">
                    &nbsp;
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <button class="btn btn-success btn-lg btn-block" type="submit">
                            <?php echo $button_confirm; ?>
                        </button>
                    </div>
                </div>
                <div class="row" style="display:none;">
                    <div class="col-xs-12">
                        <p class="payment-errors"></p>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- CREDIT CARD FORM ENDS HERE -->
</div>
<div style="clear: both">&nbsp;</div>

<script type="application/javascript">
    jQuery(document).ready(function ($) {
        /* Fancy restrictive input formatting via jQuery.payment library*/
        $('input[name=cardNumber]').payment('formatCardNumber');
        $('input[name=cardCVC]').payment('formatCardCVC');
        $('input[name=cardExpiry]').payment('formatCardExpiry');

        // Datepicker
        $('input[name=date_of_birth]').datetimepicker({
            language: 'en',
            pickTime: false
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
</script>

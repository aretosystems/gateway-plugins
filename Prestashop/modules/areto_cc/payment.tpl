<p class="payment_module">
    <a id="areto_pay" class="bankwire" href="#areto_cc" title="{l s='Pay with credit or debit card with AretoPay' mod='areto_cc'}" rel="nofollow">
        <!-- <img src="{$this_path}logo.png" alt="{l s='Pay with credit or debit card with AretoPay' mod='areto_cc'}" style="float:left;"/> -->
        <br/>{l s='Pay with Credit/Debit Card' mod='areto_cc'}
        <br style="clear:both;"/>
    </a>
</p>

<div id="cc_form">
    <div class="col-xs-12 col-md-4">
        <!-- CREDIT CARD FORM STARTS HERE -->
        <div class="panel panel-default credit-card-box">
            <div class="panel-heading display-table">
                <div class="row display-tr">
                    <!-- <h3 class="panel-title display-td" >Payment Details</h3> -->
                    <div class="display-td">
                        <img class="img-responsive pull-right" src="{$this_path}images/creditcards_logo.png">
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <form role="form" id="payment-form" method="POST" action="{$base_dir_ssl}modules/areto_cc/redirect.php">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="cardNumber">{l s='Card Number' mod='areto_cc'}</label>
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
                                <label for="cardExpiry">{l s='Expiration Date' mod='areto_cc'}</label>
                                <input
                                    type="tel"
                                    class="form-control"
                                    name="cardExpiry"
                                    placeholder="MM / YYYY"
                                    autocomplete="cc-exp"
                                    required
                                />
                            </div>
                        </div>
                        <div class="col-xs-5 col-md-5 pull-right">
                            <div class="form-group">
                                <label for="cardCVC">{l s='CV Code' mod='areto_cc'}</label>
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
                                <label for="date_of_birth">{l s='Date of birth' mod='areto_cc'}</label>
                                <input
                                    id="date_of_birth"
                                    type="text"
                                    class="form-control"
                                    name="date_of_birth"
                                    placeholder="YYYY-MM-DD"
                                    required
                                    readonly
                                />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="phone">{l s='Phone' mod='areto_cc'}</label>
                                <input
                                    id="phone"
                                    type="tel"
                                    class="form-control"
                                    name="phone"
                                    placeholder="Phone number"
                                    required
                                />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        &nbsp;
                    </div>
                    <div class="row">
                        <div class="col-xs-12">
                            <button class="btn btn-success btn-lg btn-block" type="submit">{l s='Place Order'
                                mod='areto_cc'}
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
</div>
<div style="clear: both">&nbsp;</div>

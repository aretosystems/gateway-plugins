{literal}
    <script type="application/javascript">
        $(document).ready(function () {
            // Refund Click
            $('#process_refund').on('click', function (e) {
                if (confirm('You want to perform an "Refund" operation?')) {
                    $('#process_refund').attr('disabled', 'disabled');
                    $.ajax({
                        url: document.URL,
                        type: 'POST',
                        cache: false,
                        async: true,
                        dataType: 'json',
                        data: {
                            ajax: true,
                            process_refund: true,
                            internal_order_id: $('#internal_order_id').val(),
                            refund_amount: $('#refund_amount').val()
                        },
                        success: function (response) {
                            if (response.status !== 'ok') {
                                alert('Error: ' + response.message);
                                $('#process_refund').removeAttr('disabled');
                                return false;
                            }
                            alert(response.message);
                            self.location.href = document.URL;
                        }
                    });
                }
            });
        });
    </script>
{/literal}

<br/>
<fieldset>
    <legend><img src="{$module_dir}actions.gif" alt=""/> {l s='Actions' mod='areto_cc'}</legend>
    <input type="hidden" id="areto_order_id" name="areto_order_id" value="{$order_id|escape:'htmlall':'UTF-8'}"/>
    <input type="hidden" id="internal_order_id" name="internal_order_id" value="{$internal_order_id|escape:'htmlall':'UTF-8'}"/>

    {l s='Refund' mod='areto_cc'}:
    <input type="text" id="refund_amount" name="refund_amount" value="{($total_refunded)|floatval}" />
    <input type="button" id="process_refund" name="process_refund" value="{l s='Refund' mod='areto_cc'}"
           class="button"/>
    <br/>
</fieldset>

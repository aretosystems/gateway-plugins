{if $status == 'ok'}
    <p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='areto_cc'}
        <br /><br /><span class="bold">{l s='Your order will be shipped as soon as possible.' mod='areto_cc'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='areto_cc'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='areto_cc'}</a>.
    </p>
{else}
<p class="warning">
    {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='areto_cc'}
    <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='areto_cc'}</a>.
</p>
{/if}
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'areto_cc',
                component: 'Areto_Payments/js/view/payment/method-renderer/areto-cc-method'
            }
        );

        rendererList.push(
            {
                type: 'areto_qp',
                component: 'Areto_Payments/js/view/payment/method-renderer/areto-qp-method'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);

if (payload.error === true) {
    alert('Sorry, the ' + payload.payment_method + ' payment method doesn\'t  support vouchers or promotions! Please try with different payment method!');
} else {
    prepareInline(payload);
}

function prepareInline(payload){
    (function (document, src, libName, config) {

            let script = document.createElement('script');
            script.src = src;
            script.async = true;
            let firstScriptElement = document.getElementsByTagName('script')[0];
            script.onload = function () {
                for (let namespace in config) {
                    if (config.hasOwnProperty(namespace)) {
                        window[libName].setup.setConfig(namespace, config[namespace]);
                    }
                }
                window[libName].register();
                runInlineCart(payload);
            };
            firstScriptElement.parentNode.insertBefore(script, firstScriptElement);
    })(document, 'https://secure.2checkout.com/checkout/client/twoCoInlineCart.js', 'TwoCoInlineCart',
        {'app': {'merchant': payload.merchant}, 'cart': {'host': 'https:\/\/secure.2checkout.com'}}
    );
}


function runInlineCart(payload){
    if (typeof TwoCoInlineCart !== 'object') {
        console.log('TwoCoInlineCart is not loaded yet.');
        return false;
    }

    TwoCoInlineCart.setup.setMerchant(payload['merchant']);
    TwoCoInlineCart.setup.setMode('DYNAMIC');
    TwoCoInlineCart.register();
    TwoCoInlineCart.cart.setReset(true); // erase previous cart sessions

    TwoCoInlineCart.cart.setCurrency(payload['currency']);
    TwoCoInlineCart.cart.setLanguage(payload['language']);
    TwoCoInlineCart.cart.setReturnMethod(payload['return-method']);
    TwoCoInlineCart.cart.setTest(payload['test']);
    TwoCoInlineCart.cart.setOrderExternalRef(payload['order-ext-ref']);
    TwoCoInlineCart.cart.setExternalCustomerReference(payload['customer-ext-ref']);
    TwoCoInlineCart.cart.setSource(payload['src']);

    TwoCoInlineCart.products.removeAll();
    TwoCoInlineCart.products.addMany(payload['products']);
    TwoCoInlineCart.billing.setData(payload['billing_address']);
    TwoCoInlineCart.shipping.setData(payload['shipping_address']);
    TwoCoInlineCart.cart.setSignature(payload['signature']);
    TwoCoInlineCart.cart.setAutoAdvance(true);
    TwoCoInlineCart.cart.checkout();
}

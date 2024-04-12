# WHMCS
2Checkout WHMCS Connector

### _[Signup free with 2Checkout and start selling!](https://www.2checkout.com/signup)_

This repository includes modules for each 2Checkout interface:
* **twocheckoutapi** : 2PayJS/API
* **twocheckoutinline** : Inline Checkout
* **twocheckoutconvertplus** : Hosted Checkout

### Integrate WHMCS with 2Checkout
----------------------------------------

### 2Checkout Payment Module Setup

#### WHMCS Settings

1. Copy the directory for the module that you want to install to your WHMCS directory on your web server.
2. In your WHMCS admin, navigate to **Setup** -> **Payments** -> **Payment Gateways** and under **All Payment Gateways** click to install the module you want to use.
3. Under **Manage Existing Gateways**, locate the module that you activated.
4. Check to **Show on Order Form**.
5. Enter your "Display Name".
6. Enter your **Merchant Code** found in your 2Checkout panel Integrations section.
7. Enter your **Secret Key** found in your 2Checkout panel Integrations section.
8. Enter your **Secret Word** 2Checkout panel Integrations section.
9. Enable or disable **Test Mode**
10. Click **Save Changes**.

#### 2Checkout Settings

1. Sign in to your 2Checkout account.
2. Navigate to **Dashboard** → **Integrations** → **Webhooks & API section**
3. There you can find the 'Merchant Code', 'Secret key', and the 'Buy link secret word'
4. Navigate to **Dashboard** → **Integrations** → **Ipn Settings**
5. Input the IPN URL available in the configuration page in WHMCS.
6. When adding the IPN URL make sure you check **SHA3** as Hashing algorithm
7. Enable 'Triggers' in the IPN section. It’s simpler to enable all the triggers. Those who are not required will simply not be used.

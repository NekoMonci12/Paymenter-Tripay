## Installation
- Manual: Download the code and place it in the `app/Extensions/Gateways/Tripay` directory.
- Automatic: `git clone https://github.com/NekoMonci12/Paymenter-Tripay /var/www/paymenter/extensions/Gateways/Tripay`

## Configuration
In the admin panel, go to `Settings > Payment Gateways` and click on the `Tripay` gateway. Enter Tripay `Merchant Code`/`Private-Key`/`Api-Key` then `Save`.

## Usage
When a user selects the `Tripay` gateway, they will be redirected to the Tripay payment page. After the payment is completed, the user will be redirected back to the site. 

## Setup Callback On Tripay
1. Go To [Tripay Merchant](https://tripay.co.id/member/merchant)
2. Click "Option"
3. Click "Edit"
4. Fill This Fields With Your Paymenter Callbacks.
```
URL Callback: https://yourdomain.com/extensions/Tripay/webhook
```
5. Save
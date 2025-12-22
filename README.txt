=== Events Manager Pro SecureSubmit Gateway ===
Contributors: markhagan, tonysmedal
Tags: events, event, event registration, event calendar, events calendar, event management, addon, extension, addition, registration, ticket, tickets, ticketing, tickets, theme, widget, locations, maps, booking, attendance, attendee, calendar, gigs, payment, payments
Tested up to: 6.1.1
Stable tag: trunk
License: GPLv2
License URI: https://github.com/hps/heartland-event-manager-pro-gateway/blob/master/LICENSE.md

## ⚠️ DEPRECATION NOTICE

**This plugin is deprecated as of December 19, 2025 and is no longer actively maintained.**

### Migration Path

Please migrate to the **Global Payments WordPress solution** for continued support and enhanced features:

👉 **[Global Payments for WooCommerce](https://wordpress.org/plugins/globalpayments-gateway-provider-for-woocommerce/)**

For general WordPress payment integration, please visit:
- [Global Payments Developer Portal](https://developer.globalpay.com/)
- [Global Payments Support](https://developer.globalpay.com/support)

### Rationale

This plugin is being deprecated as part of the transition from Heartland Payment Systems to Global Payments. The Global Payments solution offers:
- Enhanced security features
- Broader payment method support
- Active development and support
- Improved integration with modern WordPress and WooCommerce versions

---

SecureSubmit allows merchants to take PCI-Friendly Credit Card payments on Events Manager Pro using Heartland Payment Systems payment gateway.

== Description ==

This plugin provides a Heartland Payment Systems Gatway addon to the Events Manager Pro plugin using our SecureSubmit card tokenization library.

Features of SecureSubmit:

*   Only two configuration fields: public and secret API key
*   Simple to install and configure
*   Fully maintained and supported by Heartland Payment Systems
*	Tokenized payments help reduce the merchant's PCI exposure

== Installation ==

1. If installing, go to Plugins > Add New in the admin area, and search for 'events manager pro securesubmit'.
2. Click install, once installed, click Activate.
3. Enable the SecureSubmit gateway in the Events -> Payment Gateways (in your WordPress admin).
4. Configure the plugin by entering your public and secret API keys.

= How do I get started? =

Get your Certification (Dev) API keys by creating an account by [Clicking Here](here: https://developer.heartlandpaymentsystems.com/SecureSubmit/ "Heartland SecureSubmit")

== Screenshots ==

1. The SecureSubmit Plugin Configuration Screen.
2. A view of the plugin in action.

== Changelog ==

= 1.0.9 =
* Fix faulty payment form logic that caused token-failure error messages

= 1.0.8 =
* Fix method signature for `booking_form_feedback` to match latest Events Manager versions

= 1.0.7 =
* Fix issue with requiring payment information when there are only free tickets in the cart.

= 1.0.6 =
* Fix issue with recent versions of Events Manager Pro's gateway HTML when only one gateway is configured

= 1.0.5 =
* Fix issue with Secure Submit active with other gateways

= 1.0.4 =
* Updated PHP SDK for sanitize card holder details

= 1.0.3 =
* Updated class references

= 1.0.2 =
* Updated SDK which includes update to certification URLs for PCI DSS 3.1

= 1.0.1 =
* Adding screenshots

= 1.0.0 =
* Initial Release

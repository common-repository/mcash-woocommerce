=== mcash-woocommerce ===
Tags: woocommerce, payment, gateway
Requires at least: 4.0 or higher
Tested up to: 4.6
Stable tag: 1.3.6
License: MIT License
License URI: http://opensource.org/licenses/MIT
WC requires at least: 2.2
WC tested up to: 2.6.40

Provides a mCASH payment gateway for WooCommerce.

== Description ==

The mcash-woocommerce plugin enables you to accept payment through mobile devices in your webshop.

Only availble for Norwegian residents.

== Installation ==

= The hard way =

1. Download, Unzip and drop the extention on /wp-content/plugins/ directory,
1. As admistration, activate the plugin through the 'Plugins' menu in WordPress,


= The easy way =

1. As admistration, goto 'Plugins' then Click on 'Add New',
2. Search for 'mcash-woocommerce' then Click on 'Install Now',
3. Wait for it to download, Unpack and to install,
4. Activate the plugin by Clicking on 'Activate Plugin'

= Get started =

To start accepting payment with mCASH you first have to apply for an mCASH Merchant account.
Sign up here: https://my.mca.sh/mssp/signup/

When your merchant account have been set up, navigate to WooCommerce -> Settings -> Checkout -> mCASH.
Fill inn both the Merchant ID and Merchant User ID. These values will be set up in your merchant account at https://my.mca.sh/mssp/.

In the plugins settings page, you will also find your Public Key. Copy this and paste it in the MSSP-page https://my.mca.sh/mssp/

You are now ready to accept payments with mobile devices.

== Frequently Asked Questions ==

= How to get an mCASH Merchant account =

Sign up for an account at https://my.mca.sh/mssp/signup/ . Create a merchant and a merchant user (not only a legal entity).

== Screenshots ==

== Changelog ==

= 1.3.6 =
* Fixed issue validating headers on some setups

= 1.3.5 =
* Added patching opportunities for future updates
* Fixed meta value error

= 1.3.2 =
* Bugfix for reauthentication of orders

= 1.3.0 =
* Added cronjob for reauthenticating pending orders
* Minor changes

= 1.2.7 =
* Fixed bug in error handling

= 1.2.6 =
* Fixed critical bug
* New order status for authorized payments

= 1.2.4 =
* Improved stylesheets
* Improved error handling

= 1.2.2 =
* Improvements to the description and startup guide
* Fixed minor bugs

= 1.2 =
* Improved logging functionality

= 1.1 =
* Rebuilt plugin and added direct and express purchase

= 0.5 =
* Fix for including tax on payment request text
* Fix for when scope is not ready when payment ok

= 0.4 =
* Fixed faulty html and added banner and icon

= 0.3 =
* Added mCASH Express

= 0.2 =
* Fix for php 5.3
* Fix for apache removing Authorization header

= 0.1 =
* First early adopter release. Tested on WooCommerce



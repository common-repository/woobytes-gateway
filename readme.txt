=== WooBytes gateway ===

Contributors: bottapress, tarmo888
Tags: obyte, byteball, bytes, gateway, woocommerce, woocommerce gateway, ecommerce, cryptocurrency, payment method, sell, shop, cart, checkout
Requires at least: 4.4
Tested up to: 5.2
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==

Now accept [Obyte](https://obyte.org) bytes as payment currency with the first Obyte woocommerce gateway and get a minimum 10% cashback for *all* your sales (even not paid with bytes) by subscribing to the [Obyte cashback program](https://medium.com/byteball/byteball-cashback-program-9c717b8d3173) !


== Main features ==

 - powered by [Obyte for Merchants](https://obyte-for-merchants.com/ "Obyte for Merchants" ) payment gateway,
 - fully integrates **Obyte cashback program**,
 - **multi-currency** (Bytes, BTC, EUR, USD, AUD, BRL, CAD, CHF, CLP, CNY, DKK, GBP, HKD, INR, ISK, JPY, KRW, NZD, PLN, RUB, SEK, SGD, THB, TWD),
 - **anonymous** : no api key needed,
 - extremely **simplified setup**,
 - includes a Woocommerce integrated optional **debugging tool**,
 - other Woocommerce **gateways compliant**.


 == medium term road map ==

- internationalization
- pay button customizer


== Screenshots ==

1. Admin settings page

2. Payment page

3. Admin Obyte bytes payment report page



== support ==

go to [wordpress support forum](https://wordpress.org/support/plugin/woobytes-gateway) or on our [Discord](https://obyte.org/discord)





== Installation ==

= Minimum Requirements =

* PHP version 5.2.4 or greater (PHP 5.6 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)
* Woocommerce version 3.0


= Manual installation =

- download woobytes-gateway.zip file

- from your wordpress site admin dashboard :
    1) plugin / add new / upload plugin
    2) activate the plugin
    3) go to 'Woobytes / settings' page to set up and enable Obyte byte payments

That's all !


== Changelog ==

= 1.2.0 =
* Release Date - 2019-05-07*

* Rebranding from Byteball to Obyte
* Server change from byteball-for-merchants.com to obyte-for-merchants.com
* Less white-space


= 1.1.0 =
*Release Date - 2018-03-09*

* Now automatically sends a notification email (with order details AND payment link) to customer as soon as the order is created (can be switched off on plugin settings page)
* Added Byteball Bytes, KBytes, MBytes and GBytes as possible main currency for your e-shop (in Woocommerce / Settings / Currency options)
* Added some UI messages on the settings page to enable customization
* Minor change : added plugin version to data communicated to 'byteball-for-merchants'



= 1.0.4 =
*Release Date - 2018-02-19*

* Now accepts email address for byteball cashback programm
* Added security IP checks on byteball-for-merchants notifications


= 1.0.3 =
*Release Date - 2018-02-19*

* Fixed cashback api currency amount (that was sent in Bytes and not in GBytes)
* Added 'Cashback API SSL cipher list' on plugin settings page
* Fixed some typos

= 1.0.2 =
*Release Date - 2017-09-23*

* Fixed byteball pay button non displaying issue when using wordpress non standard permalinks


= 1.0.1 =
*Release Date - 2017-09-21*

* Added woocommerce version check
* Added woocommerce activation warning


= 1.0.0 =
*Release Date - 2017-09-18*

* Added 'woobytes' admin menu
* Added admin byteball bytes payments reports page
* Revamped plugin files structure


= 0.4 =
*Release Date - 2017-09-14*

* Replaced curl functions by wordpress build-in HTTP API functions for requests toward cashback server


= 0.3 =
*Release Date - 2017-09-13*

* Plugin renamed 'WooBytes gateway' in accordance to wopdress.org plugins guideline
* Added 'settings' link on admin plugins page
* Admin notice on plugin activation to go on plugin settings page
* Display customer byteball payment unit on order received page with a link to byteball explorer
* Make 'powered by byteball-for-merchants.com' link on checkout page and pay button optional (with default to 'no')
* Name the currency 'Bytes' (Gigabytes, etc).  Byteball is the name of the platform.
* Replaced curl functions by wordpress build-in HTTP API functions for requests toward byteball-for-merchants server


= 0.2 =
*Release Date - 2017-09-12*

* Fixed some typos
* 20 new currencies added
* Changed license from MIT to GNU General Public License v3.0


= 0.1 =
*Release Date - 2017-09-10*

* First version




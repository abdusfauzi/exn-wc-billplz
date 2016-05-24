<img src="https://raw.githubusercontent.com/abdusfauzi/exn-wc-billplz/master/assets/billplz-logo-64.png" height="26"> Billplz WooCommerce Plugin
=====================

Billplz Payment Gateway for WooCommerce (Wordpress) developed by Exnano Creative team. Download plugin [from here &#8681;](https://github.com/abdusfauzi/exn-wc-billplz/archive/master.zip) and rename to `exn-wc-billplz`.


Notes
-----
Exnano Creative is not responsible for any problems that might arise from the use of this module.
Use at your own risk. Please backup any critical data before proceeding. For any query or assistance, please submit issue to [this repo](https://github.com/abdusfauzi/exn-wc-billplz/issues).

BILLPLZ SDN BHD (1023853P) [billplz.com](https://www.billplz.com) is not associated with the development of this plugin, even though the payment gateway API was provided by the company.


Malaysian Banks Supported
-----
<img src="https://raw.githubusercontent.com/abdusfauzi/exn-wc-billplz/master/assets/billplz-banks.png" height="66" alt="FPX Banks">


Installations for Wordpress Plugin
-----------------------------
1. Simply drop the `exn-wc-billplz` folder into your WordPress plugins directory.
2. Go to Dashboard > Plugins > enable **WooCommerce Billplz**
3. Go to Dashboard > WooCommerce > Settings > Checkout > **Billplz** tab and fill up all requirements.
4. You're good to go!


Contribution
------------
You can contribute to this plugin by submitting issue on [this repo](https://github.com/abdusfauzi/exn-wc-billplz/issues).


Issues
------------
Submit issue to this [this repo](https://github.com/abdusfauzi/exn-wc-billplz/issues).


Changelog
------------
**1.2.0** - 2016-05-24
- Fix banks icon image to responsive width (100%)
- Fix payment status skipping Processing and supposedly not immediately Complete
- Dropped: `_billplz_id` meta, and use WooCommerce `_transaction_id`
- Dropped: `_billplz_url`. Will use `$order->get_transaction_url()` by WooCommerce
- Refactoring class, methods and variables
- Added: note to Order based on Billplz status
- Added: Sandbox Mode's input fields for API Secret Key & Collection Id

**1.1.23 - 1.1.26** - 2016-05-23
- Fix author proper name for proper_folder_name used by updater library
- Add `Tested up to` version
- Added `updater` library for Automatic update from Github repo
- Fix author URI

**1.0.23** - 2016-05-22
- First release
- First commit and publish to Github

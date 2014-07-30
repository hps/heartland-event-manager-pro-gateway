## SecureSubmit Event Manager Pro Plugin

This plugin allows for merchants using Event Manager Pro to take tokenized, PCI-friendly credit card payments for their events.

## Installation
Extract the contents to the WordPress plugins folder. This assumes "event-manager-pro" as the plugin folder for your Pro version of Event Manager.

One core file must be modified for this to work! The gateway.php file must be updated so that the SecureSubmit plugin is included. If you are running a vanilla install of Event Manager Pro then you can overwrite this file. Otherwise, just copy the last line (our include) and paste it into your existing file.

## Usage
Once the payment gateway is selected, you must activate and configure it by going to Events->Payment Gateways. Hover SecureSubmit and check "Activate". Next click "SecureSubmit" and add your public and secret API keys.

## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
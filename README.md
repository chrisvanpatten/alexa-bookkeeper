# Alexa Bookkeeper

Ever wanted to check your bank/credit card/loan balances through Alexa? This tool makes it easy.

## Requirements

You'll need:

+ A server capable of running Python/Pip and PHP
+ A Mint account, with your accounts connected
+ An SSL certificate properly configured on your server

## Setup

0. Install [mintapi](https://github.com/mrooney/mintapi) on your server (and configure the path to it if necessary inside the `fetchAccounts()` method)
0. Rename and move the example.mint.json file to a safe place outside of your webroot
0. Add your Mint credentials to the file
0. Set the relative path to your Mint file in the alexa-bookkeeper.php file
0. Map your accounts to the keywords you'd like to be able to use with Alexa in the `searchAccounts()` method
0. Create a new Alexa skill in the Amazon Developers portal, pointing to your alexa-bookkeeper.php file
0. ...
0. Profit!

## Caveats

+ You'll need to store your Mint credentials on your server, as Mint doesn't offer a true API/OAuth
+ The mintapi Python script could break at any time, breaking all of this. No promises.

## License

&copy; 2016 Chris Van Patten.

Licensed under the terms of the [CC0](https://creativecommons.org/publicdomain/zero/1.0/) public domain license.

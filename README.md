# Alexa Bookkeeper

Ever wanted to check your bank/credit card/loan balances through Alexa? This tool makes it easy.

## Requirements

You'll need:

+ A server capable of running Python/Pip and PHP
+ A Mint account, with your accounts connected
+ An SSL certificate properly configured on your server

## Setup

0. Install [mintapi](https://github.com/mrooney/mintapi) on your server
0. Rename `example.mint.json` to `.mint.json` and move it to a safe place **outside of your webroot**. Add your Mint credentials to the file
0. Rename `example.config.json` to `.config.json` and update the paths
0. Set the relative path to your Mint credentials file (`.mint.json`) in `refresh.py`, and configure a cron job on your server to run it once or twice daily (don't run it too frequently, or Mint will lock your account; see "Caveats and notes" below)
0. Create a new Alexa skill in the Amazon Developers portal, pointing to your `alexa-bookkeeper.php` file
0. ...
0. Profit!

## Caveats and notes

+ You'll need to store your Mint credentials on your server, as Mint doesn't offer a true API/OAuth.
+ To 'hide' an account from the script, give it the title "ignore" in Mint.
+ Be careful while testing; Mint rate-limits logins and will force a password reset or lockout if you try to log in too frequently.
+ The mintapi Python script could break at any time, breaking all of this. No promises.

## License

&copy; 2016 Chris Van Patten.

Licensed under the terms of the [CC0 public domain license](https://creativecommons.org/publicdomain/zero/1.0/).

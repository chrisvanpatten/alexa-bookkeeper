import mintapi
import json

with open('../../.mint.json') as credentials:
    credentials = json.load(credentials)

mint = mintapi.Mint( credentials['email'], credentials['password'] )
mint.initiate_account_refresh()

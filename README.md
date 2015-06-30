# JSON API Behat Extension

The JSON API Behat Extension provides step definitions for common testing scenarios specific to JSON APIs. It comes with easy ways to handle authentication through OAuth.

** NOTE: This extension is still being developed and can't be installed as described below **

---

## Installation

Recommended installation is by running the composer require command.

    composer require kielabokkie/jsonapi-behat-extension --dev

Or alternatively you can manually add the package to your composer.json file.

    "require-dev": {
        "kielabokkie/jsonapi-behat-extension": "~1.0"
    },

## Configuration

To use this extension you will have to add it under the `extensions` in your behat.yml file.

    default:
        extensions:
            Kielabokkie\BehatJsonApi: ~

This will setup the extension with the following default parameters:

| parameter              | value            |
|------------------------|------------------|
| base_url               | http://localhost |
| oauth_uri              | /v1/oauth/token  |
| oauth_client_id        | testclient       |
| oauth_client_secret    | testsecret       |
| oauth_use_bearer_token | false            |

You can overwrite these parameters in the behat.yml file as needed.

    default:
        extensions:
            Kielabokkie\BehatJsonApi:
                base_url: http://api.yourapp.dev
                parameters:
                    access_token: 90dabed99acef998fd3e35280f2a0a3c30c00c8d
                    oauth:
                        uri: /v1/oauth/token
                        client_id: testclient
                        client_secret: testpass
                        use_bearer_token: true

## Usage

To see a list of available step definitions:

    $ vendor/bin/behat -dl
# JSON API Behat Extension

[![Author](http://img.shields.io/badge/by-@kielabokkie-lightgrey.svg?style=flat-square)](https://twitter.com/kielabokkie)
[![Packagist Version](https://img.shields.io/packagist/v/kielabokkie/jsonapi-behat-extension.svg?style=flat-square)](https://packagist.org/packages/kielabokkie/jsonapi-behat-extension)
[![Codacy Badge](https://img.shields.io/codacy/grade/05bb81bdf72e4dfb8b78e76410ff7605.svg?style=flat-square)](https://www.codacy.com/app/kielabokkie/jsonapi-behat-extension)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

The JSON API Behat Extension provides step definitions for common testing scenarios specific to JSON APIs. It comes with easy ways to handle authentication through OAuth.

Starting from version 2.1 this extension also has built-in support for Laravel 5.5. This means that all requests go through Laravel's request system, eliminating the use of a webserver when running your tests. Check the [Laravel setup](#laravel-setup) section for instructions on how to set this up.

## Installation

Recommended installation is by running the composer require command. This will install the latest stable version of this extension.

    composer require kielabokkie/jsonapi-behat-extension --dev

## Configuration

To use this extension you will have to add it under the `extensions` in your `behat.yml` file.

    default:
        extensions:
            Kielabokkie\BehatJsonApi: ~

### Default parameters

Out of the box this extension has the following default parameters:

| parameter                                        | value                 |
|--------------------------------------------------|-----------------------|
| base_url                                         | http://localhost:8000 |
| oauth_uri                                        | /v1/oauth/token       |
| oauth_client_id                                  | testclient            |
| oauth_client_secret                              | testsecret            |
| oauth_use_bearer_token                           | false                 |
| oauth_password_grant_requires_client_credentials | false                 |

You can overwrite any of these parameters in the `behat.yml` file as needed.

    default:
        extensions:
            Kielabokkie\BehatJsonApi:
                base_url: http://api.yourapp.dev
                parameters:
                    oauth:
                        uri: /v1/oauth/token
                        client_id: myClientId
                        client_secret: myClientSecret
                        use_bearer_token: true
                        password_grant_requires_client_credentials: true


### Optional parameters

To avoid having to use OAuth to retrieve an access token for each API call you can also specify an optional `access_token` in the parameters:

    default:
        extensions:
            Kielabokkie\BehatJsonApi:
                parameters:
                    access_token: 90dabed99acef998fd3e35280f2a0a3c30c00c8d

## Usage

To use the step definitions provided by this extension just load the context class in your suites:

```yaml
default:
  suites:
    default:
      contexts:
        - Kielabokkie\BehatJsonApi\Context\JsonApiContext
```
You will then have access to the following step definitions:

    @Given I use the access token
    @Given I use access token :token
    @Given I oauth with :username and :password
    @Given I oauth with :username and :password and scope :scope
    @Given I oauth using the client credentials grant
    @Given I oauth using the client credentials grant with scope :scope
    @Given I oauth using the client credentials grant with :id and :secret
    @Given I oauth using the client credentials grant with :id and :secret and scope :scope
    @Given I add a :header header with the value :value
    @Given I have the payload:
    @When /^I request "(GET|PUT|PATCH|POST|DELETE) ([^"]*)"$/
    @Then I get a :statuscode response
    @Then scope into the :scope property
    @Then scope into the first :scope element
    @Then the structure matches:
    @Then the :field property is an object
    @Then the :field property is an array
    @Then the :field property is an array with :count items
    @Then the :field property is an empty array
    @Then the :field property is an integer
    @Then the :field property is a integer equaling/equalling :expected
    @Then the :field property is a string
    @Then the :field property is a string equaling/equalling :expected
    @Then the :field property is a boolean
    @Then the :field property is a boolean equaling/equalling :expected
    @Then /^echo last request$/
    @Then /^echo last response$


To get a list of all available step definitions including examples you can run the following command:

    $ vendor/bin/behat -di

### Override the base url

In some cases you might want to override the base url for a specific suite. Below is an example of a `behat.yml` file. Here the custom url `http://hooks.yourapp.dev` is passed to the FeatureContext under the hooks suite.

    default:
        autoload:
            - %paths.base%/tests/Behat/features/bootstrap
        suites:
            api:
                paths:
                    - %paths.base%/tests/Behat/features/api
                contexts:
                    - Kielabokkie\BehatJsonApi\Context\JsonApiContext: ~
            hooks:
                paths:
                    - %paths.base%/tests/Behat/features/hooks
                contexts:
                    - Kielabokkie\BehatJsonApi\Context\JsonApiContext:
                        - http://hooks.yourapp.dev
        extensions:
            Kielabokkie\BehatJsonApi: ~

## Laravel Setup

To use the Laravel specific context all you have to do is change the context class to `JsonApiLaravelContext`:

```yaml
default:
  suites:
    default:
      contexts:
        - Kielabokkie\BehatJsonApi\Context\JsonApiLaravelContext
```

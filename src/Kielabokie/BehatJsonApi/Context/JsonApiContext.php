<?php namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Buzz\Browser;
use Buzz\Message\Request;
use Exception;
use PHPUnit_Framework_Assert as PHPUnit;

/**
 * Defines application features from the specific context.
 */
class JsonApiContext implements SnippetAcceptingContext
{
    /**
     * @var \Buzz\Browser
     */
    protected $client;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var \Behat\Gherkin\Node\PyStringNode
     */
    protected $requestPayload;

    /**
     * @var array
     */
    protected $responsePayload;

    /**
     * @var \Buzz\Message\Response
     */
    protected $response;

    /**
     * The current scope within the response payload
     * which conditions are asserted against.
     */
    protected $scope;

    /**
     * Context parameters (which are set in behat.yml)
     *
     *  @var array
     */
    protected $parameters = array();

    /**
     * Request headers
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Initialize the context
     */
    public function __construct()
    {
        // Start with the default set of headers
        $this->resetHeaders();

        if (is_null($this->client) === true) {
            $this->client = new Browser();
        }
    }

    /**
     * Set the base url (specified in behat.yml)
     *
     * @param string $baseUrl [description]
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Set extension specific parameters (specified in behat.yml)
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @Given I oauth with :username and :password
     *
     * Acquire an OAuth access token using the 'password' grant
     * -
     * Example:
     * Given I oauth with "email@yourdomain.com" and "password"
     */
    public function iOauthWithUsernameAndPassword($username, $password)
    {
        if (isset($this->parameters['oauth']) === false) {
            throw new Exception('OAuth details not found in your behat.yml file.');
        }

        $payload = [
            "grant_type" => 'password',
            "username"   => $username,
            "password"   => $password,
        ];

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I oauth using the client credentials grant
     *
     * Acquire an OAuth access token using the 'client_credentials' grant
     * -
     * Example:
     * Given I oauth using the client credentials grant
     */
    public function iOauthUsingTheClientCredentialsGrant()
    {
        if (isset($this->parameters['oauth']) === false) {
            throw new Exception('OAuth details not found in your behat.yml file.');
        }

        $payload = [
            "grant_type"    => 'client_credentials',
            "client_id"     => $this->parameters['oauth']['client_id'],
            "client_secret" => $this->parameters['oauth']['client_secret'],
        ];

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I have the payload:
     *
     * Supply a JSON payload
     * -
     * Example:
     *
     * And I have the payload:
     *   """
     *   {
     *     "comment": "This is a comment"
     *   }
     *   """
     */
    public function iHaveThePayload(PyStringNode $requestPayload)
    {
        $this->requestPayload = $requestPayload->getRaw();
    }

    /**
     * @When /^I request "(GET|PUT|PATCH|POST|DELETE) ([^"]*)"$/
     *
     * Call an API endpoint
     * -
     * Example:
     * When I request "GET /v1/movies"
     */
    public function iRequest($httpMethod, $resource)
    {
        $method = strtolower($httpMethod);

        $url = sprintf('%s%s', $this->baseUrl, $resource);

        // If there is no authorization header we assume the access token should be passed as a GET parameter
        if ($this->hasHeader('Authorization') === false) {
            $accessToken = is_null($this->accessToken) === false ? $this->accessToken : $this->parameters['access_token'];
            $url = sprintf('%s?access_token=%s', $url, $accessToken);
        }

        switch ($httpMethod) {
            case 'PUT':
            case 'POST':
            case 'PATCH':
                $this->response = $this->client
                     ->$method($url, $this->headers, $this->requestPayload);
                break;
            default:
                $this->response = $this->client
                     ->$method($url, $this->headers);
        }

        // Reset so we have the default set of headers again
        $this->resetHeaders();
    }

    /**
     * @Then I get a :statuscode response
     *
     * Check the status code of a response
     * -
     * Example:
     * Then I get a 200 response
     */
    public function iGetAResponse($statusCode)
    {
        $response = $this->getResponse();

        $contentType = $response->getHeader('Content-Type');
        if ($contentType === 'application/json') {
            $bodyOutput = $response->getContent();
        } else {
            $bodyOutput = sprintf("Expected 'application/json' content type but got '%' instead.", $contentType);
        }

        PHPUnit::assertSame(intval($statusCode), $this->getResponse()->getStatusCode(), $bodyOutput);
    }

    /**
     * @Then scope into the :scope property
     *
     * Scope into a property of the current response
     * -
     * Example:
     * Then scope into the "data" property
     */
    public function scopeIntoTheProperty($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @Then scope into the first :scope element
     *
     * Scope into the first element of an array in the current response
     * -
     * Example:
     * Then scope into the first "actors" element
     */
    public function scopeIntoTheFirstElement($scope)
    {
        $payload = $this->getScopePayload($this->scope);

        $this->scope = sprintf('%s.%s.0', $this->scope, $scope);
    }

    /**
     * @Then the structure matches:
     *
     * Check if the structure of the response exactly matches
     * -
     * Example:
     * And the structure matches:
     *   """
     *   title
     *   release_date
     *   genres
     *   """
     */
    public function theStructureMatches(PyStringNode $propertiesString)
    {
        $payload = $this->getScopePayload();

        // Make sure the payload is in the expected format
        if (is_object($payload) === true) {
            $payload = get_object_vars($payload);
        }

        // Stored for error output
        $originalPayload = $payload;

        $expected = explode("\n", (string) $propertiesString);

        foreach ($expected as $property) {
            PHPUnit::assertArrayHasKey($property, $payload, sprintf(
                'Asserting the [%s] property exists in the scope [%s]: %s',
                $property,
                $this->scope,
                json_encode($originalPayload)
            ));

            unset($payload[$property]);
        }

        PHPUnit::assertEmpty($payload, sprintf(
            'Unexpected properties [%s] found in payload: %s',
            implode(' ,', array_keys($payload)),
            json_encode($originalPayload)
        ));
    }

    /**
     * @Then the :field property is an array
     *
     * Checks if the specified field is an array
     * -
     * Example:
     * And the "genres" property is an array
     */
    public function thePropertyIsAnArray($property)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        PHPUnit::assertTrue(
            is_array($actualValue),
            sprintf("Asserting the [%s] property in current scope [%s] is an array", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is an array with :count items
     *
     * Checks if the specified field is an array with a specified number of items
     * -
     * Example:
     * And the "genres" property is an array with 4 items
     */
    public function thePropertyIsAnArrayWithItems($property, $count)
    {
        // Run the regular thePropertyIsAnArray function first
        $this->thePropertyIsAnArray($property);

        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        PHPUnit::assertEquals(
            count(array_keys((array)$actualValue)),
            $count,
            sprintf('Asserting the [%s] array contains [%s] items', $property, $count)
        );
    }

    /**
     * @Then the :field property is an empty array
     *
     * Checks if the specified field is an empty array
     * -
     * Example:
     * And the "genres" property is an empty array
     */
    public function thePropertyIsAnEmptyArray($property)
    {
        $payload = $this->getScopePayload();
        $scopePayload = $this->arrayGet($payload, $property);

        PHPUnit::assertTrue(
            is_array($scopePayload) && $scopePayload === [],
            sprintf("Asserting the [%s] property in current scope [%s] is an empty array", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is an integer
     *
     * Checks if the specified field is an integer
     * -
     * Example:
     * And the "id" property is an integer
     */
    public function thePropertyIsAnInteger($property)
    {
        $payload = $this->getScopePayload();

        PHPUnit::assertInternalType(
            'int',
            $this->arrayGet($payload, $property),
            sprintf("Asserting the [%s] property in current scope [%s] is an integer", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is a integer equaling/equalling :expected
     *
     * Checks if the specified field is an integer with a specified value
     * -
     * Example:
     * And the "id" property is an integer equaling 17
     */
    public function thePropertyIsAIntegerEqualing($property, $expected)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->thePropertyIsAnInteger($property);

        PHPUnit::assertSame(
            $actualValue,
            (int) $expected,
            sprintf("Asserting the [%s] property in current scope [%s] is an integer equaling [%s]", $property, $this->scope, $expected)
        );
    }

    /**
     * @Then the :field property is a string
     *
     * Checks if the specified field is a string
     * -
     * Example:
     * And the "title" property is a string
     */
    public function thePropertyIsAString($property)
    {
        $payload = $this->getScopePayload();

        PHPUnit::assertInternalType(
            'string',
            $this->arrayGet($payload, $property),
            sprintf("Asserting the [%s] property in current scope [%s] is a string", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is a string equaling/equalling :expected
     *
     * Checks if the specified field is a string with a specified value
     * -
     * Example:
     * And the "title" property is a string equaling "Pulp Fiction"
     */
    public function thePropertyIsAStringEqualing($property, $expected)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->thePropertyIsAString($property);

        PHPUnit::assertSame(
            $actualValue,
            $expected,
            sprintf("Asserting the [%s] property in current scope [%s] is a string equaling [%s]", $property, $this->scope, $expected)
        );
    }

    /**
     * @Then the :field property is a boolean
     *
     * Checks if the specified field is a boolean
     * -
     * Example:
     * And the "is_released" property is a boolean
     */
    public function thePropertyIsABoolean($property)
    {
        $payload = $this->getScopePayload();

        PHPUnit::assertInternalType(
            'boolean',
            $this->arrayGet($payload, $property),
            sprintf("Asserting the [%s] property in current scope [%s] is a boolean", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is a boolean equaling/equalling :expected
     *
     * Checks if the specified field is a boolean with a specified value
     * -
     * Example:
     * And the "is_released" property is a boolean equaling true
     */
    public function thePropertyIsABooleanEqualing($property, $expected)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        if (in_array($expected, ['true', 'false']) === false) {
            throw new Exception("The expected value can only be 'true' or 'false'.");
        }

        $this->thePropertyIsABoolean($property);

        PHPUnit::assertSame(
            $actualValue,
            $expected === 'true',
            sprintf("Asserting the [%s] property in current scope [%s] is a boolean equaling [%s]", $property, $this->scope, $expected)
        );
    }

    /**
     * @Then /^echo last request$/
     *
     * Echos the last request for debugging purposes
     * -
     * Example:
     * And echo last request
     */
    public function echoLastRequest()
    {
        $request = $this->client->getLastRequest();

        echo sprintf("%s %s%s HTTP/%s\n", $request->getMethod(), $request->getHost(), $request->getResource(), $request->getProtocolVersion());

        $headerString = '';
        foreach ($request->getHeaders() as $header) {
            $headerString = sprintf("%s%s\n", $headerString, $header);
        }

        echo rtrim($headerString, "\n");

        if (empty($request->getContent()) === false) {
            echo sprintf("\nContent: %s", $request->getContent());
        }
    }

    /**
     * @Then /^echo last response$/
     *
     * Echos the last response for debugging purposes
     * -
     * Example:
     * And echo last response
     */
    public function echoLastResponse()
    {
        $response = $this->client->getLastResponse();

        $headerString = '';
        foreach ($response->getHeaders() as $header) {
            $headerString = sprintf("%s%s\n", $headerString, $header);
        }

        echo rtrim($headerString, "\n");

        if (empty($response->getContent()) === false) {
            echo sprintf("\nContent: %s", $response->getContent());
        }
    }

    /**
     * Send an OAuth request to the API
     *
     * @param array $payload [description]
     */
    protected function sendOauthRequest(array $payload)
    {
        $url = sprintf('%s%s', $this->baseUrl, $this->parameters['oauth']['uri']);

        $response = $this->client->post($url, $this->headers, json_encode($payload));
        $responseContent = json_decode($response->getContent());

        // Throw an exception if the statuscode is not 200
        if ($response->getStatusCode() !== 200) {
            $errorMessage = 'Authorization Error';
            if (isset($responseContent->error_description) === true) {
                $errorMessage = sprintf('%s: %s', $errorMessage, $responseContent->error_description);
            }

            throw new Exception($errorMessage);
        }

        // Add authorization header if the OAuth config is set to use the bearer authentication scheme
        if ($this->parameters['oauth']['use_bearer_token'] === true) {
            $this->addHeader('Authorization', sprintf('Bearer %s', $responseContent->access_token));
        } else {
            $this->accessToken = $responseContent->access_token;
        }
    }

    /**
     * Returns the payload from the current scope within the response
     *
     * @return mixed
     */
    protected function getScopePayload()
    {
        $payload = $this->getResponsePayload();

        if (is_null($this->scope) === true) {
            return $payload;
        }

        return $this->arrayGet($payload, $this->scope);
    }

    /**
     * Return the response payload from the current response.
     *
     * @return  mixed
     */
    protected function getResponsePayload()
    {
        $json = json_decode($this->getResponse()->getContent());

        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = 'Failed to decode JSON body ';

            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $message .= '(Maximum stack depth exceeded).';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $message .= '(Underflow or the modes mismatch).';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $message .= '(Unexpected control character found).';
                    break;
                case JSON_ERROR_SYNTAX:
                    $message .= '(Syntax error, malformed JSON).';
                    break;
                case JSON_ERROR_UTF8:
                    $message .= '(Malformed UTF-8 characters, possibly incorrectly encoded).';
                    break;
                default:
                    $message .= '(Unknown error).';
                    break;
            }

            throw new Exception($message);
        }

        $this->responsePayload = $json;

        return $this->responsePayload;
    }

    /**
     * Checks the response exists and returns it.
     *
     * @return  Guzzle\Http\Message\Response
     */
    protected function getResponse()
    {
        if (is_null($this->response) === true) {
            throw new Exception("The response was not set.");
        }

        return $this->response;
    }

    /**
     * Reset the headers to the default
     *
     * @return void
     */
    protected function resetHeaders()
    {
        $this->headers = array();

        $headers = [
            'Content-Type' => 'application/json',
        ];

        foreach ($headers as $headerName => $headerValue) {
            $this->addHeader($headerName, $headerValue);
        }
    }

    /**
     * Add a header
     *
     * @param string $headerName
     * @param string $headerValue
     */
    protected function addHeader($headerName, $headerValue)
    {
        $this->headers[$headerName] = $headerValue;
    }

    /**
     * Check if a header exists
     *
     * @param  string  $headerName
     * @return boolean
     */
    protected function hasHeader($headerName)
    {
        return isset($this->headers[$headerName]);
    }

    /**
     * Get an item from an array using "dot" notation
     *
     * @link   http://laravel.com/docs/helpers
     * @param  array   $array
     * @param  string  $key
     * @return mixed
     */
    protected function arrayGet($array, $key)
    {
        if (is_null($key) === true) {
            return $array;
        }

        foreach(explode('.', $key) as $segment) {
            if (is_object($array) === true) {
                if (isset($array->{$segment}) === false) {
                    return;
                }
                $array = $array->{$segment};
            } elseif (is_array($array) === true) {
                if (array_key_exists($segment, $array) === false) {
                    return;
                }
                $array = $array[$segment];
            }
        }

        return $array;
    }
}

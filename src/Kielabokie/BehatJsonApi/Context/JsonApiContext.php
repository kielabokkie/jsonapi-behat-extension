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
    private $parameters = array();

    /**
     * Request headers
     *
     * @var array
     */
    private $headers = array();

    /**
     * Initialize the context
     *
     * The baseUrl and parameters are specified in the bahat.yml
     */
    public function __construct($baseUrl, array $parameters)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->parameters = $parameters['parameters'];

        // Start with the default set of headers
        $this->resetHeaders();

        if (is_null($this->client) === true) {
            $this->client = new Browser();
        }
    }

    /**
     * @Given I oauth with :username and :password
     */
    public function iOauthWithUsernameAndPassword($username, $password)
    {
        if(isset($this->parameters['oauth']) === false) {
            throw new Exception('OAuth details not found in your behat.yml file.');
        }

        $payload = [
            "grant_type"    => "password",
            "client_id"     => $this->parameters['oauth']['client_id'],
            "client_secret" => $this->parameters['oauth']['client_secret'],
            "username"      => $username,
            "password"      => $password,
        ];

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

        $this->accessToken = $responseContent->access_token;
    }

    /**
     * @Given I have the payload:
     */
    public function iHaveThePayload(PyStringNode $requestPayload)
    {
        $this->requestPayload = $requestPayload->getRaw();
    }

    /**
     * @When /^I request "(GET|PUT|PATCH|POST|DELETE) ([^"]*)"$/
     */
    public function iRequest($httpMethod, $resource)
    {
        $this->addHeader('X-Test', 'Just a test');

        $method = strtolower($httpMethod);

        $accessToken = is_null($this->accessToken) === false ? $this->accessToken : $this->parameters['access_token'];

        $url = sprintf('%s%s?access_token=%s', $this->baseUrl, $resource, $accessToken);

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
     */
    public function scopeIntoTheProperty($scope)
    {
        $this->scope = $scope;
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
     * @Then the structure matches:
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
     * @Then the :field property in the response contains :count items
     */
    public function thePropertyInTheResponseContainsItems($property, $count)
    {
        $payload = (array)$this->getResponsePayload();

        PHPUnit::assertEquals(
            count(array_keys((array)$payload[$property])),
            $count,
            sprintf("Asserting the [%s] property contains [%s] items", $property, $count)
        );
    }

    /**
     * @Then the :field property is an array
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
     * @Then /^echo last request$/
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
     * Checks the response exists and returns it.
     *
     * @return  Guzzle\Http\Message\Response
     */
    private function getResponse()
    {
        if (is_null($this->response) === true) {
            throw new Exception("The response was not set.");
        }

        return $this->response;
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
     * Reset the headers to the default
     *
     * @return void
     */
    private function resetHeaders()
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
    private function addHeader($headerName, $headerValue)
    {
        $this->headers[$headerName] = $headerValue;
    }

    /**
     * Get an item from an array using "dot" notation.
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

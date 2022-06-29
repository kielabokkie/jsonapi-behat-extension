<?php namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Buzz\Browser;
use Buzz\Client\FileGetContents;
use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Defines application features from the specific context.
 */
class JsonApiContext implements Context, JsonApiAwareInterface
{
    protected ?Browser $browser = null;

    protected ?string $baseUrl = null;

    private ?string $accessToken = null;

    protected string $requestPayload;

    protected object $responsePayload;

    protected ?Response $response = null;

    protected string $responseBody;

    /**
     * The current scope within the response payload
     * which conditions are asserted against.
     */
    protected ?string $scope = null;

    /**
     * Context parameters (which are set in behat.yml)
     */
    protected array $parameters = [];

    /**
     * Request headers
     */
    protected array $headers = [];

    /**
     * Initialize the context
     */
    public function __construct()
    {
        // Start with the default set of headers
        $this->resetHeaders();

        if ($this->browser === null) {
            $client = new FileGetContents(new Psr17Factory());
            $this->browser = new Browser($client, new Psr17Factory());
        }
    }

    /**
     * Set the base url (specified in behat.yml)
     *
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl): void
    {
        // Only set the baseUrl if it's not already set
        if ($this->baseUrl === null) {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    /**
     * Set extension specific parameters (specified in behat.yml)
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @Given I use the access token
     *
     * Use the access token specified in the behat.yml file
     * -
     * Example:
     * Given I use the access token
     */
    public function iUseTheAccessToken(): void
    {
        if (isset($this->parameters['access_token']) === false) {
            throw new Exception('The access token is not found in the behat.yml file.');
        }

        // Set the authentication token
        $this->setAuthentication($this->parameters['access_token']);
    }

    /**
     * @Given I use access token :token
     *
     * Use the specified access token
     * -
     * Example:
     * Given I use access token "90dabed99acef998fd3e35280f2a0a3c30c00c8d"
     */
    public function iUseAccessToken($accessToken): void
    {
        // Set the authentication token
        $this->setAuthentication($accessToken);
    }

    /**
     * @Given I oauth with :username and :password
     *
     * Acquire an OAuth access token using the 'password' grant
     * -
     * Example:
     * Given I oauth with "email@yourdomain.com" and "p4ssw0rd"
     */
    public function iOauthWithUsernameAndPassword($username, $password): void
    {
        $payload = $this->createPasswordGrantPayload($username, $password);

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I oauth with :username and :password and scope :scope
     *
     * Acquire an OAuth access token using the 'password' grant with specified scope
     * -
     * Example:
     * Given I oauth with "email@yourdomain.com" and "p4ssw0rd" and scope "view edit"
     */
    public function iOauthWithUsernameAndPasswordAndScope($username, $password, $scope): void
    {
        $payload = $this->createPasswordGrantPayload($username, $password, $scope);

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
    public function iOauthUsingTheClientCredentialsGrant(): void
    {
        $payload = $this->createClientCredentialsGrantPayload();

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I oauth using the client credentials grant with scope :scope
     *
     * Acquire an OAuth access token using the 'client_credentials' grant with specified scope
     * -
     * Example:
     * Given I oauth using the client credentials grant with scope "view edit"
     */
    public function iOauthUsingTheClientCredentialsGrantWithScope($scope): void
    {
        $payload = $this->createClientCredentialsGrantPayload($scope);

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I oauth using the client credentials grant with :id and :secret and scope :scope
     *
     * Acquire an OAuth access token using the 'client_credentials' grant with specified details
     * -
     * Example:
     * Given I oauth using the client credentials grant with "oauth_id" and "oauth_secret" and scope "view edit"
     */
    public function iOauthUsingTheClientCredentialsGrantWithAllDetails($id, $secret, $scope): void
    {
        $payload = $this->buildClientCredentialsGrantPayload($id, $secret, $scope);

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I oauth using the client credentials grant with :id and :secret
     *
     * Acquire an OAuth access token using the 'client_credentials' grant with specified details
     * -
     * Example:
     * Given I oauth using the client credentials grant with "oauth_id" and "oauth_secret"
     */
    public function iOauthUsingTheClientCredentialsGrantWithAllDetailsExceptScope($id, $secret): void
    {
        $payload = $this->buildClientCredentialsGrantPayload($id, $secret);

        $this->sendOauthRequest($payload);
    }

    /**
     * @Given I add a :header header with the value :value
     *
     * Set a header with a specified value
     * -
     * Example:
     * Given I add a "X-My-Header" header with the value "bacon"
     */
    public function iAddAHeaderWithTheValue($header, $value): void
    {
        $this->addHeader($header, $value);
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
    public function iHaveThePayload(PyStringNode $requestPayload): void
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
    public function iRequest($httpMethod, $resource): void
    {
        $method = strtolower($httpMethod);

        $url = sprintf('%s%s', $this->baseUrl, $resource);

        // If there is no authorization header we assume the access token should be passed as a GET parameter
        if ($this->hasHeader('Authorization') === false && $this->accessToken !== null) {
            $url = sprintf('%s?access_token=%s', $url, $this->accessToken);
        }

        $this->response = match ($httpMethod) {
            'PUT', 'POST', 'PATCH' => $this->browser->$method($url, $this->headers, $this->requestPayload),
            default => $this->browser->$method($url, $this->headers),
        };

        $this->responseBody = $this->response->getBody()->getContents();

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
    public function iGetAResponse($statusCode): void
    {
        $response = $this->getResponse();

        $contentType = $response->getHeader('Content-Type')[0];
        $bodyOutput = $this->responseBody;

        if ($contentType !== 'application/json') {
            $bodyOutput = sprintf("Expected 'application/json' content type but got '%s' instead.", $contentType);
        }

        PHPUnit::assertSame(intval($statusCode), $response->getStatusCode(), $bodyOutput);
    }

    /**
     * @Then scope into the :scope property
     *
     * Scope into a property of the current response
     * -
     * Example:
     * Then scope into the "data" property
     */
    public function scopeIntoTheProperty($scope): void
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
    public function scopeIntoTheFirstElement($scope): void
    {
        // Check if there is a current scope
        if ($this->scope === null) {
            $this->scope = sprintf('%s.0', $scope);
        } else {
            $this->scope = sprintf('%s.%s.0', $this->scope, $scope);
        }
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
    public function theStructureMatches(PyStringNode $propertiesString): void
    {
        $payload = $this->getScopePayload();

        // Make sure the payload is in the expected format
        if (is_object($payload) === true) {
            $payload = get_object_vars($payload);
        }

        // Stored for error output
        $originalPayload = $payload;

        $expected = explode("\n", (string)$propertiesString);

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
     * @Then the :field property is an object
     *
     * Checks if the specified field is an object
     * -
     * Example:
     * And the "data" property is an object
     */
    public function thePropertyIsAnObject($property): void
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        PHPUnit::assertIsObject(
            $actualValue, sprintf("Asserting the [%s] property in current scope [%s] is an object", $property, $this->scope)
        );
    }

    /**
     * @Then the :field property is an array
     *
     * Checks if the specified field is an array
     * -
     * Example:
     * And the "genres" property is an array
     */
    public function thePropertyIsAnArray($property): void
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        PHPUnit::assertIsArray(
            $actualValue, sprintf("Asserting the [%s] property in current scope [%s] is an array", $property, $this->scope)
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
    public function thePropertyIsAnArrayWithItems($property, $count): void
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
    public function thePropertyIsAnEmptyArray($property): void
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
    public function thePropertyIsAnInteger($property): void
    {
        $payload = $this->getScopePayload();
        $actual = $this->arrayGet($payload, $property);
        $message = sprintf(
            "Asserting the [%s] property in current scope [%s] is an integer",
            $property,
            $this->scope
        );

        if (method_exists(PHPUnit::class, 'assertInternalType')) {
            PHPUnit::assertInternalType('int', $actual, $message);
        } else {
            PHPUnit::assertIsInt($actual, $message);
        }
    }

    /**
     * @Then the :field property is a integer equaling/equalling :expected
     *
     * Checks if the specified field is an integer with a specified value
     * -
     * Example:
     * And the "id" property is an integer equaling 17
     */
    public function thePropertyIsAIntegerEqualing($property, $expected): void
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->thePropertyIsAnInteger($property);

        PHPUnit::assertSame(
            $actualValue,
            (int)$expected,
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
    public function thePropertyIsAString($property): void
    {
        $payload = $this->getScopePayload();
        $actual = $this->arrayGet($payload, $property);
        $message = sprintf("Asserting the [%s] property in current scope [%s] is a string", $property, $this->scope);

        if (method_exists(PHPUnit::class, 'assertInternalType')) {
            PHPUnit::assertInternalType('string', $actual, $message);
        } else {
            PHPUnit::assertIsString($actual, $message);
        }
    }

    /**
     * @Then the :field property is a string equaling/equalling :expected
     *
     * Checks if the specified field is a string with a specified value
     * -
     * Example:
     * And the "title" property is a string equaling "Pulp Fiction"
     */
    public function thePropertyIsAStringEqualing($property, $expected): void
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
    public function thePropertyIsABoolean($property): void
    {
        $payload = $this->getScopePayload();
        $actual = $this->arrayGet($payload, $property);
        $message = sprintf("Asserting the [%s] property in current scope [%s] is a boolean", $property, $this->scope);

        if (method_exists(PHPUnit::class, 'assertInternalType')) {
            PHPUnit::assertInternalType('boolean', $this->arrayGet($payload, $property), $message);
        } else {
            PHPUnit::assertIsBool($actual, $message);
        }
    }

    /**
     * @Then the :field property is a boolean equaling/equalling :expected
     *
     * Checks if the specified field is a boolean with a specified value
     * -
     * Example:
     * And the "is_released" property is a boolean equaling true
     */
    public function thePropertyIsABooleanEqualing($property, $expected): void
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
    public function echoLastRequest(): void
    {
        $request = $this->browser->getLastRequest();

        if ($request === null) {
            throw new Exception('No request has been made yet.');
        }

        echo sprintf(
            "%s %s HTTP/%s\n",
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getProtocolVersion()
        );

        if (count($request->getHeaders()) > 0) {
            foreach ($request->getHeaders() as $key => $header) {
                echo sprintf("\n%s: %s", $key, implode(',', $header));
            }
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
    public function echoLastResponse(): void
    {
        $response = $this->browser->getLastResponse();

        if ($response === null) {
            throw new Exception('No response has been sent yet.');
        }

        if (count($response->getHeaders()) > 0) {
            foreach ($response->getHeaders() as $key => $header) {
                echo sprintf("%s: %s\n", $key, implode(',', $header));
            }
        }

        if (empty($this->responseBody) === false && $this->isJson($this->responseBody) === true) {
            echo sprintf("\n%s", $this->responseBody);
        }
    }

    private function isJson(string $string): bool
    {
        try {
            json_decode($string, null, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }

    /**
     * Create a payload for the password grant
     */
    protected function createPasswordGrantPayload(string $username, string $password, string $scope = null): array
    {
        if (isset($this->parameters['oauth']) === false) {
            throw new Exception('OAuth details not found in your behat.yml file.');
        }

        $payload = [
            "grant_type" => 'password',
            "username" => $username,
            "password" => $password,
        ];

        // Add scope to payload if it is set
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }

        // Check if client credentials are required for the password grant
        if ($this->parameters['oauth']['password_grant_requires_client_credentials'] === true) {
            $payload["client_id"] = $this->parameters['oauth']['client_id'];
            $payload["client_secret"] = $this->parameters['oauth']['client_secret'];
        }

        return $payload;
    }

    /**
     * Create a payload for the client credentials grant
     */
    public function createClientCredentialsGrantPayload(string $scope = null): array
    {
        if (isset($this->parameters['oauth']) === false) {
            throw new Exception('OAuth details not found in your behat.yml file.');
        }

        $payload = [
            "grant_type" => 'client_credentials',
            "client_id" => $this->parameters['oauth']['client_id'],
            "client_secret" => $this->parameters['oauth']['client_secret'],
        ];

        // Add scope to payload if it is set
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }

        return $payload;
    }

    /**
     * Build a payload for the client credentials grant with the given variables
     */
    public function buildClientCredentialsGrantPayload($id, $secret, ?string $scope = null): array
    {
        $payload = [
            "grant_type" => 'client_credentials',
            "client_id" => $id,
            "client_secret" => $secret,
        ];

        // Add scope to payload if it is set
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }

        return $payload;
    }

    /**
     * Send an OAuth request to the API
     */
    protected function sendOauthRequest(array $payload): void
    {
        $url = sprintf('%s%s', $this->baseUrl, $this->parameters['oauth']['uri']);


        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $response = $this->browser->post($url, $this->headers, http_build_query($payload));

        $responseContent = json_decode($this->responseBody);

        // Throw an exception if the status code is not 200
        if ($response->getStatusCode() !== 200) {
            $errorMessage = 'Authorization Error';
            if (isset($responseContent->error_description) === true) {
                $errorMessage = sprintf('%s: %s', $errorMessage, $responseContent->error_description);
            }

            throw new Exception($errorMessage);
        }

        // Set the authentication token
        $this->setAuthentication($responseContent->access_token);
    }

    /**
     * Set the authentication token as a bearer token or as a normal access token
     */
    protected function setAuthentication(string $accessToken): void
    {
        // Add authorization header if the OAuth config is set to use the bearer authentication scheme
        if ($this->parameters['oauth']['use_bearer_token'] === true) {
            $this->addHeader('Authorization', sprintf('Bearer %s', $accessToken));
            return;
        }

        $this->accessToken = $accessToken;
    }

    /**
     * Returns the payload from the current scope within the response
     */
    protected function getScopePayload(): mixed
    {
        $payload = $this->getResponsePayload();

        if ($this->scope === null) {
            return $payload;
        }

        return $this->arrayGet($payload, $this->scope);
    }

    /**
     * Return the response payload from the current response.
     */
    protected function getResponsePayload(): object
    {
        /** @var object $json */
        $json = json_decode($this->responseBody, false, 512, JSON_THROW_ON_ERROR);

        $this->responsePayload = $json;

        return $this->responsePayload;
    }

    /**
     * Checks the response exists and returns it.
     */
    protected function getResponse(): Response
    {
        if ($this->response === null) {
            throw new Exception("The response was not set.");
        }

        return $this->response;
    }

    /**
     * Reset the headers to the default
     */
    protected function resetHeaders(): void
    {
        $this->headers = [];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        foreach ($headers as $headerName => $headerValue) {
            $this->addHeader($headerName, $headerValue);
        }
    }

    /**
     * Add a header
     */
    protected function addHeader(string $headerName, string $headerValue): void
    {
        $this->headers[$headerName] = $headerValue;
    }

    /**
     * Check if a header exists
     */
    protected function hasHeader(string $headerName): bool
    {
        return isset($this->headers[$headerName]);
    }

    /**
     * Get an item from an array using "dot" notation
     *
     * @link http://laravel.com/docs/helpers
     */
    protected function arrayGet(array|object $array, ?string $key): mixed
    {
        if ($key === null) {
            return $array;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_object($array) === true) {
                if (isset($array->{$segment}) === false) {
                    return null;
                }
                $array = $array->{$segment};
            } elseif (is_array($array) === true) {
                if (array_key_exists($segment, $array) === false) {
                    return null;
                }
                $array = $array[$segment];
            }
        }

        return $array;
    }
}

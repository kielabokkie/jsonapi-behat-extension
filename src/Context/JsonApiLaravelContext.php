<?php namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Buzz\Browser;
use Buzz\Message\Request;
use Exception;
use Kielabokkie\BehatJsonApi\Context\JsonApiAwareInterface;
use Kielabokkie\BehatJsonApi\Context\JsonApiContextTrait;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\TestCase;

/**
 * Defines application features from the specific context.
 */
class JsonApiLaravelContext extends TestCase implements SnippetAcceptingContext, JsonApiAwareInterface
{
    use JsonApiContextTrait;

    /**
     * Initialize the context
     */
    public function __construct()
    {
        // Start with the default set of headers
        $this->resetHeaders();

        parent::setUp();
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
        $url = $resource;

        // If there is no authorization header we assume the access token should be passed as a GET parameter
        if ($this->hasHeader('Authorization') === false && is_null($this->accessToken) === false) {
            $url = sprintf('%s?access_token=%s', $url, $this->accessToken);
        }

        switch ($httpMethod) {
            case 'PUT':
            case 'POST':
            case 'PATCH':
                $this->response = $this->withHeaders($this->headers)
                    ->json($httpMethod, $url, $this->requestPayload);
                break;
            default:
                $this->response = $this->withHeaders($this->headers)
                    ->json($httpMethod, $url);
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

        // $contentType = $response->baseResponse->getHeader('Content-Type');
        $contentType = $response->baseResponse->headers->get('Content-Type');
        $bodyOutput = $response->getContent();

        if ($contentType !== 'application/json') {
            $bodyOutput = sprintf("Expected 'application/json' content type but got '%s' instead.", $contentType);
        }

        PHPUnit::assertSame(intval($statusCode), $this->getResponse()->getStatusCode(), $bodyOutput);
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
        echo 'Echoing the last request is not supported by this context.';
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
        echo 'Echoing the last response is not supported by this context.';
    }

    /**
     * Send an OAuth request to the API
     *
     * @param array $payload [description]
     */
    protected function sendOauthRequest(array $payload)
    {
        $url = $this->parameters['oauth']['uri'];

        $response = $this->withHeaders($this->headers)
            ->json('POST', $url, json_encode($payload));

        $responseContent = json_decode($response->getContent());

        // Throw an exception if the statuscode is not 200
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
}

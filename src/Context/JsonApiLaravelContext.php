<?php

namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Kielabokkie\BehatJsonApi\Context\JsonApiAwareInterface;
use Kielabokkie\BehatJsonApi\Context\JsonApiContextInterface;
use Kielabokkie\BehatJsonApi\Context\JsonApiContextTrait;
use Tests\TestCase;

/**
 * Defines application features from the specific context.
 */
class JsonApiLaravelContext extends TestCase implements SnippetAcceptingContext, JsonApiAwareInterface, JsonApiContextInterface
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
     * Create and execute an API request
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param array $payload
     */
    public function executeRequest(string $url, string $method, array $headers = [], array $payload = [])
    {
        return $this
            ->withHeaders($headers)
            ->json($method, $url, $payload);
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
     * Get the content type of a given request
     *
     * @param Illuminate\Foundation\Testing\TestResponse $response
     */
    public function getContentType($response)
    {
        return $response->baseResponse->headers->get('Content-Type');
    }

}

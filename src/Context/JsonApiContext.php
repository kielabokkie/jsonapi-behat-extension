<?php

namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Buzz\Browser;
use Kielabokkie\BehatJsonApi\Context\JsonApiAwareInterface;
use Kielabokkie\BehatJsonApi\Context\JsonApiContextInterface;
use Kielabokkie\BehatJsonApi\Context\JsonApiContextTrait;

/**
 * Defines application features from the specific context.
 */
class JsonApiContext implements SnippetAcceptingContext, JsonApiAwareInterface, JsonApiContextInterface
{
    use JsonApiContextTrait;

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
     * Create and execute an API request
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param array $payload
     */
    public function executeRequest(string $url, string $method, array $headers = [], array $payload = [])
    {
        $url = sprintf('%s%s', $this->baseUrl, $url);
        $method = strtolower($method);

        return $this
            ->client
            ->$method($url, $headers, json_encode($payload));
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
     * Get the content type of a given request
     *
     * @param Buzz\Message\Response $response
     */
    public function getContentType($response)
    {
        return $response->getHeader('Content-Type');
    }

}

<?php

namespace Kielabokkie\BehatJsonApi\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
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

        self::setUp();
    }

    /**
     * Get a fresh database with seeded data and begin transaction
     *
     * @BeforeScenario
     */
    public function before()
    {
        DB::beginTransaction();

        $this->artisan('migrate:refresh', ['--seed' => true]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Rollback all database changes to return to its initial state
     *
     * @AfterScenario
     */
    public function after()
    {
        DB::rollBack();
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
        $request = request();

        echo sprintf("%s %s %s\n", $request->method(), $request->path(), $request->server('SERVER_PROTOCOL'));

        $headerString = "\n";
        foreach ($request->headers as $key => $header) {
            $headerString .= sprintf("%s: %s\n", $key, implode(' ', $header));
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
        $response = $this->response;

        $headerString = '';
        foreach ($response->headers as $key => $header) {
            $headerString .= sprintf("%s: %s\n", $key, implode(' ', $header));
        }

        echo rtrim($headerString, "\n");

        if (empty($response->getContent()) === false) {
            echo sprintf("\nContent: %s", $response->getContent());
        }
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

<?php namespace Kielabokkie\BehatJsonApi\Context\Initializer;

use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Behat\Context\Context;

class JsonApiInitializer implements ContextInitializer
{
    private $baseUrl;
    private $parameters;

    public function __construct($baseUrl, array $parameters)
    {
        $this->baseUrl = $baseUrl;
        $this->parameters = $parameters;
    }

    public function initializeContext(Context $context)
    {
        // Set the base url used for the tests
        $context->setBaseUrl($this->baseUrl);
        // Add all other parameters
        $context->setParameters($this->parameters);
    }
}

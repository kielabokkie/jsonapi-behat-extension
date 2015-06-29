<?php namespace Kielabokkie\BehatJsonApi\Context\Initializer;

use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Behat\Context\Context;

class JsonApiInitializer implements ContextInitializer
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array
     */
    private array $parameters;

    /**
     * Default constructor
     *
     * @param string $baseUrl    Base url used for the tests
     * @param array  $parameters Other parameters
     */
    public function __construct($baseUrl, array $parameters)
    {
        $this->baseUrl = $baseUrl;
        $this->parameters = $parameters;
    }

    /**
     * Initialize the context
     *
     * @param Context $context [description]
     */
    public function initializeContext(Context $context)
    {
        $context->setBaseUrl($this->baseUrl);
        $context->setParameters($this->parameters);
    }
}

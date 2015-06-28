<?php namespace Kielabokkie\BehatJsonApi\Context\Initializer;

use Behat\Behat\Context\Initializer\InitializerInterface;
use Behat\Behat\Context\ContextInterface;

class JsonApiInitializer implements InitializerInterface
{
    private $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function initializeContext(ContextInterface $context)
    {
        // Add all parameters to the context
        $context->setParameters($this->parameters);
    }
}

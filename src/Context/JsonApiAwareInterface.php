<?php

namespace Kielabokkie\BehatJsonApi\Context;

/**
 * Interface JsonApiAwareContext.
 *
 * @package Kielabokkie\BehatJsonApi\Context
 */
interface JsonApiAwareInterface
{

    /**
     * Set the base url (specified in behat.yml)
     *
     * @param string $baseUrl [description]
     */
    public function setBaseUrl($baseUrl);

    /**
     * Set extension specific parameters (specified in behat.yml)
     */
    public function setParameters(array $parameters);

}

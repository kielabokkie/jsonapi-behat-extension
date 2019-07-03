<?php

namespace Kielabokkie\BehatJsonApi\Context;

/**
 * Interface JsonApiAwareContext.
 *
 * @package Kielabokkie\BehatJsonApi\Context
 */
interface JsonApiContextInterface
{

    public function executeRequest(string $url, string $method, array $headers, array $payload);
    public function echoLastRequest();
    public function echoLastResponse();
    public function getContentType($response);

}

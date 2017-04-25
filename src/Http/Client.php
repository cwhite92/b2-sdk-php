<?php

namespace ChrisWhite\B2\Http;

use ChrisWhite\B2\ErrorHandler;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Client wrapper around Guzzle.
 *
 * @package ChrisWhite\B2\Http
 */
class Client extends GuzzleClient
{
    protected $retryLimit = 10;
    protected $retryWaitSec = 10;

    /**
     * Sends a response to the B2 API, automatically handling decoding JSON and errors.
     *
     * @param string $method
     * @param null $uri
     * @param array $options
     * @param bool $asJson
     * @return mixed|string
     */
    public function request($method, $uri = null, array $options = [], $asJson = true)
    {
        $response = parent::request($method, $uri, $options);

        // Support for 503 "too busy errors". Retry multiple times before failure
        $retries = 0;
        $wait = $this->retryWaitSec;
        while ($response->getStatusCode() === 503 and $this->retryLimit > $retries) {
            $retries++;
            sleep($wait);
            $response = parent::request($method, $uri, $options);
            // Wait 20% longer if it fails again
            $wait *= 1.2;
        }
        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        if ($asJson) {
            return json_decode($response->getBody(), true);
        }

        return $response->getBody()->getContents();
    }
}

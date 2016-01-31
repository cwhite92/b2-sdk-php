<?php

namespace ChrisWhite\B2\Http;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Client wrapper around Guzzle.
 *
 * @package ChrisWhite\B2\Http
 */
class Client extends GuzzleClient
{
    /**
     * Provide a responsible and helpful user agent when making HTTP requests.
     *
     * @return string
     */
    public function getDefaultUserAgent()
    {
        return 'b2-sdk-php'.
            ' Guzzle/'.curl_version()['version'].
            ' cURL'.
            ' PHP/'.PHP_VERSION;
    }
}
<?php

namespace ChrisWhite\B2\Tests;

trait TestHelper
{
    protected function buildGuzzleFromResponses(array $responses)
    {
        $mock = new \GuzzleHttp\Handler\MockHandler($responses);
        $handler = new \GuzzleHttp\HandlerStack($mock);

        return new \GuzzleHttp\Client(['handler' => $handler]);
    }

    protected function buildResponseFromStub($statusCode, array $headers = [], $responseFile)
    {
        $response = file_get_contents(dirname(__FILE__).'/responses/'.$responseFile);

        return new \GuzzleHttp\Psr7\Response($statusCode, $headers, $response);
    }
}
<?php

namespace ChrisWhite\B2;

use ChrisWhite\B2\Exceptions\B2Exception;

class ErrorHandler
{
    protected static $mappings = [
        'bad_json' => Exceptions\BadJsonException::class,
        'duplicate_bucket_name' => Exceptions\BucketAlreadyExistsException::class
    ];

    public static function handleErrorResponse($response)
    {
        $responseJson = json_decode($response->getBody(), true);

        if (isset(self::$mappings[$responseJson['code']])) {
            $exceptionClass = self::$mappings[$responseJson['code']];
        } else {
            // We don't have an exception mapped to this response error, throw generic exception
            $exceptionClass = B2Exception::class;
        }

        throw new $exceptionClass('Received error from B2: '.$responseJson['message']);
    }
}
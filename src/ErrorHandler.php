<?php

namespace ChrisWhite\B2;

use ChrisWhite\B2\Exceptions\B2Exception;
use ChrisWhite\B2\Exceptions\BadJsonException;
use ChrisWhite\B2\Exceptions\BadValueException;
use ChrisWhite\B2\Exceptions\BucketAlreadyExistsException;
use ChrisWhite\B2\Exceptions\NotFoundException;
use ChrisWhite\B2\Exceptions\FileNotPresentException;

class ErrorHandler
{
    protected static $mappings = [
        'bad_json' => BadJsonException::class,
        'bad_value' => BadValueException::class,
        'duplicate_bucket_name' => BucketAlreadyExistsException::class,
        'not_found' => NotFoundException::class,
        'file_not_present' => FileNotPresentException::class
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
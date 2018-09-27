<?php

namespace ChrisWhite\B2;

use ChrisWhite\B2\Exceptions\B2Exception;
use ChrisWhite\B2\Exceptions\BadJsonException;
use ChrisWhite\B2\Exceptions\BadValueException;
use ChrisWhite\B2\Exceptions\BucketAlreadyExistsException;
use ChrisWhite\B2\Exceptions\BucketNotEmptyException;
use ChrisWhite\B2\Exceptions\FileNotPresentException;
use ChrisWhite\B2\Exceptions\NotFoundException;
use GuzzleHttp\Psr7\Response;

class ErrorHandler
{
    protected static $mappings = [
        'bad_json'                       => BadJsonException::class,
        'bad_value'                      => BadValueException::class,
        'duplicate_bucket_name'          => BucketAlreadyExistsException::class,
        'not_found'                      => NotFoundException::class,
        'file_not_present'               => FileNotPresentException::class,
        'cannot_delete_non_empty_bucket' => BucketNotEmptyException::class,
    ];

    public static function handleErrorResponse(Response $response)
    {
        $responseJson = json_decode($response->getBody(), true);

        if (isset(self::$mappings[$responseJson['code']])) {
            $exceptionClass = self::$mappings[$responseJson['code']];
        } else {
            // We don't have an exception mapped to this response error, throw generic exception
            $exceptionClass = B2Exception::class;
        }

        throw new $exceptionClass(sprintf('Received error from B2: %s. Code: %s', $responseJson['message'], $responseJson['code']));
    }
}

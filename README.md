## b2-sdk-php

`b2-sdk-php` is a small(ish) client library for working with Backblaze's B2 storage service. It aims to make using the
service as easy as possible while taking influence from other, similar clients.

## Example

The API is subject to change.

    $client = new Client('accountId', 'applicationKey');

    // Returns a Bucket object.
    $bucket = $client->createBucket('bucket-name', Bucket::TYPE_PUBLIC);

    // Change the bucket to private.
    $updatedBucket = $client->updateBucket($bucket->getId(), Bucket::TYPE_PRIVATE);

    // Retrieve a list of Bucket objects representing the buckets on your account.
    $buckets = $client->listBuckets();

    // Delete a bucket
    $client->deleteBucket($bucket->getId());

    // Upload a file as a string or from a resource. Returns a File object.
    $stringFile = $client->upload($bucket->getId(), '/path/to/upload/to', 'Lorem ipsum.');
    $handle = fopen('/path/to/file/to/upload', 'r');
    $resourceFile = $client->upload($bucket->getId(), '/path/to/upload/to', $handle);

    // Downloads a file by its file ID or path, storing it in a variable or saving to a file on disk.
    $fileContent = $client->downloadById($file->getId());
    $client->downloadByPath($bucket->getName(), $file->getId(), '/path/to/save/to');

## Installation

To come.

## Tests

Tests are run with PHPUnit. After installing PHPUnit via composer:

    vendor/bin/phpunit

## Contributors

Feel free to contribute in any way you can whether that be reporting issues, making suggestions or sending PRs. :)

## License

MIT.

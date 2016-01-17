<?php

namespace ChrisWhite\B2\Tests;

use ChrisWhite\B2\Bucket;
use ChrisWhite\B2\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;

    public function testListBuckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_3.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_0.json')
        ]);

        // Test that we get an array of 3 bucket objects back
        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(3, $buckets);
        $this->assertInstanceOf(Bucket::class, $buckets[0]);

        // Test that we get an empty array when we have no buckets
        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(0, $buckets);
    }
}
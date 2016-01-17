<?php

namespace ChrisWhite\B2\Tests;

use ChrisWhite\B2\Bucket;
use ChrisWhite\B2\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;

    public function testCreateBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_private.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_public.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get a bucket object back after creation
        $bucket = $client->createBucket('Test bucket', Bucket::TYPE_PRIVATE);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('Test bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PRIVATE, $bucket->getType());
    }

    public function testListBuckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_3.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_0.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get an array of 3 bucket objects back
        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(3, $buckets);
        $this->assertInstanceOf(Bucket::class, $buckets[0]);

        // Test that we get an empty array when we have no buckets
        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(0, $buckets);
    }

    public function testDeleteBuckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'delete_bucket.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $this->assertTrue($client->deleteBucket('testId'));
    }
}
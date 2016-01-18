<?php

namespace ChrisWhite\B2\Tests;

use ChrisWhite\B2\Bucket;
use ChrisWhite\B2\Client;
use ChrisWhite\B2\Exceptions\BucketAlreadyExistsException;
use ChrisWhite\B2\Exceptions\BadJsonException;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;

    public function testCreatePublicBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_public.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get a public bucket back after creation
        $bucket = $client->createBucket('Test bucket', Bucket::TYPE_PUBLIC);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('Test bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PUBLIC, $bucket->getType());
    }

    public function testCreatePrivateBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_private.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get a private bucket back after creation
        $bucket = $client->createBucket('Test bucket', Bucket::TYPE_PRIVATE);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('Test bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PRIVATE, $bucket->getType());
    }

    public function testBucketAlreadyExistsExceptionThrown()
    {
        $this->setExpectedException(BucketAlreadyExistsException::class);

        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(400, [], 'create_bucket_exists.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $client->createBucket('I already exist', Bucket::TYPE_PRIVATE);
    }

    public function testList3Buckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_3.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(3, $buckets);
        $this->assertInstanceOf(Bucket::class, $buckets[0]);
    }

    public function testEmptyArrayWithNoBuckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_0.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(0, $buckets);
    }

    public function testDeleteBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'delete_bucket.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $this->assertTrue($client->deleteBucket('testId'));
    }

    public function testBadJsonThrownDeletingNonExistentBucket()
    {
        $this->setExpectedException(BadJsonException::class);

        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(400, [], 'delete_bucket_non_existent.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $client->deleteBucket('i-dont-exist');
    }
}
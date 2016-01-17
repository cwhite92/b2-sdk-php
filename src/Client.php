<?php

namespace ChrisWhite\B2;

class Client
{
    protected $accountId;
    protected $applicationKey;

    protected $authToken;
    protected $apiUrl;
    protected $downloadUrl;

    protected $client;

    public function __construct($accountId, $applicationKey, array $options = [])
    {
        $this->accountId = $accountId;
        $this->applicationKey = $applicationKey;

        if (isset($options['client']) && $options['client'] instanceof \GuzzleHttp\Client) {
            $this->client = $options['client'];
        }

        $this->authoriseAccount();
    }

    /**
     * Create a bucket with the given name and type.
     * TODO: add proper error checking.
     *
     * @param $name
     * @param $type
     * @return Bucket
     */
    public function createBucket($name, $type)
    {
        $response = $this->client->post($this->apiUrl.'/b2_create_bucket', [
            'Authorization' => $this->authToken
        ], [
            'accountId' => $this->accountId,
            'bucketName' => $name,
            'bucketType' => $type
        ]);

        $response = json_decode($response->getBody(), true);

        return new Bucket($response['bucketId'], $response['bucketName'], $response['bucketType']);
    }

    /**
     * Returns a list of bucket objects representing the buckets on the account.
     *
     * @return array
     */
    public function listBuckets()
    {
        $buckets = [];
        $response = json_decode($this->client->get($this->apiUrl.'/b2_list_buckets')->getBody(), true);

        foreach ($response['buckets'] as $bucket) {
            $buckets[] = new Bucket($bucket['bucketId'], $bucket['bucketName'], $bucket['bucketType']);
        }

        return $buckets;
    }

    /**
     * Deletes the bucket identified by ID.
     * TODO: add proper error handling.
     *
     * @param $id
     * @return bool
     */
    public function deleteBucket($id)
    {
        $response = $this->client->post($this->apiUrl.'/b2_delete_bucket', [
            'Authorization' => $this->authToken
        ], [
            'accountId' => $this->accountId,
            'bucketId' => $id
        ]);

        return true;
    }

    /**
     * Authorise the B2 account in order to get an auth token and API/download URLs.
     *
     * @throws \Exception
     */
    protected function authoriseAccount()
    {
        $response = $this->client->get('https://api.backblaze.com/b2api/v1/b2_authorize_account', [
            'auth' => [$this->accountId, $this->applicationKey]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Received non-200 status code when authorizing account: '.$response->getStatusCode());
        }

        $response = json_decode($response->getBody(), true);

        $this->authToken = $response['authorizationToken'];
        $this->apiUrl = $response['apiUrl'].'/b2api/v1';
        $this->downloadUrl = $response['downloadUrl'];
    }
}
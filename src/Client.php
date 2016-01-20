<?php

namespace ChrisWhite\B2;

use ChrisWhite\B2\Exceptions\ValidationException;

class Client
{
    protected $accountId;
    protected $applicationKey;

    protected $authToken;
    protected $apiUrl;
    protected $downloadUrl;

    protected $client;

    /**
     * Client constructor. Accepts the account ID, application key and an optional array of options.
     *
     * @param $accountId
     * @param $applicationKey
     * @param array $options
     */
    public function __construct($accountId, $applicationKey, array $options = [])
    {
        $this->accountId = $accountId;
        $this->applicationKey = $applicationKey;

        if (isset($options['client']) && $options['client'] instanceof \GuzzleHttp\Client) {
            $this->client = $options['client'];
        } else {
            $this->client = new \GuzzleHttp\Client(['exceptions' => false]);
        }

        $this->authoriseAccount();
    }

    /**
     * Create a bucket with the given name and type.
     *
     * @param $name
     * @param $type
     * @return Bucket
     * @throws ValidationException
     */
    public function createBucket($name, $type)
    {
        if (!in_array($type, [Bucket::TYPE_PUBLIC, Bucket::TYPE_PRIVATE])) {
            throw new ValidationException(
                sprintf('Bucket type must be %s or %s', Bucket::TYPE_PRIVATE, Bucket::TYPE_PUBLIC)
            );
        }

        $response = $this->client->request('POST', $this->apiUrl.'/b2_create_bucket', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json' => [
                'accountId' => $this->accountId,
                'bucketName' => $name,
                'bucketType' => $type
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        $responseJson = json_decode($response->getBody(), true);

        return new Bucket($responseJson['bucketId'], $responseJson['bucketName'], $responseJson['bucketType']);
    }

    /**
     * Updates the type attribute of a bucket by the given ID.
     *
     * @param $id
     * @param $type
     * @return Bucket
     * @throws ValidationException
     */
    public function updateBucket($id, $type)
    {
        if (!in_array($type, [Bucket::TYPE_PUBLIC, Bucket::TYPE_PRIVATE])) {
            throw new ValidationException(
                sprintf('Bucket type must be %s or %s', Bucket::TYPE_PRIVATE, Bucket::TYPE_PUBLIC)
            );
        }

        $response = $this->client->request('POST', $this->apiUrl.'/b2_update_bucket', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json' => [
                'accountId' => $this->accountId,
                'bucketId' => $id,
                'bucketType' => $type
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        $responseJson = json_decode($response->getBody(), true);

        return new Bucket($responseJson['bucketId'], $responseJson['bucketName'], $responseJson['bucketType']);
    }

    /**
     * Returns a list of bucket objects representing the buckets on the account.
     *
     * @return array
     */
    public function listBuckets()
    {
        $buckets = [];

        $response = $this->client->request('POST', $this->apiUrl.'/b2_list_buckets', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json' => [
                'accountId' => $this->accountId
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        $responseJson = json_decode($response->getBody(), true);

        foreach ($responseJson['buckets'] as $bucket) {
            $buckets[] = new Bucket($bucket['bucketId'], $bucket['bucketName'], $bucket['bucketType']);
        }

        return $buckets;
    }

    /**
     * Deletes the bucket identified by its ID.
     *
     * @param $id
     * @return bool
     */
    public function deleteBucket($id)
    {
        $response = $this->client->request('POST', $this->apiUrl.'/b2_delete_bucket', [
            'headers' => [
                'Authorization' => $this->authToken
            ],
            'json' => [
                'accountId' => $this->accountId,
                'bucketId' => $id
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        return true;
    }

    /**
     * Uploads a file to a bucket and returns a File object.
     *
     * @param $bucketId
     * @param $path
     * @param $body
     * @return bool
     */
    public function upload($bucketId, $path, $body)
    {
        // Clean the path if it starts with /.
        if (substr($path, 0, 1) === '/') {
            $path = ltrim($path, '/');
        }

        // Retrieve the URL that we should be uploading to.
        $response = $this->client->post($this->apiUrl.'/b2_get_upload_url', [
            'headers' => [
                'Authorization' => $this->authToken
            ],
            'json' => [
                'bucketId' => $bucketId
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        $responseJson = json_decode($response->getBody(), true);
        $uploadEndpoint = $responseJson['uploadUrl'];
        $uploadAuthToken = $responseJson['authorizationToken'];

        if (is_resource($body)) {
            // We need to calculate the hash incrementally from the stream.
            $context = hash_init('sha1');
            hash_update_stream($context, $body);
            $hash = hash_final($context);

            // Similarly, we have to use fstat to get the size of the stream.
            $size = fstat($body)['size'];
        } else {
            // We've been given a simple string body, it's super simple to calculate the hash and size.
            $hash = sha1($body);
            $size = mb_strlen($body);
        }

        $response = $this->client->post($uploadEndpoint, [
            'headers' => [
                'Authorization' => $uploadAuthToken,
                // TODO: work out the content type, or allow it to be passed in
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => $size,
                'X-Bz-File-Name' => $path,
                'X-Bz-Content-Sha1' => $hash,
                // TODO: work out the last modified time
                'X-Bz-Info-src_last_modified_millis' => round(microtime(true) * 1000)
            ],
            'body' => $body
        ]);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        $responseJson = json_decode($response->getBody(), true);

        return new File(
            $responseJson['fileId'],
            $responseJson['fileName'],
            $responseJson['contentSha1'],
            $responseJson['contentLength'],
            $responseJson['contentType'],
            $responseJson['fileInfo']
        );
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
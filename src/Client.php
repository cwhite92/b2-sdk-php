<?php

namespace ChrisWhite\B2;

use ChrisWhite\B2\Exceptions\CacheException;
use ChrisWhite\B2\Exceptions\NotFoundException;
use ChrisWhite\B2\Exceptions\ValidationException;
use ChrisWhite\B2\Http\Client as HttpClient;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;

class Client
{
    protected $accountId;
    protected $applicationKey;
    protected $cache;

    protected $authToken;
    protected $apiUrl;
    protected $downloadUrl;
    protected $recommendedPartSize;

    protected $client;

    /**
     * Lower limit for using large files upload support. More information:
     * https://www.backblaze.com/b2/docs/large_files.html. Default: 3 GB
     * Files larger than this value will be uploaded in multiple parts.
     *
     * @var int
     */
    protected $largeFileLimit = 3000000000;

    /**
     * Client constructor. Accepts the account ID, application key and an optional array of options.
     *
     * @param $accountId
     * @param $applicationKey
     * @param array $options
     */
    public function __construct($accountId, $applicationKey, array $options = [])
    {
        $this->accountId      = $accountId;
        $this->applicationKey = $applicationKey;

        if (isset($options['client'])) {
            $this->client = $options['client'];
        } else {
            $this->client = new HttpClient(['exceptions' => false]);
        }

        // initialize cache
        $this->createCacheContainer();

        $this->authorizeAccount();
    }

    private function createCacheContainer()
    {
        $container           = new Container;
        $container['config'] = [
            'cache.default'     => 'file',
            'cache.stores.file' => [
                'driver' => 'file',
                'path'   => __DIR__ . '/Cache',
            ],
        ];
        $container['files'] = new Filesystem;

        try {
            $cacheManager = new CacheManager($container);
            $this->cache  = $cacheManager->store();
        } catch (\Exception $e) {
            throw new CacheException(
                $e->getMessage()
            );
        }
    }

    /**
     * Create a bucket with the given name and type.
     *
     * @param array $options
     * @return Bucket
     * @throws ValidationException
     */
    public function createBucket(array $options)
    {
        if (!in_array($options['BucketType'], [Bucket::TYPE_PUBLIC, Bucket::TYPE_PRIVATE])) {
            throw new ValidationException(
                sprintf('Bucket type must be %s or %s', Bucket::TYPE_PRIVATE, Bucket::TYPE_PUBLIC)
            );
        }

        $response = $this->client->request('POST', $this->apiUrl . '/b2_create_bucket', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'accountId'  => $this->accountId,
                'bucketName' => $options['BucketName'],
                'bucketType' => $options['BucketType'],
            ],
        ]);

        return new Bucket($response['bucketId'], $response['bucketName'], $response['bucketType']);
    }

    /**
     * Updates the type attribute of a bucket by the given ID.
     *
     * @param array $options
     * @return Bucket
     * @throws ValidationException
     */
    public function updateBucket(array $options)
    {
        if (!in_array($options['BucketType'], [Bucket::TYPE_PUBLIC, Bucket::TYPE_PRIVATE])) {
            throw new ValidationException(
                sprintf('Bucket type must be %s or %s', Bucket::TYPE_PRIVATE, Bucket::TYPE_PUBLIC)
            );
        }

        if (!isset($options['BucketId']) && isset($options['BucketName'])) {
            $options['BucketId'] = $this->getBucketIdFromName($options['BucketName']);
        }

        $response = $this->client->request('POST', $this->apiUrl . '/b2_update_bucket', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'accountId'  => $this->accountId,
                'bucketId'   => $options['BucketId'],
                'bucketType' => $options['BucketType'],
            ],
        ]);

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

        $response = $this->client->request('POST', $this->apiUrl . '/b2_list_buckets', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'accountId' => $this->accountId,
            ],
        ]);

        foreach ($response['buckets'] as $bucket) {
            $buckets[] = new Bucket($bucket['bucketId'], $bucket['bucketName'], $bucket['bucketType']);
        }

        return $buckets;
    }

    /**
     * Deletes the bucket identified by its ID.
     *
     * @param array $options
     * @return bool
     */
    public function deleteBucket(array $options)
    {
        if (!isset($options['BucketId']) && isset($options['BucketName'])) {
            $options['BucketId'] = $this->getBucketIdFromName($options['BucketName']);
        }

        $this->client->request('POST', $this->apiUrl . '/b2_delete_bucket', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'accountId' => $this->accountId,
                'bucketId'  => $options['BucketId'],
            ],
        ]);

        return true;
    }

    /**
     * Uploads a file to a bucket and returns a File object.
     *
     * @param array $options
     * @return File
     */
    public function upload(array $options)
    {
        // Clean the path if it starts with /.
        if (substr($options['FileName'], 0, 1) === '/') {
            $options['FileName'] = ltrim($options['FileName'], '/');
        }

        if (!isset($options['BucketId']) && isset($options['BucketName'])) {
            $options['BucketId'] = $this->getBucketIdFromName($options['BucketName']);
        }

        if (!isset($options['FileLastModified'])) {
            $options['FileLastModified'] = round(microtime(true) * 1000);
        }

        if (!isset($options['FileContentType'])) {
            $options['FileContentType'] = 'b2/x-auto';
        }

        list($options['hash'], $options['size']) = $this->getFileHashAndSize($options['Body']);

        if ($options['size'] <= $this->largeFileLimit && $options['size'] <= $this->recommendedPartSize) {
            return $this->uploadStandardFile($options);
        } else {
            return $this->uploadLargeFile($options);
        }
    }

    /**
     * Download a file from a B2 bucket.
     *
     * @param array $options
     * @return bool|mixed|string
     */
    public function download(array $options)
    {
        $requestUrl     = null;
        $requestOptions = [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'sink'    => isset($options['SaveAs']) ? $options['SaveAs'] : fopen('php://temp', 'w'),
        ];

        if (isset($options['FileId'])) {
            $requestOptions['query'] = ['fileId' => $options['FileId']];
            $requestUrl              = $this->downloadUrl . '/b2api/v1/b2_download_file_by_id';
        } else {
            if (!isset($options['BucketName']) && isset($options['BucketId'])) {
                $options['BucketName'] = $this->getBucketNameFromId($options['BucketId']);
            }

            $requestUrl = sprintf('%s/file/%s/%s', $this->downloadUrl, $options['BucketName'], $options['FileName']);
        }

        if (isset($options['stream'])) {
            $requestOptions['stream'] = $options['stream'];
            $response                 = $this->client->request('GET', $requestUrl, $requestOptions, false, false);
        } else {
            $response = $this->client->request('GET', $requestUrl, $requestOptions, false);
        }

        return isset($options['SaveAs']) ? true : $response;
    }

    /**
     * Retrieve a collection of File objects representing the files stored inside a bucket.
     *
     * @param array $options
     * @return array
     */
    public function listFiles(array $options)
    {
        // if FileName is set, we only attempt to retrieve information about that single file.
        $fileName = !empty($options['FileName']) ? $options['FileName'] : null;

        $nextFileName = null;
        $maxFileCount = 1000;
        $files        = [];

        if (!isset($options['BucketId']) && isset($options['BucketName'])) {
            $options['BucketId'] = $this->getBucketIdFromName($options['BucketName']);
        }

        if ($fileName) {
            $nextFileName = $fileName;
            $maxFileCount = 1;
        }

        // B2 returns, at most, 1000 files per "page". Loop through the pages and compile an array of File objects.
        while (true) {
            $response = $this->client->request('POST', $this->apiUrl . '/b2_list_file_names', [
                'headers' => [
                    'Authorization' => $this->authToken,
                ],
                'json'    => [
                    'bucketId'      => $options['BucketId'],
                    'startFileName' => $nextFileName,
                    'maxFileCount'  => $maxFileCount,
                ],
            ]);

            foreach ($response['files'] as $file) {
                // if we have a file name set, only retrieve information if the file name matches
                if (!$fileName || ($fileName === $file['fileName'])) {
                    $files[] = new File($file['fileId'], $file['fileName'], null, $file['size']);
                }
            }

            if ($fileName || $response['nextFileName'] === null) {
                // We've got all the files - break out of loop.
                break;
            }

            $nextFileName = $response['nextFileName'];
        }

        return $files;
    }

    /**
     * Test whether a file exists in B2 for the given bucket.
     *
     * @param array $options
     * @return boolean
     */
    public function fileExists(array $options)
    {
        $files = $this->listFiles($options);

        return !empty($files);
    }

    /**
     * Returns a single File object representing a file stored on B2.
     *
     * @param array $options
     * @throws NotFoundException If no file id was provided and BucketName + FileName does not resolve to a file, a NotFoundException is thrown.
     * @return File
     */
    public function getFile(array $options)
    {
        if (!isset($options['FileId']) && isset($options['BucketName']) && isset($options['FileName'])) {
            $options['FileId'] = $this->getFileIdFromBucketAndFileName($options['BucketName'], $options['FileName']);

            if (!$options['FileId']) {
                throw new NotFoundException();
            }
        }

        $response = $this->client->request('POST', $this->apiUrl . '/b2_get_file_info', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'fileId' => $options['FileId'],
            ],
        ]);

        return new File(
            $response['fileId'],
            $response['fileName'],
            $response['contentSha1'],
            $response['contentLength'],
            $response['contentType'],
            $response['fileInfo'],
            $response['bucketId'],
            $response['action'],
            $response['uploadTimestamp']
        );
    }

    /**
     * Deletes the file identified by ID from Backblaze B2.
     *
     * @param array $options
     * @return bool
     */
    public function deleteFile(array $options)
    {
        if (!isset($options['FileName'])) {
            $file = $this->getFile($options);

            $options['FileName'] = $file->getName();
        }

        if (!isset($options['FileId']) && isset($options['BucketName']) && isset($options['FileName'])) {
            $file = $this->getFile($options);

            $options['FileId'] = $file->getId();
        }

        $this->client->request('POST', $this->apiUrl . '/b2_delete_file_version', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'fileName' => $options['FileName'],
                'fileId'   => $options['FileId'],
            ],
        ]);

        return true;
    }

    /**
     * Authorize the B2 account in order to get an auth token and API/download URLs.
     *
     * @throws \Exception
     */
    protected function authorizeAccount()
    {
        $client         = $this->client;
        $accountId      = $this->accountId;
        $applicationKey = $this->applicationKey;

        $response = $this->cache->remember('RunCloud-B2-SDK-Authorization', 60, function () use ($client, $accountId, $applicationKey) {
            return $client->request('GET', 'https://api.backblazeb2.com/b2api/v1/b2_authorize_account', [
                'auth' => [$accountId, $applicationKey],
            ]);
        });

        $this->authToken           = $response['authorizationToken'];
        $this->apiUrl              = $response['apiUrl'] . '/b2api/v1';
        $this->downloadUrl         = $response['downloadUrl'];
        $this->recommendedPartSize = $response['recommendedPartSize'];
    }

    /**
     * Maps the provided bucket name to the appropriate bucket ID.
     *
     * @param $name
     * @return null
     */
    protected function getBucketIdFromName($name)
    {
        $buckets = $this->listBuckets();

        foreach ($buckets as $bucket) {
            if ($bucket->getName() === $name) {
                return $bucket->getId();
            }
        }

        return null;
    }

    /**
     * Maps the provided bucket ID to the appropriate bucket name.
     *
     * @param $id
     * @return null
     */
    protected function getBucketNameFromId($id)
    {
        $buckets = $this->listBuckets();

        foreach ($buckets as $bucket) {
            if ($bucket->getId() === $id) {
                return $bucket->getName();
            }
        }

        return null;
    }

    protected function getFileIdFromBucketAndFileName($bucketName, $fileName)
    {
        $files = $this->listFiles([
            'BucketName' => $bucketName,
            'FileName'   => $fileName,
        ]);

        foreach ($files as $file) {
            if ($file->getName() === $fileName) {
                return $file->getId();
            }
        }

        return null;
    }

    /**
     * Calculate hash and size of file/stream. If $offset and $partSize is given return
     * hash and size of this chunk
     *
     * @param $content
     * @param int $offset
     * @param null $partSize
     * @return array
     */
    protected function getFileHashAndSize($data, $offset = 0, $partSize = null)
    {
        if (!$partSize) {
            if (is_resource($data)) {
                // We need to calculate the file's hash incrementally from the stream.
                $context = hash_init('sha1');
                hash_update_stream($context, $data);
                $hash = hash_final($context);
                // Similarly, we have to use fstat to get the size of the stream.
                $size = fstat($data)['size'];
                // Rewind the stream before passing it to the HTTP client.
                rewind($data);
            } else {
                // We've been given a simple string body, it's super simple to calculate the hash and size.
                $hash = sha1($data);
                $size = mb_strlen($data, '8bit');
            }
        } else {
            $dataPart = $this->getPartOfFile($data, $offset, $partSize);
            $hash     = sha1($dataPart);
            $size     = mb_strlen($dataPart, '8bit');
        }

        return array($hash, $size);
    }

    /**
     * Return selected part of file
     *
     * @param $data
     * @param $offset
     * @param $partSize
     * @return bool|string
     */
    protected function getPartOfFile($data, $offset, $partSize)
    {
        // Get size and hash of one data chunk
        if (is_resource($data)) {
            // Get data chunk
            fseek($data, $offset);
            $dataPart = fread($data, $partSize);
            // Rewind the stream before passing it to the HTTP client.
            rewind($data);
        } else {
            $dataPart = substr($data, $offset, $partSize);
        }
        return $dataPart;
    }

    /**
     * Upload single file (smaller than 3 GB)
     *
     * @param array $options
     * @return File
     */
    protected function uploadStandardFile($options = array())
    {
        // Retrieve the URL that we should be uploading to.
        $response = $this->client->request('POST', $this->apiUrl . '/b2_get_upload_url', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'bucketId' => $options['BucketId'],
            ],
        ]);

        $uploadEndpoint  = $response['uploadUrl'];
        $uploadAuthToken = $response['authorizationToken'];

        $response = $this->client->request('POST', $uploadEndpoint, [
            'headers' => [
                'Authorization'                      => $uploadAuthToken,
                'Content-Type'                       => $options['FileContentType'],
                'Content-Length'                     => $options['size'],
                'X-Bz-File-Name'                     => $options['FileName'],
                'X-Bz-Content-Sha1'                  => $options['hash'],
                'X-Bz-Info-src_last_modified_millis' => $options['FileLastModified'],
            ],
            'body'    => $options['Body'],
        ]);

        return new File(
            $response['fileId'],
            $response['fileName'],
            $response['contentSha1'],
            $response['contentLength'],
            $response['contentType'],
            $response['fileInfo']
        );
    }

    /**
     * Upload large file. Large files will be uploaded in chunks of recommendedPartSize bytes (usually 100MB each)
     *
     * @param array $options
     * @return File
     */
    protected function uploadLargeFile($options)
    {
        // Prepare for uploading the parts of a large file.
        $response = $this->client->request('POST', $this->apiUrl . '/b2_start_large_file', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'bucketId'    => $options['BucketId'],
                'fileName'    => $options['FileName'],
                'contentType' => $options['FileContentType'],
                /**
                'fileInfo' => [
                'src_last_modified_millis' => $options['FileLastModified'],
                'large_file_sha1' => $options['hash']
                ]
                 **/
            ],
        ]);
        $fileId = $response['fileId'];

        $partsCount = ceil($options['size'] / $this->recommendedPartSize);

        $hashParts = [];
        for ($i = 1; $i <= $partsCount; $i++) {
            $bytesSent = ($i - 1) * $this->recommendedPartSize;
            $bytesLeft = $options['size'] - $bytesSent;
            $partSize  = ($bytesLeft > $this->recommendedPartSize) ? $this->recommendedPartSize : $bytesLeft;

            // Retrieve the URL that we should be uploading to.
            $response = $this->client->request('POST', $this->apiUrl . '/b2_get_upload_part_url', [
                'headers' => [
                    'Authorization' => $this->authToken,
                ],
                'json'    => [
                    'fileId' => $fileId,
                ],
            ]);

            $uploadEndpoint  = $response['uploadUrl'];
            $uploadAuthToken = $response['authorizationToken'];

            list($hash, $size) = $this->getFileHashAndSize($options['Body'], $bytesSent, $partSize);
            $hashParts[]       = $hash;

            $response = $this->client->request('POST', $uploadEndpoint, [
                'headers' => [
                    'Authorization'     => $uploadAuthToken,
                    'X-Bz-Part-Number'  => $i,
                    'Content-Length'    => $size,
                    'X-Bz-Content-Sha1' => $hash,
                ],
                'body'    => $this->getPartOfFile($options['Body'], $bytesSent, $partSize),
            ]);
        }

        // Finish upload of large file
        $response = $this->client->request('POST', $this->apiUrl . '/b2_finish_large_file', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'json'    => [
                'fileId'        => $fileId,
                'partSha1Array' => $hashParts,
            ],
        ]);
        return new File(
            $response['fileId'],
            $response['fileName'],
            $response['contentSha1'],
            $response['contentLength'],
            $response['contentType'],
            $response['fileInfo']
        );
    }
}

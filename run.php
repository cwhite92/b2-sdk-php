<?php

require ('vendor/autoload.php');

$client = new \ChrisWhite\B2\Client('cb561ac2f6eb', '001415c09c2139b77637a05b155baeef43c0b225cd');

$bucket = $client->createBucket('test-bucsdfsdfket-fh7f', \ChrisWhite\B2\Bucket::TYPE_PUBLIC);

$client->updateBucket($bucket->getId(), \ChrisWhite\B2\Bucket::TYPE_PRIVATE);
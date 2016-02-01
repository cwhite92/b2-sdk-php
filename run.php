<?php

require 'vendor/autoload.php';

use ChrisWhite\B2\Client;

$client = new Client('cb561ac2f6eb', '001e1f97414024ec65699a5bc2e770edd203a48a2c');

$fileContent = $client->downloadById('4_z4c2b957661da9c825f260e1b_f119f1fae240dae93_d20160131_m162947_c001_v0001015_t0020');

die(var_dump($fileContent));
<?php

require 'vendor/autoload.php';

use Agentk\TagProcessor\PinboardClient;

Dotenv::load(__DIR__);
Dotenv::required([
    'PINBOARD_USERNAME',
    'PINBOARD_TOKEN',
]);

$cache_filename = getenv('CACHE_FILENAME') ?: 'status.cache';

$pinboard = new PinboardClient([
    'username' => getenv('PINBOARD_USERNAME'),
    'token' => getenv('PINBOARD_TOKEN'),
]);

$pinboard->unwantedTags = ['ifttt', 'instapaper'];
$pinboard->logToConsole = true;
$pinboard->default_unread = false;
$pinboard->default_public = true;

$pinboard->scrubTag('IFTTT');
$pinboard->scrubTag('ifttt');

// Save skipped bookmarks once tag scrubbing is complete
$pinboard->onUpdateStatus(function($status) use ($cache_filename) {
    file_put_contents($cache_filename, serialize($status));
});
if (file_exists($cache_filename)) {
    $pinboard->setStatus(unserialize(file_get_contents($cache_filename)));
}

$pinboard->updateUntagged();

$pinboard->renameTagByRegex('/^#(.+)/', '$1');
$pinboard->renameTagByRegex('/^@(.+)/', '$1');
$pinboard->deleteTagsByRegex('/^(from|via):.+/');
$pinboard->renameTagByRegex('/^(.*):(.+)/', '$2');


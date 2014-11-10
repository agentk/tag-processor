<?php
namespace Agentk\TagProcessor;

use PinboardAPI;

class PinboardClient
{

    public $logToConsole = false;
    public $default_unread = true;
    public $default_public = false;
    public $unwanted_tags = [];

    protected $api;
    protected $status = [];
    protected $statusCallback;


    public function __construct($credentials)
    {
        $this->api = new PinboardAPI($credentials['username'], $credentials['token']);
        $this->log = [];
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function onUpdateStatus($callback)
    {
        $this->statusCallback = $callback;
    }

    public function getUnwantedTags()
    {
        return $this->unwanted_tags;
    }


    public function scrubTag($tag)
    {
        $this->log("Scrubbing tag: '$tag'");
        $bookmarks = $this->getByTag([$tag]);
        $this->retagBookmarks($bookmarks);
    }


    public function updateUntagged()
    {
        $this->log("Tagging untagged bookmarks");
        $bookmarks = $this->getUntagged();
        $this->retagBookmarks($bookmarks);
    }


    public function retagBookmarks($bookmarks)
    {
        $count = count($bookmarks);
        $index = 0;
        $this->log(" - Bookmark count: $count");

        foreach($bookmarks as $bookmark) {
            $index++;
            if ($this->isBookmarkSkipped($bookmark)) {
                $this->log(" - Skipping bookmark [$index of $count]: $bookmark->url");
                continue;
            }
            $this->log(" - Updating bookmark [$index of $count]: $bookmark->url");
            $this->retagBookmark($bookmark);

            if (count($bookmark->tags) == 0) {
                $this->skipBookmarkInFuture($bookmark);
            }
        }
    }

    public function retagBookmark($bookmark)
    {
        $this->log("   - Old tags: " . implode(', ', $bookmark->tags));

        $this->initTags($bookmark);
        $this->log("   - New tags: " . implode(', ', $bookmark->tags));

        $bookmark->is_unread = $this->default_unread;
        $bookmark->is_public = $this->default_public;
        $bookmark->save();
    }

    public function initTags($bookmark)
    {
        $suggestion = $this->getSuggestedTags($bookmark);
        $tags = array_merge($suggestion->popular, $suggestion->recommended);
        $lowercase_tags = array_map('strtolower', $tags);
        $removed_unwanted_tags = array_diff($lowercase_tags, ['ifttt', 'instapaper']);
        $bookmark->tags = array_unique($removed_unwanted_tags);
    }

    public function isBookmarkSkipped($bookmark)
    {
        return in_array($bookmark->url, $this->status);
    }

    public function skipBookmarkInFuture($bookmark)
    {
        $this->status[] = $bookmark->url;
        if ($this->statusCallback) {
            $callback = $this->statusCallback;
            $callback($this->status);
        }
    }

    public function getByTag($tags)
    {
        $results = $this->api->search_by_tag($tags);
        return $results;
    }

    public function getSuggestedTags($bookmark)
    {
        return $this->api->get_suggested_tags($bookmark);
    }

    public function getUntagged()
    {
        $all = $this->api->get_all();
        $results = [];
        foreach ($all as $bookmark) {
            if (count($bookmark->tags) == 0 || $bookmark->tags[0] == '') {
                $bookmark->tags = [];
                $results[] = $bookmark;
            }
        }
        return $results;
    }

    public function getAll()
    {
        $all = $this->api->get_all();
        $results = [];
        foreach ($all as $bookmark) {
            if (count($bookmark->tags) == 0 || $bookmark->tags[0] == '') {
                $bookmark->tags = [];
            }
            $results[] = $bookmark;
        }
        return $results;
    }

    public function getTags()
    {
        return $this->api->get_tags();
    }

    public function renameTag($old, $new)
    {
        return $this->api->rename_tag($old, $new);
    }

    public function log($message)
    {
        if ($this->logToConsole) {
            echo $message . PHP_EOL;
        }
        $this->log[] = $message;
    }

    public function renameTagByRegex($regex, $replacement)
    {
        $this->log("Cleaning tags by regex: '$regex' -> '$replacement'");

        $tags = $this->getTags();
        $this->log(" - Total tags: " . count($tags));

        foreach($tags as $tag) {
            $modified_tag = preg_replace($regex, $replacement, $tag->tag);
            if ($modified_tag != $tag->tag) {
                $this->log(" - Renaming tag: $tag->tag -> $modified_tag");
                $this->renameTag($tag->tag, $modified_tag);
            }
        }
    }

    public function deleteTagsByRegex($regex)
    {
        $this->log("Deleting tags by regex: '$regex'");

        $tags = $this->getTags();
        $this->log(" - Total tags: " . count($tags));

        foreach($tags as $tag) {
            if (preg_match($regex, $tag->tag)) {
                $this->log(" - Deleting tag: $tag->tag");
                $this->api->delete_tag($tag->tag);
            }
        }
    }


}

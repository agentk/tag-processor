# tag-processor

## Pinboard Tag Processing

Tag Processor is just a utility layer on top of the Pinboard API Client by (Kijin Sung) <https://github.com/kijin/pinboard-api>

An example usage is documented in `example.php`

Typical usage would entail:

 - Create a new instance of the PinboardClient with your credentials (Stored in .env in the example application).
 - Configure the client with unwanted tags, logging and caching options.
 - Process bookmarks and tags by scrubbing, updating renaming and deleting.

 
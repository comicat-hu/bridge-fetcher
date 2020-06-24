# bridge-fetcher

fetch rss-bridge json data and send to slack

## note

PHP 7.0+

Run `composer install` before you run scripts.

Make sure the folder where logs and scripts are stored has write permission.

Scripts will create a file called `recentFetchTime.tmp` to check if the messages has been processed.

## refs

* https://github.com/RSS-Bridge/rss-bridge
(make sure install release >= 2020-02-26, and enable 'direct_links=on')

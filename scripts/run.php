<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . './utils.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Dotenv\Dotenv;
use JoliCode\Slack\ClientFactory;

const RECENT_FETCH_TIME_TMP = __DIR__ . '/recentFetchTime.tmp';

$dotenv = Dotenv::create(__DIR__)->load();

function putLog(string $log='')
{
    $sysTime = date('Y-m-d H:i:s');
    $logDir = getEnv('LOG_DIR');
    $filename = date('Ymd') . '.log';
    
    if (!is_dir($logDir)) {
      mkdir($logDir);
    }
    
    file_put_contents(
        "$logDir$filename",
        "[$sysTime] $log\n",
        FILE_APPEND
    );	
}

function postMessage($item)
{
    $client = ClientFactory::create(getEnv('SLACK_TOKEN'));
    $response = $client->chatPostMessage([
        'username' => getEnv('SLACK_USERNAME'),
        'channel' => getEnv('SLACK_CHANNEL_ID'),
        'text' => $item->url, // 有blocks時，text會轉成只出現在通知文字
        'unfurl_links' => false,
        'icon_emoji' => getEnv('SLACK_ICON_EMOJI'),
        'blocks' => json_encode(createBlocks($item))

    ]);

    if (!$response->getOk()) {
        putLog('***Failed post messages***');
        putLog(json_encode($response));
    }
}

function createBlocks($item)
{
    $blocks = [];
    $blocks[] = [
        'type' => 'section',
        'text' => [
            'type' => 'mrkdwn',
            'text' => "<{$item->url}>"
        ]
    ];

    foreach ($item->attachments as $attachment) {
        if ($attachment->mime_type == 'application/octet-stream') {
            $attachment->url = getLastUrl($attachment->url);
        }

        $blocks[] = [
            'type' => 'image',
            'title' => [
                'type' => 'plain_text',
                'text' => $item->title,
                "emoji" => true
            ],
            'image_url' => $attachment->url,
            'alt_text' => $item->url
        ];
    }

    // line
    $blocks[] = [
        'type' => 'divider'
    ];

    return $blocks;
}

function setRecentFetchTime() {
    file_put_contents(RECENT_FETCH_TIME_TMP, time());
    putlog('set recent fetch time complete.');
}

function getRecentFetchTime() {
    if (file_exists(RECENT_FETCH_TIME_TMP)) {
        return file_get_contents(RECENT_FETCH_TIME_TMP);
    } else {
        return time();
    }
}

try {
    putLog("== Script begin ==");

    $followList = explode(',', getEnv('FOLLOW_LIST'));

    $mainUrl = getEnv('BRIDGE_MAIN_URL');
    $maxRecentPosts = getEnv('MAX_RECENT_POSTS');

    $messageCount = 0;
    foreach ($followList as $username) {
        $target = $mainUrl . $username;
        $content = json_decode(file_get_contents($target));
        $postsCount = 0;
        if (!empty($content->items)) {
            foreach ($content->items as $item) {
                if ($postsCount < $maxRecentPosts && strtotime($item->date_modified) > getRecentFetchTime()) {
                    if (!empty($item)) {
                        putLog($item->url . " ($username)");
                        postMessage($item);
                        $postsCount++;
                        $messageCount++;
                    } else {
                        putLog("***item null from '{$content->feed_url}'***");
                        putLog(json_encode($item));
                    }

                    sleep(2);
                }
            }
        } else {
            putLog("***empty content from '{$target}'***");
        }
    }
    putLog("send $messageCount messages totally.");
} catch (Exception $ex) {
    putLog('***Script failed with exception***');
    putLog($ex->getMessage());
} catch (Error $err) {
    putLog('***Script failed with error***');
    putLog($err->getMessage());
} finally {
    setRecentFetchTime();
    putLog("== Script end ==");
}

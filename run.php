<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/utils.php';

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
    $messageContent = [
        'username' => getEnv('SLACK_USERNAME'),
        'channel' => getEnv('SLACK_CHANNEL_ID'),
        'text' => $item->url, // 有blocks時，text會轉成只出現在通知文字
        'unfurl_links' => true,
        'icon_emoji' => getEnv('SLACK_ICON_EMOJI'),
        'blocks' => json_encode(createBlocks($item))
    ];

    $response = $client->chatPostMessage($messageContent);

    if (!$response->getOk()) {
        putLog('***Failed post messages***');
        putLog(json_encode($response));
        putLog('message content: ' . print_r($messageContent, TRUE));
    }

    return $response->getOk();
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
        // 此格式多為影片，slack block暫不支援
        if ($attachment->mime_type == 'application/octet-stream') {
            // $attachment->url = getLastUrl($attachment->url);
            continue;
        }

        $blocks[] = [
            'type' => 'image',
            'title' => [
                'type' => 'plain_text',
                'text' => "@{$item->author->name}",
                "emoji" => true
            ],
            'image_url' => $attachment->url,
            'alt_text' => "{$item->url} ({$item->author->name})"
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
    $latestMode = filter_var(getEnv('LATEST_MODE'), FILTER_VALIDATE_BOOLEAN);

    $messageCount = 0;
    foreach ($followList as $username) {
        $target = $mainUrl . $username;
        $content = json_decode(file_get_contents($target));
        $postsCount = 0;
        if (empty($content->items)) {
            putLog("***empty content from '{$target}'***");
        } else {
            foreach ($content->items as $item) {
                $checkedLatest = $latestMode ? true : strtotime($item->date_modified) > getRecentFetchTime();
                if ($postsCount < $maxRecentPosts && $checkedLatest) {
                    if (empty($item)) {
                        putLog("***item null from '{$content->feed_url}'***");
                        putLog(json_encode($item));
                    } else {
                        $postsCount++;
                        $author = $item->author->name ?? $username;
                        putLog($item->url . " ({$author})");
                        if (postMessage($item)) {
                            $messageCount++;
                        }
                    }

                    sleep(2);
                }
            }
        }
    }
    putLog("success send $messageCount messages totally.");
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

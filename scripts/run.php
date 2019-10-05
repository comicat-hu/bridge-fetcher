<?php

require __DIR__ . '/../vendor/autoload.php';

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

function postMessage(string $content)
{
    $client = ClientFactory::create(getEnv('SLACK_TOKEN'));
    $response = $client->chatPostMessage([
        'username' => getEnv('SLACK_USERNAME'),
        'channel' => getEnv('SLACK_CHANNEL_ID'),
        'text' => $content,
        'unfurl_links' => true,
        'icon_emoji' => getEnv('SLACK_ICON_EMOJI')

    ]);

    if (!$response->getOk()) {
        putLog('***Failed post messages***');
        putLog(json_encode($response));
    }
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
        foreach ($content->items as $item) {
            if ($postsCount < $maxRecentPosts && strtotime($item->date_modified) > getRecentFetchTime()) {
                $msg = $item->url;
                if (!is_null($msg)) {
                    putLog($item->url . " ($username)");
                    postMessage($item->url);
                    $postsCount++;
                    $messageCount++;
                } else {
                    putLog('***message null***');
                    putLog(json_encode($item));
                }

                sleep(2);
            }
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

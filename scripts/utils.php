<?php

function getRedirectedUrl($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE); // We'll parse redirect url from header.
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); // We want to just get redirect url but not to follow it.
    
    $response = curl_exec($ch);
    preg_match_all('/^Location:(.*)$/mi', $response, $matches);
    curl_close($ch);
    return !empty($matches[1]) ? trim($matches[1][0]) : false;
}

function getLastUrl($url)
{
    $lastUrl = '';
    while ($url) {
        $url = getRedirectedUrl($url);
        if ($url !== false) {
            $lastUrl = $url;
        }
    }
    return $lastUrl;
}

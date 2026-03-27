<?php

function check($url, $key)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $key . ':');
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$key = 'sk_test_REPLACE_ME';
echo "Wallets v2: " . check('https://api.paymongo.com/v2/wallets', $key) . "\n";
echo "Wallets v1: " . check('https://api.paymongo.com/v1/wallets', $key) . "\n";

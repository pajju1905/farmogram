<?php
// config/push_engine.php

function sendMobilePush($target_user_id, $title, $message) {
    // 1. PASTE YOUR KEYS HERE
    $app_id = getenv('ONESIGNAL_APP_ID') ?: ''; 
    $rest_api_key = getenv('ONESIGNAL_REST_API_KEY') ?: '';

    $content = array("en" => $message);
    $headings = array("en" => $title);

    // Target the specific user ID
    $fields = array(
        'app_id' => $app_id,
        'include_aliases' => array(
            'external_id' => array( strval($target_user_id) )
        ),
        'target_channel' => 'push',
        'contents' => $content,
        'headings' => $headings,
        'url' => 'http://localhost/farmogram/farmer/dashboard.php' // Change if your folder name is different
    );

    $fields_json = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.onesignal.com/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $rest_api_key
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
?>
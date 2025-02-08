<?php
function answerCallbackQuery($callback_query_id, $text = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $data = [
        'callback_query_id' => $callback_query_id,
        'text' => $text
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['ok']) && $responseData['ok'] === true) {
        return true; // Return true to indicate success
    } else {
        logMessage("Error in answerCallbackQuery: API response was not successful. Response: " . $response);
        return false; // Return false to indicate failure
    }
}
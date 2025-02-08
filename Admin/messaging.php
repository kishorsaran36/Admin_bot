<?php

function sendMessage($chatId, $message) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        logMessage("Curl error: " . curl_error($ch));
    } else {
        logMessage("Telegram API response: " . $response);
    }
    curl_close($ch);

    logMessage("Sent message to $chatId: $message");
}

function sendMessageWithInlineKeyboard($chatId, $message, $buttons) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        logMessage("Curl error: " . curl_error($ch));
    } else {
        logMessage("Telegram API response: " . $response);
    }
    curl_close($ch);

    logMessage("Sent message to $chatId: $message");
}

function sendMessageWithButtons($chatId, $message, $buttons) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['keyboard' => $buttons, 'resize_keyboard' => true, 'one_time_keyboard' => true])
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        logMessage("Curl error: " . curl_error($ch));
    } else {
        logMessage("Telegram API response: " . $response);
    }
    curl_close($ch);

    logMessage("Sent message to $chatId: $message");
}

?>
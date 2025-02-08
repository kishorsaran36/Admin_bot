<?php

require_once 'messaging.php';

// Function to handle code modification selection
function handleModifyCode($chatId, $code) {
    global $pdo;

    // Check if session is initialized for this user
    if (!isset($GLOBALS['sessionData'][$chatId])) {
        $GLOBALS['sessionData'][$chatId] = [];
    }

    try {
        // Check if code exists
        $stmt = $pdo->prepare("SELECT * FROM video_codes WHERE code = ?");
        $stmt->execute([$code]);
        $codeData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeData) {
            sendMessage($chatId, "❌ Code not found.");
            return;
        }

        // Message with modification options
        $message = "🔹 *Modify Code: `{$code}`* 🔹\n\n";
        $message .= "Select an option to modify:";

        // Buttons for modification
        $buttons = [
            [["text" => "✏ Modify Redeem Code", "callback_data" => "edit_redeem_$code"]],
            [["text" => "💰 Modify T1 Amount", "callback_data" => "edit_t1_amt_$code"]],
            [["text" => "⏳ Modify T1 Expiry", "callback_data" => "edit_t1_exp_$code"]],
            [["text" => "💵 Modify T2 Amount", "callback_data" => "edit_t2_amt_$code"]],
            [["text" => "⏳ Modify T2 Expiry", "callback_data" => "edit_t2_exp_$code"]],
            [["text" => "⬅ Back", "callback_data" => "activeCodes_1"]]
        ];

        // Send message with inline keyboard
        sendMessageWithInlineKeyboard($chatId, $message, $buttons);

        // Set session data for modification
        $GLOBALS['sessionData'][$chatId]['step'] = 'modify_code';
        $GLOBALS['sessionData'][$chatId]['modify_code'] = $code;

    } catch (PDOException $e) {
        sendMessage($chatId, "❌ Error fetching code details.");
        logMessage("Error fetching code details: " . $e->getMessage());
    }
}
?>
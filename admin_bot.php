<?php
require_once '../admin_db.php';
require_once 'admin_config.php'; // Include admin configuration
require_once 'handlers/logging.php';
require_once 'handlers/messaging.php';
require_once 'handlers/session.php';
require_once 'handlers/admin.php';
require_once 'handlers/video_code.php';
require_once 'handlers/withdrawal.php';
require_once 'handlers/active_codes.php'; // Include the new handler
require_once 'handlers/modify_code.php'; // Include the modify handler
require_once 'handlers/update_code.php'; // Include the update handler
require_once 'handlers/answer_callback.php'; // Include the answer callback handler

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Main function to handle incoming Telegram updates
function handleTelegramWebhook() {
    $update = json_decode(file_get_contents('php://input'), true);
    logMessage("Received update: " . json_encode($update));

    if (!isset($update['message']) && !isset($update['callback_query'])) {
        logMessage("No message or callback query found in the update.");
        return;
    }

    $chatId = null;
    $text = null;
    $callbackData = null;

    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'];
        $username = $message['chat']['username'] ?? 'Unknown';
        logMessage("Received message from $chatId: $text");
    } elseif (isset($update['callback_query'])) {
        $callbackQuery = $update['callback_query'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $callbackData = $callbackQuery['data'];
        logMessage("Received callback query from $chatId: $callbackData");
    }

    // Load session data
    $sessionData = loadSessionData();
    logMessage("Successfully loaded session data.");

    if (!isset($sessionData[$chatId])) {
        $sessionData[$chatId] = [];
    }

    // Define the keyboard layout
    $keyboard = [
        ['Set Video Code', 'Pending Withdrawal Requests', 'Active Codes'],
        ['Cancel']
    ];

    // Command handling
    if ($text === "/start") {
        if (isAdmin($chatId)) {
            sendMessageWithButtons($chatId, "Welcome to the admin bot! Please choose an option:", $keyboard);
            logMessage("Admin $chatId started the bot.");
        } else {
            sendMessage($chatId, "🚫 The bot is currently not working.");
            logMessage("Unauthorized user $chatId tried to start the bot.");
        }
        $sessionData[$chatId]['step'] = null;
    } elseif (strpos($text, "/setadmin") === 0) {
        $parts = explode(' ', $text);
        if (isset($parts[1]) && $parts[1] === ADMIN_ACCESS_CODE) {
            registerAdmin($chatId);
            sendMessageWithButtons($chatId, "✅ You are now registered as an admin! Please choose an option:", $keyboard);
            logMessage("User $chatId registered as admin.");
        } else {
            sendMessage($chatId, "❌ Invalid admin access code.");
            logMessage("User $chatId provided an invalid admin access code.");
        }
        $sessionData[$chatId]['step'] = null;
    } else {
        if (!isAdmin($chatId)) {
            sendMessage($chatId, "🚫 The bot is currently not working.");
            logMessage("Unauthorized user $chatId tried to interact with the bot.");
            return;
        }

        switch ($sessionData[$chatId]['step']) {
            case 'set_video_code_bulk_count':
            case 'set_video_code_t1_price':
            case 'set_video_code_t1_expiry_bulk':
                handleSetVideoCodeSteps($chatId, $text, $sessionData);
                break;
            case 'awaiting_redeem_code':
            case 'awaiting_t1_amount':
            case 'awaiting_t1_expiry':
            case 'awaiting_t2_amount':
            case 'awaiting_t2_expiry':
                list($code, $field) = explode('|', $sessionData[$chatId]['modify_data']);
                logMessage("Updating code: $code, field: $field with value: $text");

                // --- NEW: 4-digit time format processing for expiry ---
                if (in_array($field, ['t1_expiry', 't2_expiry'])) {
                    $expiry_time_4digit = str_replace(' ', '', $text);
                    $currentDate = date('Y-m-d');
                    $expiry_datetime = DateTime::createFromFormat('Y-m-d Hi', "$currentDate $expiry_time_4digit");

                    // Adjust for past times
                    if ($expiry_datetime < new DateTime()) {
                        $expiry_datetime->modify('+1 day');
                    }

                    if (!$expiry_datetime) {
                        sendMessage($chatId, "❌ Invalid expiry time format. Please use 4-digit 24-hour format (e.g., 1813 for 18:13).");
                        logMessage("Error: Invalid expiry time format provided: $text");
                        return;
                    }

                    $formatted_expiry_datetime = $expiry_datetime->format('Y-m-d H:i:s');
                    handleUpdateCode($chatId, $code, $field, $formatted_expiry_datetime);
                } else {
                    // For other fields (redeem, t1_amount, t2_amount), use text directly
                    handleUpdateCode($chatId, $code, $field, $text);
                }
                // --- END NEW: 4-digit time format processing for expiry ---

                sendMessage($chatId, "✅ Code updated successfully.");
                $sessionData[$chatId]['step'] = null;
                break;
            default:
                if ($callbackData) {
                    logMessage("Handling callback data: $callbackData");
                    if (strpos($callbackData, 'activeCodes_') === 0) {
                        $page = (int)str_replace('activeCodes_', '', $callbackData);
                        handleActiveCodes($chatId, $page);
                    } elseif (strpos($callbackData, 'modify_') === 0) {
                        $code = str_replace('modify_', '', $callbackData);
                        handleModifyCode($chatId, $code);
                        $sessionData[$chatId]['step'] = 'modify_code';
                        $sessionData[$chatId]['modify_code'] = $code;
                    } elseif (strpos($callbackData, 'edit_') === 0) {
                        // --- UPDATED CALLBACK DATA PARSING LOGIC (ROBUST & REDEEM FIX) ---
                        $parts = explode('_', $callbackData);
                        if (count($parts) >= 2 && $parts[0] === 'edit') {
                            $action = $parts[1]; // Default action is the second part
                            $code = end($parts); // Code is still the last part

                            // Reconstruct action for t1_amt, t1_exp, t2_amt, t2_exp (if more than 3 parts)
                            if (count($parts) >= 3 && !in_array($action, ['redeem'])) { // Reconstruct action only if NOT 'redeem' and more than 3 parts
                                $action = $parts[1] . '_' . $parts[2];
                            }

                            $validActions = ['redeem', 't1_amt', 't1_exp', 't2_amt', 't2_exp'];
                            if (!in_array($action, $validActions)) {
                                logMessage("Error: Invalid edit action: " . $action);
                                if (isset($update['callback_query']['id'])) {
                                    answerCallbackQuery($update['callback_query']['id'], "Error: Invalid action.");
                                }
                                return;
                            }

                            $callback_query_id = $update['callback_query']['id'];
                            answerCallbackQuery($callback_query_id, "Processing your request...");
                            logMessage("Handling edit action: $action for code: $code");

                            switch ($action) {
                                case 'redeem':
                                    sendMessage($chatId, "✏️ Enter new redeem code for {$code}:");
                                    $sessionData[$chatId]['step'] = 'awaiting_redeem_code';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|code";
                                    break;
                                case 't1_amt':
                                    sendMessage($chatId, "✏️ Enter new T1 amount for {$code}:\nFormat: number only (e.g., 100)");
                                    $sessionData[$chatId]['step'] = 'awaiting_t1_amount';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|t1_amount";
                                    break;
                                case 't1_exp':
                                    sendMessage($chatId, "✏️ Enter new T1 expiry time (e.g., 1813, 2240, 0930, 1505, 2130):"); // Updated prompt message
                                    $sessionData[$chatId]['step'] = 'awaiting_t1_expiry';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|t1_expiry";
                                    break;
                                case 't2_amt':
                                    sendMessage($chatId, "✏️ Enter new T2 amount for {$code}:\nFormat: number only (e.g., 200)");
                                    $sessionData[$chatId]['step'] = 'awaiting_t2_amount';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|t2_amount";
                                    break;
                                case 't2_exp':
                                    sendMessage($chatId, "✏️ Enter new T2 expiry time (e.g., 1813, 2240, 0930, 1505, 2130):"); // Updated prompt message
                                    $sessionData[$chatId]['step'] = 'awaiting_t2_expiry';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|t2_expiry";
                                    break;
                            }
                        } else {
                            // Handle invalid callbackData format for edit action
                            logMessage("Error: Invalid callbackData format for edit action: " . $callbackData);
                            if (isset($update['callback_query']['id'])) {
                                $callback_query_id = $update['callback_query']['id'];
                                answerCallbackQuery($callback_query_id, "Error: Invalid request format.");
                            }
                        }
                        // --- END UPDATED CALLBACK DATA PARSING LOGIC ---
                    }
                    logMessage("Updated session step to: " . $sessionData[$chatId]['step']);
                } else {
                    switch ($text) {
                        case 'Set Video Code':
                            sendMessage($chatId, "Please enter the number of codes to generate:");
                            logMessage("Admin $chatId selected Set Video Code.");
                            $sessionData[$chatId]['step'] = 'set_video_code_bulk_count';
                            break;
                        case 'Pending Withdrawal Requests':
                            handlePendingWithdrawalRequests($chatId);
                            break;
                        case 'Active Codes':
                            handleActiveCodes($chatId);
                            break;
                        case '⬅️ Previous':
                            handleActiveCodes($chatId, max(1, $sessionData[$chatId]['page'] - 1));
                            $sessionData[$chatId]['page'] = max(1, $sessionData[$chatId]['page'] - 1);
                            break;
                        case 'Next ➡️':
                            handleActiveCodes($chatId, $sessionData[$chatId]['page'] + 1);
                            $sessionData[$chatId]['page'] = $sessionData[$chatId]['page'] + 1;
                            break;
                        case 'Cancel':
                            sendMessageWithButtons($chatId, "Operation cancelled. Please choose an option:", $keyboard);
                            logMessage("Admin $chatId cancelled the operation.");
                            $sessionData[$chatId]['step'] = null;
                            break;
                        default:
                            sendMessageWithButtons($chatId, "❓ Please use the buttons to interact with the bot.", $keyboard);
                            logMessage("Admin $chatId sent an unknown command: $text");
                            break;
                    }
                }
                break;
        }
    }

    // Save session data
    saveSessionData($sessionData);
    logMessage("Successfully saved session data.");
}

handleTelegramWebhook();
?>
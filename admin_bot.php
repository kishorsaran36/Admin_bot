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
require_once 'handlers/answer_callback.php';
require_once 'handlers/post_code_handler.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// मेन फंक्शन इनकमिंग टेलीग्राम अपडेट्स को हैंडल करने के लिए
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

    // सेशन डेटा लोड करें
    $sessionData = loadSessionData();
    logMessage("Successfully loaded session data.");

    if (!isset($sessionData[$chatId])) {
        $sessionData[$chatId] = [];
    }

    // कीबोर्ड लेआउट डिफाइन करें
    $keyboard = [
        ['Set Video Code', 'Pending Withdrawal Requests', 'Active Codes'],
        ['Post Video Code', 'Pending Scheduled Posts', 'Cancel'] // "Pending Scheduled Posts" बटन जोड़ा
    ];

    // कमांड हैंडलिंग
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
    } elseif (strpos($text, "/cancel_scheduled_") === 0) { // Cancel scheduled post command
        $postId = str_replace('/cancel_scheduled_', '', $text);
        handleCancelScheduledPostCallback($chatId, $postId);
        return; // Exit to prevent further processing of this message as a step
    }
     else {
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
            case 'awaiting_video_duration': // **नया केस: वीडियो ड्यूरेशन का इंतजार**
                $videoDuration = intval($text); // ड्यूरेशन को integer में कन्वर्ट करें
                if ($videoDuration <= 0) {
                    sendMessage($chatId, "❌ Invalid video duration. Please enter a valid number of minutes (e.g., 5).");
                    $sessionData[$chatId]['step'] = 'awaiting_video_duration'; // उसी स्टेप पर रहें
                } else {
                    $sessionData[$chatId]['video_duration'] = $videoDuration; // वीडियो ड्यूरेशन सेशन डेटा में स्टोर करें
                    sendMessage($chatId, "✏️ Please enter the schedule time in 4-digit 24-hour format (e.g., 1813 for 18:13):"); // शेड्यूलिंग टाइम के लिए पूछें
                    $sessionData[$chatId]['step'] = 'awaiting_schedule_time'; // अगले स्टेप पर जाएँ
                }
                break;
            case 'awaiting_schedule_time': // **नया केस: शेड्यूलिंग टाइम का इंतजार**
                $scheduleTime4Digit = str_replace(' ', '', $text);
                $sessionData[$chatId]['schedule_time_4digit'] = $scheduleTime4Digit; // शेड्यूलिंग टाइम सेशन डेटा में स्टोर करें
                sendMessage($chatId, "✏️ Please enter the video code to post:"); // वीडियो कोड के लिए पूछें
                $sessionData[$chatId]['step'] = 'awaiting_post_video_code'; // अगले स्टेप पर जाएँ
                break;
            case 'awaiting_post_video_code': // पोस्ट वीडियो कोड के लिए नया केस
                $videoCode = $text;
                $videoDuration = $sessionData[$chatId]['video_duration']; // सेशन डेटा से वीडियो ड्यूरेशन लें
                $scheduleTime4Digit = $sessionData[$chatId]['schedule_time_4digit']; // सेशन डेटा से शेड्यूलिंग टाइम लें
                logMessage("Scheduling video code: $videoCode for posting with duration: $videoDuration and schedule time: $scheduleTime4Digit");
                scheduleVideoPostToChannel($chatId, $videoCode, $videoDuration, $scheduleTime4Digit); // शेड्यूल पोस्टिंग फंक्शन कॉल करें, वीडियो ड्यूरेशन और शेड्यूलिंग टाइम पास करें
                sendMessageWithButtons($chatId, "Operation completed. Please choose an option:", $keyboard); // मेन मेनू पर वापस जाएँ
                $sessionData[$chatId]['step'] = null; // स्टेप रीसेट करें
                break;
            case 'awaiting_redeem_code':
            case 'awaiting_t1_amount':
            case 'awaiting_t1_expiry':
            case 'awaiting_t2_amount':
            case 'awaiting_t2_expiry':
                list($code, $field) = explode('|', $sessionData[$chatId]['modify_data']);
                logMessage("Updating code: $code, field: $field with value: $text");

                // --- नया: 4-डिजिट टाइम फॉर्मेट प्रोसेसिंग एक्सपायरी के लिए ---
                if (in_array($field, ['t1_expiry', 't2_expiry'])) {
                    $expiry_time_4digit = str_replace(' ', '', $text);
                    $currentDate = date('Y-m-d');
                    $expiry_datetime = DateTime::createFromFormat('Y-m-d Hi', "$currentDate $expiry_time_4digit");

                    // पास्ट टाइम्स के लिए एडजस्ट करें
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
                    // दूसरे फील्ड्स (redeem, t1_amount, t2_amount) के लिए, टेक्स्ट को सीधे इस्तेमाल करें
                    handleUpdateCode($chatId, $code, $field, $text);
                }
                // --- एंड नया: 4-डिजिट टाइम फॉर्मेट प्रोसेसिंग एक्सपायरी के लिए ---

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
                        // --- UPDATED CALLBACK DATA पार्सिंग लॉजिक (रोबस्ट & रिडीम फिक्स) ---
                        $parts = explode('_', $callbackData);
                        if (count($parts) >= 2 && $parts[0] === 'edit') {
                            $action = $parts[1]; // डिफ़ॉल्ट एक्शन दूसरा पार्ट है
                            $code = end($parts); // कोड अभी भी लास्ट पार्ट है

                            // t1_amt, t1_exp, t2_amt, t2_exp के लिए एक्शन रिकंस्ट्रक्ट करें (अगर 3 से ज़्यादा पार्ट्स हैं)
                            if (count($parts) >= 3 && !in_array($action, ['redeem'])) { // एक्शन रिकंस्ट्रक्ट करें सिर्फ अगर 'redeem' नहीं है और 3 से ज़्यादा पार्ट्स हैं
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
                                    sendMessage($chatId, "✏️ Enter new T1 expiry time (e.g., 1813, 2240, 0930, 1505, 2130):"); // अपडेटेड प्रॉम्प्ट मैसेज
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
                                    sendMessage($chatId, "✏️ Enter new T2 expiry time (e.g., 1813, 2240, 0930, 1505, 2130):"); // अपडेटेड प्रॉम्प्ट मैसेज
                                    $sessionData[$chatId]['step'] = 'awaiting_t2_expiry';
                                    $sessionData[$chatId]['modify_code'] = $code;
                                    $sessionData[$chatId]['modify_data'] = "$code|t2_expiry";
                                    break;
                            }
                        } else {
                            // एडिट एक्शन के लिए इनवैलिड callbackData फॉर्मेट हैंडल करें
                            logMessage("Error: Invalid callbackData format for edit action: " . $callbackData);
                            if (isset($update['callback_query']['id'])) {
                                $callback_query_id = $update['callback_query']['id'];
                                answerCallbackQuery($callback_query_id, "Error: Invalid request format.");
                            }
                        }
                        // --- एंड UPDATED CALLBACK DATA पार्सिंग लॉजिक ---
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
                        case 'Post Video Code': // पोस्ट वीडियो कोड के लिए नया केस
                            sendMessage($chatId, "✏️ Please enter video duration in minutes (e.g., 5):"); // वीडियो ड्यूरेशन के लिए पूछें
                            logMessage("Admin $chatId selected Post Video Code.");
                            $sessionData[$chatId]['step'] = 'awaiting_video_duration'; // नया स्टेप सेट करें
                            break;
                        case 'Pending Scheduled Posts': // "Pending Scheduled Posts" बटन के लिए नया केस
                            handlePendingScheduledPosts($chatId); // फंक्शन कॉल करें
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

    // सेशन डेटा सेव करें
    saveSessionData($sessionData);
    logMessage("Successfully saved session data.");
}

function handlePendingScheduledPosts($chatId, $page = 1) { // फंक्शन को पेज नंबर पैरामीटर लें
    $postsPerPage = 5; // हर पेज पर पोस्ट्स की संख्या
    $pendingPosts = fetchPendingScheduledPosts($page, $postsPerPage); // पेजिनेटेड पोस्ट्स fetch करें
    $totalPostsCount = getTotalPendingPostsCount(); // कुल पोस्ट्स काउंट fetch करें
    $totalPages = ceil($totalPostsCount / $postsPerPage); // कुल पेज संख्या कैलकुलेट करें

    if (empty($pendingPosts)) {
        sendMessage($chatId, "✅ There are no pending scheduled posts.");
        return;
    }

    $message = "⏳ *Pending Scheduled Posts (Page {$page}/{$totalPages}):*\n\n"; // पेज नंबरिंग जोड़ें
    $buttons = []; // इनलाइन बटन्स के लिए ऐरे

    foreach ($pendingPosts as $post) {
        $scheduledTime = date('d M Y H:i', strtotime($post['scheduled_datetime']));
        $message .= "Code: `{$post['video_code']}`\n";
        $message .= "Duration: {$post['video_duration']} minutes\n";
        $message .= "Schedule: {$scheduledTime}\n";
        // $message .= "/cancel_scheduled_{$post['id']}\n\n"; // कमांड लाइन की जगह हटाएँ
        $buttons[] = [ // Cancel Schedule बटन जोड़ें
            ['text' => "❌ Cancel Schedule", 'callback_data' => "cancel_schedule_{$post['id']}"]
        ];
        $message .= "\n"; // पोस्ट्स के बीच गैप
    }
    // $message .= "\nTo cancel a scheduled post, use the command above its details (e.g., /cancelscheduled123)"; // कमांड इंस्ट्रक्शन हटाएँ

    // पेजिनेशन बटन्स जोड़ें अगर मल्टीपल पेज हैं
    if ($totalPages > 1) {
        $paginationRow = [];
        if ($page > 1) {
            $paginationRow[] = ['text' => "⬅️ Previous", 'callback_data' => 'pendingSchedule_prev'];
        }
        if ($page < $totalPages) {
            $paginationRow[] = ['text' => "Next ➡️", 'callback_data' => 'pendingSchedule_next'];
        }
        if (!empty($paginationRow)) {
            $buttons[] = $paginationRow;
        }
    }

    $buttons[] = [['text' => "❌ Cancel", 'callback_data' => 'cancel']]; // ग्लोबल Cancel बटन जोड़ें

    sendMessageWithInlineKeyboard($chatId, $message, $buttons);
}


function handleCancelScheduledPostCallback($chatId, $postId) { // नया फंक्शन Cancel Schedule कॉलबैक हैंडल करने के लिए
    if (cancelScheduledPost($postId)) { // पोस्ट कैंसिल करें
        sendMessage($chatId, "✅ Scheduled post ID {$postId} cancelled successfully.");
        logMessage("Scheduled post ID {$postId} cancelled by admin $chatId.");
    } else {
        sendMessage($chatId, "❌ Failed to cancel scheduled post ID {$postId}. Please check logs.");
        logMessage("Error cancelling scheduled post ID {$postId} by admin $chatId.");
    }
    handlePendingScheduledPosts($chatId, $_SESSION[$chatId]['pending_schedule_page'] ?? 1); // पेंडिंग पोस्ट्स लिस्ट रिफ्रेश करें
}


handleTelegramWebhook();
?>

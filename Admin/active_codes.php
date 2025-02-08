<?php

require_once 'messaging.php';

// Function to handle active codes with pagination
function handleActiveCodes($chatId, $page = 1) {
    global $pdo;

    $codesPerPage = 5;
    $offset = ($page - 1) * $codesPerPage;

    try {
        // Fetch active codes with pagination
        $stmt = $pdo->prepare("SELECT * FROM video_codes WHERE status = 'active' LIMIT ? OFFSET ?");
        $stmt->execute([$codesPerPage, $offset]);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($codes)) {
            sendMessage($chatId, "✅ No active codes.");
            return;
        }

        $message = "🔹 *Active Video Codes (Page $page)* 🔹\n\n";
        $buttons = [];

        foreach ($codes as $index => $code) {
            $message .= ($index + 1) . "️⃣ *Code:* `{$code['code']}`\n";
            $message .= "*T1 Amount:* ₹{$code['t1_amount']}\n";
            $message .= "*T1 Expiry:* {$code['t1_expiry']}\n";
            $message .= "*T2 Amount:* ₹{$code['t2_amount']}\n";
            $message .= "*T2 Expiry:* {$code['t2_expiry']}\n";
            $message .= "*Status:* {$code['status']}\n\n";

            // Buttons for modification
            $buttons[] = [
                ["text" => "✏ Modify {$code['code']}", "callback_data" => "modify_{$code['code']}"]
            ];
        }

        // Pagination Buttons
        $prevButton = ["text" => "⬅️ Previous", "callback_data" => "activeCodes_" . ($page - 1)];
        $nextButton = ["text" => "Next ➡️", "callback_data" => "activeCodes_" . ($page + 1)];

        if ($page > 1) {
            $buttons[] = [$prevButton];
        }

        // Check if next page exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_codes WHERE status = 'active'");
        $stmt->execute();
        $totalCodes = $stmt->fetchColumn();

        if ($offset + $codesPerPage < $totalCodes) {
            $buttons[] = [$nextButton];
        }

        sendMessageWithInlineKeyboard($chatId, $message, $buttons);
    } catch (PDOException $e) {
        sendMessage($chatId, "❌ Error fetching active codes.");
        logMessage("Error fetching active codes: " . $e->getMessage());
    }
}

?>
<?php

require_once 'messaging.php';

// Function to update the code details
function handleUpdateCode($chatId, $code, $field, $newValue) {
    global $pdo;

    // Validate the input field to avoid SQL injection
    // Updated $validFields array to match database column names
    $validFields = ['code', 't1_amount', 't1_expiry', 't2_amount', 't2_expiry'];

    if (!in_array($field, $validFields)) {
        sendMessage($chatId, "❌ Invalid field provided.");
        return;
    }

    try {
        // Sanitize new value to prevent SQL injection
        $newValue = htmlspecialchars($newValue);

        // Update the code details in the database
        // Now directly using $field variable as $validFields matches database column names
        $stmt = $pdo->prepare("UPDATE video_codes SET $field = :newValue WHERE code = :code");
        $stmt->execute([':newValue' => $newValue, ':code' => $code]);

        sendMessage($chatId, "✅ $field updated to $newValue for code $code.");
    } catch (PDOException $e) {
        sendMessage($chatId, "❌ Error updating code details.");
        logMessage("Error updating code details: " . $e->getMessage());
    }
}
?>
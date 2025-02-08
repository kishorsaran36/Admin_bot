<?php

// Function to handle pending withdrawal requests
function handlePendingWithdrawalRequests($chatId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE status = 'pending'");
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($requests)) {
            sendMessage($chatId, "✅ No pending withdrawal requests.");
            return;
        }

        $message = "Pending Withdrawal Requests:\n\n";
        foreach ($requests as $request) {
            $message .= "Request ID: {$request['id']}\n";
            $message .= "User ID: {$request['user_id']}\n";
            $message .= "Amount: {$request['amount']}\n";
            $message .= "UPI ID: {$request['upi_id']}\n";
            $message .= "Mobile Number: {$request['mobile_number']}\n";
            $message .= "Requested At: {$request['requested_at']}\n\n";
        }

        sendMessage($chatId, $message);
    } catch (PDOException $e) {
        sendMessage($chatId, "❌ Error fetching pending withdrawal requests.");
        logMessage("Error fetching pending withdrawal requests: " . $e->getMessage());
    }
}

?>
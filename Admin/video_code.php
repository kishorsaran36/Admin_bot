<?php

// Function to handle set video code steps
function handleSetVideoCodeSteps($chatId, $text, &$sessionData) {
    global $pdo, $logFile;

    if (!isset($sessionData[$chatId]['step'])) {
        sendMessage($chatId, "❌ Bot is not working.");
        error_log("[$chatId] State step not set\n", 3, $logFile);
        return;
    }

    $step = $sessionData[$chatId]['step'];

    // Log the current step
    error_log("[$chatId] Handling step: $step\n", 3, $logFile);

    switch ($step) {
        case 'set_video_code_bulk_count':
            if (!is_numeric($text) || $text <= 0) {
                sendMessage($chatId, "❌ Valid number of codes bhejo (e.g., 5, 10, 20).");
                return;
            }
            $sessionData[$chatId]['bulk_count'] = min((int)$text, 6); // Limit to 6 codes
            $sessionData[$chatId]['bulk_codes'] = array_fill(0, $sessionData[$chatId]['bulk_count'], []);
            $sessionData[$chatId]['step'] = 'set_video_code_t1_price';
            sendMessage($chatId, "T1 price for all codes bhejo:");
            error_log("[$chatId] Transition to step: set_video_code_t1_price\n", 3, $logFile);
            break;

        case 'set_video_code_t1_price':
            if (!is_numeric($text) || $text <= 0) {
                sendMessage($chatId, "❌ Valid T1 price bhejo (e.g., 50).");
                return;
            }
            foreach ($sessionData[$chatId]['bulk_codes'] as $index => $code) {
                $sessionData[$chatId]['bulk_codes'][$index]['t1_amount'] = (int)$text;
            }
            $sessionData[$chatId]['step'] = 'set_video_code_t1_expiry_bulk';
            sendMessage($chatId, "All T1 expiry times comma-separated bhejo (e.g., 1813,2240,0930,1505,2130):");
            error_log("[$chatId] Transition to step: set_video_code_t1_expiry_bulk\n", 3, $logFile);
            break;

        case 'set_video_code_t1_expiry_bulk':
            $expiry_times = explode(',', str_replace(' ', '', $text));
            if (count($expiry_times) !== count($sessionData[$chatId]['bulk_codes'])) {
                sendMessage($chatId, "❌ Expiry times ka count galat hai. Total " . count($sessionData[$chatId]['bulk_codes']) . " times bhejo.");
                return;
            }

            $currentDate = date('Y-m-d');
            foreach ($sessionData[$chatId]['bulk_codes'] as $index => $code) {
                // Generate the code once here
                $sessionData[$chatId]['bulk_codes'][$index]['code'] = generateRandomCode($pdo);
                $t1_expiry = str_pad($expiry_times[$index], 4, '0', STR_PAD_LEFT);
                $t1_expiry_datetime = DateTime::createFromFormat('Y-m-d Hi', "$currentDate $t1_expiry");

                // Adjust for past times
                if ($t1_expiry_datetime < new DateTime()) {
                    $t1_expiry_datetime->modify('+1 day');
                }

                if (!$t1_expiry_datetime) {
                    sendMessage($chatId, "❌ Invalid expiry time format for code index $index: {$expiry_times[$index]}");
                    error_log("[$chatId] Invalid expiry time format for code index $index: {$expiry_times[$index]}\n", 3, $logFile);
                    return;
                }

                $sessionData[$chatId]['bulk_codes'][$index]['t1_expiry'] = $t1_expiry_datetime->format('Y-m-d H:i:s');
                $sessionData[$chatId]['bulk_codes'][$index]['t2_amount'] = (int)($sessionData[$chatId]['bulk_codes'][$index]['t1_amount'] / 2);
                $t2_expiry_datetime = clone $t1_expiry_datetime;
                $t2_expiry_datetime->add(new DateInterval('P1D'));
                $sessionData[$chatId]['bulk_codes'][$index]['t2_expiry'] = $t2_expiry_datetime->format('Y-m-d H:i:s');
            }

            saveCodesToDatabase($sessionData[$chatId]['bulk_codes'], $pdo);

            // Log the codes that will be displayed to the user
            error_log("[$chatId] Codes generated: " . json_encode($sessionData[$chatId]['bulk_codes']) . "\n", 3, $logFile);

            sendMessage($chatId, generateCodeResponseMessage($sessionData[$chatId]['bulk_codes']));

            // ✅ Reset session after task completion
            unset($sessionData[$chatId]['step']);
            unset($sessionData[$chatId]['bulk_count']);
            unset($sessionData[$chatId]['bulk_codes']);
            error_log("[$chatId] Session reset after task completion.\n", 3, $logFile);
            break;

        default:
            sendMessage($chatId, "Invalid option. Please choose from the menu.");
            error_log("[$chatId] Invalid step: $step\n", 3, $logFile);
            break;
    }

    // Log the updated state
    error_log("[$chatId] Updated state: " . json_encode($sessionData) . "\n", 3, $logFile);
}

// Function to generate a random code
function generateRandomCode($pdo) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }

    // Ensure the generated code is unique
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_codes WHERE code = ?");
    $stmt->execute([$code]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        return generateRandomCode($pdo); // Recursively generate a new code if the code already exists
    }

    return $code;
}

// Function to save codes to the database
function saveCodesToDatabase($codes, $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO video_codes (code, t1_amount, t1_expiry, t2_amount, t2_expiry, status) VALUES (?, ?, ?, ?, ?, 'active')");
        foreach ($codes as $code) {
            $stmt->execute([$code['code'], $code['t1_amount'], $code['t1_expiry'], $code['t2_amount'], $code['t2_expiry']]);
        }
    } catch (PDOException $e) {
        logMessage("Error saving codes to database: " . $e->getMessage());
    }
}

// Function to generate code response message
function generateCodeResponseMessage($codes) {
    $message = "✅ Codes generated successfully:\n\n";
    foreach ($codes as $index => $code) {
        // Use backticks for inline code formatting
        $message .= "Code: `{$code['code']}`\nT1 Amount: {$code['t1_amount']}\nT1 Expiry: {$code['t1_expiry']}\nT2 Amount: {$code['t2_amount']}\nT2 Expiry: {$code['t2_expiry']}\n\n";
    }
    return $message;
}
?>
<?php

// Function to check if a user is an admin
function isAdmin($chatId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT telegram_id FROM admins WHERE telegram_id = ?");
        $stmt->execute([$chatId]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        logMessage("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

// Function to register a new admin
function registerAdmin($chatId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO admins (telegram_id) VALUES (?)");
        $stmt->execute([$chatId]);
        logMessage("Registered new admin: $chatId");
    } catch (PDOException $e) {
        logMessage("Error registering admin: " . $e->getMessage());
    }
}
?>
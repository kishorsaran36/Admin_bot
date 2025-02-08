<?php

require_once 'logging.php';

$sessionFile = 'logs/admin_session.json';

// Function to load session data
function loadSessionData() {
    global $sessionFile;
    
    try {
        if (!file_exists(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0755, true);
        }
        
        if (!file_exists($sessionFile)) {
            logMessage("Session file not found. Creating a new one.");
            file_put_contents($sessionFile, json_encode([]));
            return [];
        }
        
        $data = file_get_contents($sessionFile);
        if ($data === false) {
            logMessage("Error reading session file.");
            return [];
        }
        
        $decodedData = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("Error decoding session data: " . json_last_error_msg());
            return [];
        }
        
        logMessage("Successfully loaded session data.");
        return $decodedData;
    } catch (Exception $e) {
        logMessage("Error in loadSessionData: " . $e->getMessage());
        return [];
    }
}

// Function to save session data
function saveSessionData($sessionData) {
    global $sessionFile;
    
    try {
        if (!is_array($sessionData)) {
            logMessage("Invalid session data type. Expected array.");
            return false;
        }
        
        $encodedData = json_encode($sessionData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("Error encoding session data: " . json_last_error_msg());
            return false;
        }
        
        if (!file_exists(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0755, true);
        }
        
        $result = file_put_contents($sessionFile, $encodedData, LOCK_EX);
        if ($result === false) {
            logMessage("Error writing session data to file.");
            return false;
        }
        
        logMessage("Successfully saved session data.");
        return true;
    } catch (Exception $e) {
        logMessage("Error in saveSessionData: " . $e->getMessage());
        return false;
    }
}

// Function to clear session data for a chat
function clearChatSession($chatId) {
    $sessionData = loadSessionData();
    if (isset($sessionData[$chatId])) {
        unset($sessionData[$chatId]);
        saveSessionData($sessionData);
        logMessage("Cleared session data for chat ID: $chatId");
    }
}

// Function to get specific session value
function getSessionValue($chatId, $key, $default = null) {
    $sessionData = loadSessionData();
    return isset($sessionData[$chatId][$key]) ? $sessionData[$chatId][$key] : $default;
}

// Function to set specific session value
function setSessionValue($chatId, $key, $value) {
    $sessionData = loadSessionData();
    if (!isset($sessionData[$chatId])) {
        $sessionData[$chatId] = [];
    }
    $sessionData[$chatId][$key] = $value;
    return saveSessionData($sessionData);
}

?>
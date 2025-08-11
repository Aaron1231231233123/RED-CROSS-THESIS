<?php
// This is a simple test script to verify the donor registration workflow
// Place this in the project root directory and run it in the browser

// Start a debugging session
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a mock user session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 1; // Admin role

echo "<h1>Red Cross Donor Registration Workflow Test</h1>";
echo "<p>This script tests the new donor registration workflow.</p>";

echo "<h2>Workflow Steps:</h2>";
echo "<ol>";
echo "<li>Donor Form Registration: <a href='src/views/forms/donor-form-modal.php' target='_blank'>Click to test</a></li>";
echo "<li>Medical History Form: <a href='src/views/forms/medical-history-modal.php' target='_blank'>Click to test</a> (requires donor_id in session)</li>";
echo "<li>Declaration Form: <a href='src/views/forms/declaration-form-modal.php' target='_blank'>Click to test</a> (requires donor_id in session)</li>";
echo "<li>Screening Form: <a href='src/views/forms/screening-form.php' target='_blank'>Click to test</a> (requires donor_id, medical_history_id, and declaration_completed in session)</li>";
echo "</ol>";

echo "<h2>Current Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Set Test Session Data:</h2>";
echo "<form method='post' action=''>";
echo "<p><label>donor_id: <input type='number' name='donor_id' value='" . ($_SESSION['donor_id'] ?? '') . "'></label></p>";
echo "<p><label>medical_history_id: <input type='number' name='medical_history_id' value='" . ($_SESSION['medical_history_id'] ?? '') . "'></label></p>";
echo "<p><label>declaration_completed: <input type='checkbox' name='declaration_completed' " . (isset($_SESSION['declaration_completed']) ? 'checked' : '') . "></label></p>";
echo "<p><input type='submit' name='set_session' value='Set Session Variables'></p>";
echo "</form>";

// Handle form submission
if (isset($_POST['set_session'])) {
    if (!empty($_POST['donor_id'])) {
        $_SESSION['donor_id'] = (int)$_POST['donor_id'];
    } else {
        unset($_SESSION['donor_id']);
    }
    
    if (!empty($_POST['medical_history_id'])) {
        $_SESSION['medical_history_id'] = (int)$_POST['medical_history_id'];
    } else {
        unset($_SESSION['medical_history_id']);
    }
    
    if (isset($_POST['declaration_completed'])) {
        $_SESSION['declaration_completed'] = true;
    } else {
        unset($_SESSION['declaration_completed']);
    }
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

echo "<h2>Clear Session:</h2>";
echo "<form method='post' action=''>";
echo "<p><input type='submit' name='clear_session' value='Clear Session Variables'></p>";
echo "</form>";

// Handle clear session
if (isset($_POST['clear_session'])) {
    unset($_SESSION['donor_id']);
    unset($_SESSION['medical_history_id']);
    unset($_SESSION['declaration_completed']);
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?> 
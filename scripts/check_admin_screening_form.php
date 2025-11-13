<?php
declare(strict_types=1);

/**
 * Simple diagnostic script to verify the current admin initial screening form markup.
 * Usage: php scripts/check_admin_screening_form.php
 */

$targetPath = dirname(__DIR__) . '/src/views/forms/admin_donor_initial_screening_form_modal.php';

if (!is_file($targetPath)) {
    fwrite(STDERR, "[FAIL] Target form file not found at {$targetPath}" . PHP_EOL);
    exit(1);
}

$html = file_get_contents($targetPath);
if ($html === false) {
    fwrite(STDERR, "[FAIL] Unable to read target form file." . PHP_EOL);
    exit(1);
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
if (!$dom->loadHTML($html)) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    fwrite(STDERR, "[FAIL] Failed to parse form HTML. Detected " . count($errors) . " libxml errors." . PHP_EOL);
    exit(1);
}
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Check the in-house donation type radio input.
$donationTypeInputs = $xpath->query("//input[@name='donation-type']");
if ($donationTypeInputs->length === 0) {
    fwrite(STDERR, "[FAIL] No donation-type inputs found." . PHP_EOL);
    exit(1);
}

$radio = $xpath->query("//input[@id='adminDonationTypeWalkIn']")->item(0);
if (!$radio instanceof DOMElement) {
    fwrite(STDERR, "[FAIL] adminDonationTypeWalkIn radio input not found." . PHP_EOL);
    exit(1);
}

if (strtolower($radio->getAttribute('type')) !== 'radio') {
    fwrite(STDERR, "[FAIL] adminDonationTypeWalkIn is not a radio input." . PHP_EOL);
    exit(1);
}

if ($radio->getAttribute('value') !== 'Walk-in') {
    fwrite(STDERR, "[FAIL] adminDonationTypeWalkIn value mismatch. Expected 'Walk-in'." . PHP_EOL);
    exit(1);
}

if (!$radio->hasAttribute('required')) {
    fwrite(STDERR, "[FAIL] adminDonationTypeWalkIn is not marked as required." . PHP_EOL);
    exit(1);
}

if (!$radio->hasAttribute('checked')) {
    fwrite(STDERR, "[FAIL] adminDonationTypeWalkIn is not checked by default." . PHP_EOL);
    exit(1);
}

// Ensure mobile donation inputs are present and disabled.
foreach (['adminMobilePlaceInput', 'adminMobileOrganizerInput'] as $inputId) {
    $input = $xpath->query("//*[@id='{$inputId}']")->item(0);
    if (!$input instanceof DOMElement) {
        fwrite(STDERR, "[FAIL] {$inputId} input not found." . PHP_EOL);
        exit(1);
    }

    if (!$input->hasAttribute('disabled')) {
        fwrite(STDERR, "[FAIL] {$inputId} input should be disabled but is not." . PHP_EOL);
        exit(1);
    }

    $placeholder = $input->getAttribute('placeholder');
    if (stripos($placeholder, 'disabled') === false) {
        fwrite(STDERR, "[FAIL] {$inputId} placeholder should indicate disabled state." . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Admin initial screening form markup matches expected configuration." . PHP_EOL;
exit(0);


#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Interactive demonstration of permalink stability
 * 
 * This script visually demonstrates that permalinks remain stable
 * even when entries are deleted at different positions.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Trail\Services\HashIdService;

// Colors for terminal output
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_BLUE = "\033[34m";
const COLOR_YELLOW = "\033[33m";
const COLOR_RESET = "\033[0m";
const COLOR_BOLD = "\033[1m";

function printHeader(string $text): void
{
    echo "\n" . COLOR_BOLD . COLOR_BLUE . "═══════════════════════════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_BOLD . COLOR_BLUE . "  " . $text . COLOR_RESET . "\n";
    echo COLOR_BOLD . COLOR_BLUE . "═══════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
}

function printSuccess(string $text): void
{
    echo COLOR_GREEN . "✓ " . $text . COLOR_RESET . "\n";
}

function printInfo(string $text): void
{
    echo COLOR_BLUE . "→ " . $text . COLOR_RESET . "\n";
}

function printWarning(string $text): void
{
    echo COLOR_YELLOW . "⚠ " . $text . COLOR_RESET . "\n";
}

function printEntry(int $id, string $hash, bool $deleted = false): void
{
    if ($deleted) {
        echo COLOR_RED . "  [DELETED] ID: " . str_pad((string)$id, 3) . " → Hash: " . $hash . COLOR_RESET . "\n";
    } else {
        echo COLOR_GREEN . "  [ACTIVE]  ID: " . str_pad((string)$id, 3) . " → Hash: " . $hash . COLOR_RESET . "\n";
    }
}

// Initialize HashIdService with test salt
$hashIdService = new HashIdService('demo_salt_for_testing');

printHeader("PERMALINK STABILITY DEMONSTRATION");

echo "This demo shows that permalinks remain stable even when entries are deleted.\n";
echo "Each entry has a unique ID and a corresponding hash ID used in permalinks.\n";

// Scenario 1: Initial state
printHeader("SCENARIO 1: Initial State - 5 Entries");

$entries = [
    1 => $hashIdService->encode(1),
    2 => $hashIdService->encode(2),
    3 => $hashIdService->encode(3),
    4 => $hashIdService->encode(4),
    5 => $hashIdService->encode(5),
];

printInfo("Creating 5 entries with their permalink hashes:");
foreach ($entries as $id => $hash) {
    printEntry($id, $hash);
}

// Scenario 2: User bookmarks entry 3
printHeader("SCENARIO 2: User Bookmarks Entry 3");

$bookmarkedId = 3;
$bookmarkedHash = $entries[$bookmarkedId];

printInfo("User bookmarks entry 3:");
echo COLOR_YELLOW . "  📌 Bookmarked URL: https://example.com/status/" . $bookmarkedHash . COLOR_RESET . "\n";
echo COLOR_YELLOW . "  📌 Points to Entry ID: " . $bookmarkedId . COLOR_RESET . "\n";

// Scenario 3: Delete entry 1 (before bookmarked entry)
printHeader("SCENARIO 3: Delete Entry 1 (Before Bookmarked Entry)");

printWarning("Deleting entry 1...");
$deletedEntry1 = $entries[1];
unset($entries[1]);

echo "\n";
printInfo("Remaining entries:");
foreach ($entries as $id => $hash) {
    printEntry($id, $hash);
}

printInfo("Checking bookmarked entry 3:");
$currentHash3 = $hashIdService->encode($bookmarkedId);
if ($currentHash3 === $bookmarkedHash) {
    printSuccess("Hash is UNCHANGED: " . $bookmarkedHash);
    printSuccess("Bookmarked permalink still works!");
} else {
    echo COLOR_RED . "✗ Hash changed! Permalink would be broken!" . COLOR_RESET . "\n";
}

// Scenario 4: Delete entry 5 (after bookmarked entry)
printHeader("SCENARIO 4: Delete Entry 5 (After Bookmarked Entry)");

printWarning("Deleting entry 5...");
$deletedEntry5 = $entries[5];
unset($entries[5]);

echo "\n";
printInfo("Remaining entries:");
foreach ($entries as $id => $hash) {
    printEntry($id, $hash);
}

printInfo("Checking bookmarked entry 3:");
$currentHash3 = $hashIdService->encode($bookmarkedId);
if ($currentHash3 === $bookmarkedHash) {
    printSuccess("Hash is STILL UNCHANGED: " . $bookmarkedHash);
    printSuccess("Bookmarked permalink still works!");
} else {
    echo COLOR_RED . "✗ Hash changed! Permalink would be broken!" . COLOR_RESET . "\n";
}

// Scenario 5: Delete entry 2 (adjacent to bookmarked entry)
printHeader("SCENARIO 5: Delete Entry 2 (Adjacent to Bookmarked Entry)");

printWarning("Deleting entry 2...");
$deletedEntry2 = $entries[2];
unset($entries[2]);

echo "\n";
printInfo("Remaining entries:");
foreach ($entries as $id => $hash) {
    printEntry($id, $hash);
}

printInfo("Checking bookmarked entry 3:");
$currentHash3 = $hashIdService->encode($bookmarkedId);
if ($currentHash3 === $bookmarkedHash) {
    printSuccess("Hash is STILL UNCHANGED: " . $bookmarkedHash);
    printSuccess("Bookmarked permalink still works!");
} else {
    echo COLOR_RED . "✗ Hash changed! Permalink would be broken!" . COLOR_RESET . "\n";
}

// Scenario 6: User clicks bookmarked link
printHeader("SCENARIO 6: User Clicks Bookmarked Link");

printInfo("User clicks: https://example.com/status/" . $bookmarkedHash);
$decodedId = $hashIdService->decode($bookmarkedHash);

if ($decodedId === $bookmarkedId) {
    printSuccess("Hash decodes to correct entry ID: " . $decodedId);
    
    if (isset($entries[$decodedId])) {
        printSuccess("Entry still exists in database!");
        printSuccess("✨ Permalink works perfectly! ✨");
    } else {
        printWarning("Entry was deleted (would show 404)");
    }
} else {
    echo COLOR_RED . "✗ Hash decodes to wrong ID!" . COLOR_RESET . "\n";
}

// Summary
printHeader("SUMMARY");

echo "All deleted entries:\n";
printEntry(1, $deletedEntry1, true);
printEntry(2, $deletedEntry2, true);
printEntry(5, $deletedEntry5, true);

echo "\n";
echo "Remaining entries:\n";
foreach ($entries as $id => $hash) {
    printEntry($id, $hash);
}

echo "\n";
printSuccess("Bookmarked entry 3 hash remained stable through all deletions");
printSuccess("Permalink: https://example.com/status/" . $bookmarkedHash);
printSuccess("Still resolves to Entry ID: " . $bookmarkedId);

printHeader("CONCLUSION");

echo COLOR_GREEN . "✓ Permalinks are STABLE and RELIABLE" . COLOR_RESET . "\n";
echo COLOR_GREEN . "✓ Deleting entries does NOT break other permalinks" . COLOR_RESET . "\n";
echo COLOR_GREEN . "✓ Users can safely bookmark and share links" . COLOR_RESET . "\n";
echo COLOR_GREEN . "✓ Hash IDs depend only on entry ID, not position" . COLOR_RESET . "\n";

echo "\n";

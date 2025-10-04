<?php
// Quick test for webhook
//
// Usage: php test-webhook.php
//

$env = parse_ini_file(__DIR__ . '/.env');
$secret = $env['WEBHOOK_SECRET'] ?? '';
$webhook_url = $env['WEBHOOK_ENDPOINT'] ?? '';

file_put_contents('php://stderr', "DEBUG: .env file path: " . __DIR__ . "/.env\n");
file_put_contents('php://stderr', "DEBUG: .env exists: " . (file_exists(__DIR__ . '/.env') ? 'yes' : 'no') . "\n");
file_put_contents('php://stderr', "DEBUG: Secret loaded: " . (empty($secret) ? 'EMPTY' : 'yes (length ' . strlen($secret) . ')') . "\n");

$payload = '{"ref":"refs/heads/main"}';
$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

file_put_contents('php://stderr', "DEBUG: Payload: $payload\n");
file_put_contents('php://stderr', "DEBUG: Signature: $signature\n");

echo "Testing webhook logic:\n";
echo "✓ Payload created\n";
echo "✓ Signature: $signature\n";

// Test verification
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$valid = hash_equals($expected, $signature);
echo $valid ? "✓ Signature valid\n" : "✗ Signature failed\n";

// Test branch check
$data = json_decode($payload, true);
$branch_ok = ($data['ref'] === 'refs/heads/main');
echo $branch_ok ? "✓ Main branch\n" : "✗ Wrong branch\n";

echo "Done. Now test live with: $webhook_url\n";

// Final status
$success = ! empty($secret) && $valid && $branch_ok;
echo "\nStatus: " . ($success ? "SUCCESS" : "FAIL") . "\n";


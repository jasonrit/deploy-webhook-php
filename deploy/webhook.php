<?php
// webhook.php - GitHub webhook deploy script
//
// Receives GitHub webhook POST requests on push to main branch
// Validates signature, checks permissions, pulls latest code
// Sends email notification on failure, runs health check on success
//
// Test mode: Add ?test to URL to simulate webhook without deploying
//            Example: https://boonemoylelaw.com/deploy/webhook.php?test
//

// Constants
const WEBHOOK_URL = 'https://boonemoylelaw.com/deploy/webhook.php';
const DEPLOY_PATH = '/var/www/boone';
const HEALTH_CHECK_URL = 'https://boonemoylelaw.com/health';
const FROM_EMAIL = 'jason@ritenour.net';
const FROM_NAME = 'JR Deploybot';
const TO_EMAIL = 'bidslammer@gmail.com';
const REQUIRED_GROUP = 'www-data';

// Get env vars
$env = parse_ini_file('../.env');
$webhook_secret = $env['WEBHOOK_SECRET'] ?? '';

// Test mode check - simulates GitHub webhook without actual deployment
// Use ?test in URL to run in test mode (e.g. webhook.php?test)
if (isset($_GET['test'])) {
  // Create fake but valid payload and signature for testing
  $payload = '{"ref":"refs/heads/main"}';
  $signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
  $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = $signature;
}
else {
  // Production mode - get real payload from GitHub
  $payload = file_get_contents('php://input');
}

// Verify GitHub webhook signature to prevent unauthorized access
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (! verify_signature($payload, $signature)) {
  http_response_code(403);
  exit('Invalid signature');
}
$data = json_decode($payload, true);
// Only deploy on push to main branch - ignore other branches/events
if ($data['ref'] !== 'refs/heads/main') {
  exit('Not main branch');
}

// Test mode - show what would happen without actually deploying
if (isset($_GET['test'])) {
  echo 'Test mode: Would check permissions and run git fetch/pull here';
  exit;
}

// Check that deploy directory has correct permissions (www-data group)
if (! check_permissions()) {
  http_response_code(500);
  exit('Permission error: Deploy path not owned by ' . REQUIRED_GROUP);
}

// Execute deployment - fetch latest code and pull changes
chdir(DEPLOY_PATH);
exec('git fetch 2>&1', $output, $return_code);
exec('git pull origin main 2>&1', $output, $return_code);
if ($return_code === 0) {
  // Add build steps here if needed (composer, npm, etc.)
  // exec('composer install --no-dev');
  // exec('npm run build');

  // Verify deployment worked with health check
  $health_check = file_get_contents(HEALTH_CHECK_URL);
  if ($health_check === false) {
    http_response_code(500);
    echo 'Deploy failed health check';
    exit;
  }
  echo 'Deploy successful';
}
else {
  // Send failure notification email
  $headers = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
  mail(TO_EMAIL, 'Deploy to boonemoylelaw failed', implode("\n", $output), $headers);
  http_response_code(500);
  echo 'Deploy failed: ' . implode("\n", $output);
}

// Verify deploy directory is owned by correct group for security
function check_permissions(): bool
{
  $stat = stat(DEPLOY_PATH);
  if ($stat === false)
    return false;

  $gid = $stat['gid'];
  $group_info = posix_getgrgid($gid);
  return $group_info['name'] === REQUIRED_GROUP;
}

// Verify webhook signature matches expected value using HMAC
function verify_signature(string $payload, string $signature): bool
{
  global $webhook_secret;
  $expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
  return hash_equals($expected, $signature);
}


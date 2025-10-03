<?php
// central-webhook.php - Multi-project GitHub webhook deploy
//
// Single webhook for all projects on server
// Maps repo names to deploy paths via config
//

// Repo to path mapping
const REPOS = [
  'boonemoylelaw' => '/var/www/boone',
  'brightbuilders' => '/var/www/bb',
  'leanpress' => '/var/www/leanpress-jr',
  'leanpress-site' => '/var/www/leanpress-site',
  'test-deploy-actions' => '/var/www/jr-deploy',
  'texas-dwi-penalties ' => '/var/www/tx-dwi',
  // add more repos here
];

// Configuration
const FROM_EMAIL = 'jason@ritenour.net';
const FROM_NAME = 'JR Deploybot';
const TO_EMAIL = 'bidslammer@gmail.com';
const REQUIRED_GROUP = 'www-data';
const LOG_FILE = __DIR__ . '/../deploy.log';
const HEALTH_CHECK_PATH = '/health';

$env = parse_ini_file(__DIR__ . '/../.env');
$webhook_secret = $env['WEBHOOK_SECRET'] ?? '';

$raw_payload = file_get_contents('php://input');

// Verify signature against raw payload
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (! verify_signature($raw_payload, $signature)) {
  log_event('FAILED', 'unknown', 'Invalid signature');
  http_response_code(403);
  exit('Invalid signature');
}

// Parse payload (handle form-encoded if needed)
$payload = $raw_payload;
if (strpos($payload, 'payload=') === 0) {
  parse_str($payload, $parsed);
  $payload = $parsed['payload'] ?? '';
}

$data = json_decode($payload, true);

// Only main branch
if ($data['ref'] !== 'refs/heads/main') {
  log_event('SKIPPED', $data['repository']['name'] ?? 'unknown', 'Not main branch');
  exit('Not main branch');
}

// Get repo name and lookup deploy path
$repo_name = $data['repository']['name'];
if (! isset(REPOS[$repo_name])) {
  log_event('FAILED', $repo_name, 'Unknown repo');
  http_response_code(404);
  exit("Unknown repo: $repo_name");
}

$deploy_path = REPOS[$repo_name];

// Check permissions
if (! check_permissions($deploy_path)) {
  log_event('FAILED', $repo_name, "Permission error on $deploy_path");
  send_failure_email($repo_name, "Permission error on $deploy_path");
  http_response_code(500);
  exit('Permission error');
}

// Deploy
chdir($deploy_path);
exec('git fetch 2>&1', $output, $return_code);
exec('git pull origin main 2>&1', $output, $return_code);

if ($return_code === 0) {
  // Health check
  $health_url = 'https://' . $_SERVER['HTTP_HOST'] . HEALTH_CHECK_PATH;
  $health = @file_get_contents($health_url);
  if ($health === false || strpos($health, 'Status OK') === false) {
    log_event('FAILED', $repo_name, "Health check failed: $health_url");
    send_failure_email($repo_name, "Health check failed: $health_url");
    http_response_code(500);
    exit('Deploy failed health check');
  }
  log_event('SUCCESS', $repo_name, 'Deployed');
  send_success_email($repo_name);
  echo "Deploy successful: $repo_name";
}
else {
  log_event('FAILED', $repo_name, implode("\n", $output));
  send_failure_email($repo_name, implode("\n", $output));
  http_response_code(500);
  exit('Deploy failed');
}

function check_permissions(string $path): bool
{
  $stat = stat($path);
  if ($stat === false)
    return false;
  
  $gid = $stat['gid'];
  $group_info = posix_getgrgid($gid);
  return $group_info['name'] === REQUIRED_GROUP;
}

function verify_signature(string $payload, string $signature): bool
{
  global $webhook_secret;
  $expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
  return hash_equals($expected, $signature);
}

function send_failure_email(string $repo, string $message): void
{
  $subject = "Deploy failed: $repo";
  $headers = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
  $sent = mail(TO_EMAIL, $subject, $message, $headers);
  log_event('EMAIL', $repo, $sent ? 'Email sent' : 'Email failed');
}

function send_success_email(string $repo): void
{
  $subject = "Deploy successful: $repo";
  $message = "Deployment completed successfully for $repo";
  $headers = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
  $sent = mail(TO_EMAIL, $subject, $message, $headers);
  log_event('EMAIL', $repo, $sent ? 'Email sent' : 'Email failed');
}

function log_event(string $status, string $repo, string $message): void
{
  $timestamp = date('Y-m-d H:i:s');
  $log_line = "[$timestamp] $status - $repo - $message\n";
  file_put_contents(LOG_FILE, $log_line, FILE_APPEND);
}


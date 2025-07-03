<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// List of endpoints that can be used by the Push Generator. Please send a PR
// on GitHub (beverloo/peter.sh) if yours isn't listed.
$endpointPatterns = [
  '/^https:\/\/android\.googleapis\.com\/gcm\/send.*/',
  '/^https:\/\/fcm\.googleapis\.com.*/',
  '/^https:\/\/jmt17\.google\.com.*/',
  '/^https:\/\/updates\.push\.services\.mozilla\.com.*/',
  '/^https:\/\/updates-autopush\.stage\.mozaws\.net.*/',
  '/^https:\/\/graph\.facebook\.com\/rl_push_send.*/',
  '/^https:\/\/.*\.push\.apple\.com.*/',
  '/^https:\/\/.*\.notify\.windows\.com.*/',
];

if (file_exists(__DIR__ . '/push.private.php'))
  require_once __DIR__ . '/push.private.php';

// Responds with HTTP |$status| and outputs |$message| for additional info.
function fatalError($status, $message) {
  Header('HTTP/1.0 ' . $status); 
  echo $message;
  exit;
}

// Converts the HTTP header |$name| to the key used in $_SERVER.
function toHeaderName($name) {
  return 'HTTP_' . str_replace('-', '_', strtoupper($name));
}

// Determines if the |$endpoint| contains a whitelisted URL.
function isWhitelisted($endpoint) {
  global $endpointPatterns;

  foreach ($endpointPatterns as $pattern) {
    if (preg_match($pattern, $endpoint)) {
      return true;
    }
  }

  return false;
}

// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] != 'POST')
  fatalError('405 Method Not Allowed', 'Only POST requests may be made to this tool.');

$requestHeaders = array_change_key_case(apache_request_headers(), CASE_LOWER);

if (!array_key_exists('x-endpoint', $requestHeaders))
  fatalError('400 Bad Request', 'The X-Endpoint HTTP header must be set.');

$endpoint = $requestHeaders['x-endpoint'];
$headers = [];

if (!isWhitelisted($endpoint))
  fatalError(
    '403 Forbidden',
    'The endpoint has not been whitelisted. Send a PR to the https://github.com/beverloo/peter.sh/blob/master/tests/push.php file?'
  );

$optionalHeaders = [
  'Authorization',
  'Content-Encoding',
  'Content-Type',
  'Crypto-Key',
  'Encryption',
  'TTL',
];

foreach ($optionalHeaders as $headerName) {
  $lowerCaseHeaderName = strtolower($headerName);
  
  if (!array_key_exists($lowerCaseHeaderName, $requestHeaders))
    continue;
  
  $value = $requestHeaders[$lowerCaseHeaderName];
  if ($lowerCaseHeaderName == 'content-type' && $value != 'application/json')
    continue;

  $headers[] = $headerName . ': ' . $value;
}

$rawData = file_get_contents('php://input');

// -----------------------------------------------------------------------------

$request = curl_init();

curl_setopt_array($request, [
  CURLOPT_URL             => $endpoint,
  CURLOPT_HTTPHEADER      => $headers,
  CURLOPT_POST            => true,
  CURLOPT_POSTFIELDS      => $rawData,
  CURLOPT_RETURNTRANSFER  => true
]);

$content = curl_exec($request);
$response = curl_getinfo($request);

Header('HTTP/1.0 ' . $response['http_code']);
Header('Content-Type: ' . $response['content_type']);

echo $content;

<?php
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function fetchHtml(string $url): array {
  $headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Upgrade-Insecure-Requests: 1',
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_USERAGENT      => UA,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
  ]);

  $html = curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);

  return [
    'html'      => $html,
    'final_url' => $info['EFFECTIVE_URL'] ?? $url,
    'content_type' => $info['content_type'] ?? '',
  ];
}
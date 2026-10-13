<?php
require 'db.php';
require_once __DIR__ . '/http_client.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM selectors WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo json_encode(['error' => 'not found']); exit; }

$value = fetchValue($row['url'], $row['selector']);

$db->prepare("UPDATE selectors SET last_value=?, last_fetched_at=CURRENT_TIMESTAMP WHERE id=?")
   ->execute([$value, $id]);

echo json_encode(['value' => $value]);

// ---------- helpers ----------

function fetchValue(string $url, string $selector): ?string {
  $res = fetchHtml($url);
  if (empty($res['html'])) return null;

  libxml_use_internal_errors(true);
  $doc = new DOMDocument();
  $doc->loadHTML('<?xml encoding="UTF-8">' . $res['html']);
  $xpath = new DOMXPath($doc);

  $xp = cssToXPath($selector);
  $nodes = $xpath->query($xp);
  if (!$nodes || $nodes->length === 0) return null;

  return trim(preg_replace('/\s+/', ' ', $nodes->item(0)->textContent));
}

/**
 * تبدیل CSS selector تولیدی خودمون به XPath.
 * پشتیبانی: tag, #id, tag.class, tag:nth-of-type(n), و ترکیب با >
 */
function cssToXPath(string $css): string {
  $parts = preg_split('/\s*>\s*/', trim($css));
  $xp = '';
  foreach ($parts as $part) {
    $seg = '*';
    // tag#id یا #id
    if (preg_match('/^([a-z0-9]*)#([\w-]+)/i', $part, $m)) {
      $tag = $m[1] ?: '*';
      $seg = $tag . '[@id="' . $m[2] . '"]';
    }
    // tag.class
    elseif (preg_match('/^([a-z0-9]*)\.([\w-]+)/i', $part, $m)) {
      $tag = $m[1] ?: '*';
      $seg = $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $m[2] . ' ")]';
    }
    // tag:nth-of-type(n)
    elseif (preg_match('/^([a-z0-9*]+):nth-of-type\((\d+)\)$/i', $part, $m)) {
      $seg = $m[1] . '[' . $m[2] . ']';
    }
    // tag ساده
    elseif (preg_match('/^[a-z0-9*]+$/i', $part)) {
      $seg = $part;
    }
    $xp .= ($xp === '' ? '' : '/') . $seg;
  }
  return '//' . $xp;
}
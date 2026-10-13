<?php
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id'])) {
  $db->prepare("DELETE FROM selectors WHERE id=?")->execute([(int)$_GET['id']]);
  echo json_encode(['ok' => true]); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['url']) || empty($data['selector'])) {
  http_response_code(400); echo json_encode(['error' => 'missing']); exit;
}

$stmt = $db->prepare("INSERT INTO selectors (url, selector, sample, tag, el_id) VALUES (?,?,?,?,?)");
$stmt->execute([
  $data['url'], $data['selector'],
  $data['sample'] ?? '', $data['tag'] ?? '', $data['el_id'] ?? ''
]);

echo json_encode([
  'ok' => true,
  'id' => $db->lastInsertId(),
  'selector' => $data['selector']
]);
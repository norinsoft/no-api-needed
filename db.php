<?php
$db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS selectors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  url TEXT NOT NULL,
  selector TEXT NOT NULL,
  sample TEXT,
  tag TEXT,
  el_id TEXT,
  last_value TEXT,
  last_fetched_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
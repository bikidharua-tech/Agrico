<?php
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status' => 'ok',
    'service' => 'Agrico',
    'time' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);

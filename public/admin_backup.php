<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$gate = query_value('admin_key');
require_admin_gate_or_404($gate);
if (!admin_session()) {
    http_response_code(403);
    exit('Forbidden');
}
$file = basename(query_value('file'));
$path = app_config()['storage_path'] . '/backups/' . $file;
if ($file === '' || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);

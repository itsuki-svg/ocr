<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start_safe();
require_api_role('sysadmin');
$users = db_query('SELECT * FROM users ORDER BY created_at DESC');
json_response($users);

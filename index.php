<?php
require_once __DIR__ . '/includes/bootstrap.php';
session_start_safe();
$user = current_user();
if (!$user) redirect('/login.php');
if ($user['status'] === 'pending')  redirect('/pending.php');
if ($user['status'] === 'rejected') redirect('/error.php?reason=rejected');
if (in_array($user['role'], ['sysadmin','accounting'])) redirect('/admin/receipts.php');
redirect('/new.php');

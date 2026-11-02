<?php
require_once __DIR__ . '/includes/auth.php';
redirect(user() ? 'dashboard.php' : 'login.php');

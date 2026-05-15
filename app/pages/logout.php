<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
logout_user();
flash('success', 'You have been signed out.');
redirect('/login');

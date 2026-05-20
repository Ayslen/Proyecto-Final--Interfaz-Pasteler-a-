<?php
require_once __DIR__ . '/../app/bootstrap.php';
Auth::logout();
session_start();
Flash::set('success', 'Sesión cerrada correctamente.');
redirect('login.php');

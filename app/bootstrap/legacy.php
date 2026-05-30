<?php
declare(strict_types=1);

if (!isset($_SESSION['auth'])) {
    $_SESSION['auth'] = false;
}

if (isset($_SESSION['user']) && !isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = $_SESSION['user'];
}

if (isset($_SESSION['user']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $_SESSION['user'];
}

if (isset($_SESSION['email']) && !isset($_SESSION['user_email'])) {
    $_SESSION['user_email'] = $_SESSION['email'];
}

if (!isset($_SESSION['is_admin'])) {
    $_SESSION['is_admin'] = false;
}

<?php
require_once dirname(__DIR__) . '/auth.php';
setcookie('pars_session', '', time() - 3600, '/', '', false, true);
session_destroy();
header('Location: ../index.php');
exit;

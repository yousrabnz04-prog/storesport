<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Signing out changes state, so it is only accepted as a POST with a valid CSRF token.
if (!isPost()) {
    redirect('index.php');
}
verify_csrf();
logoutSession();
header('Location: ' . url('login.php'));
exit;

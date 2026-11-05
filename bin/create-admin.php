<?php
/**
 * Create an administrator account from the command line.
 *
 *   php bin/create-admin.php <username> <email>
 *
 * The first account created becomes the owner; later ones are regular admins.
 * The password is requested interactively so it never appears in shell history.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

[$script, $username, $email] = array_pad($argv, 3, null);
if (!$username || !$email) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username> <email>\n");
    exit(1);
}
$email = strtolower($email);

if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Invalid username (3-50 letters, numbers, dots, dashes, underscores).\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$exists = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ? OR username = ?');
$exists->execute([$email, $username]);
if ((int) $exists->fetchColumn() > 0) {
    fwrite(STDERR, "An administrator with this email or username already exists.\n");
    exit(1);
}

echo 'Password (min. 10 characters, input is visible): ';
$password = trim((string) fgets(STDIN));
if (strlen($password) < 10) {
    fwrite(STDERR, "The password must be at least 10 characters long.\n");
    exit(1);
}

$hasOwner = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'owner'")->fetchColumn() > 0;
$role = $hasOwner ? 'admin' : 'owner';

$pdo->prepare('INSERT INTO admins (username, email, password, role, can_manage_admins) VALUES (?, ?, ?, ?, ?)')
    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $role === 'owner' ? 1 : 0]);

echo "Created $role account \"$username\". You can now sign in at the login page.\n";

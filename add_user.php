<?php
session_start();
$users_file = __DIR__ . '/users.txt';

// Only allow admin to add users
$admin_user = 'admin'; // Change as needed
if (!isset($_SESSION['user']) || $_SESSION['user'] !== $admin_user) {
    header('Location: /login.php');
    exit();
}

$error = '';
$success = '';

// New: Only ask for username, generate invite code (crypto key style)
$invites_file = __DIR__ . '/invites.txt';
$invite = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user = trim($_POST['new_user'] ?? '');
    if ($new_user === '') {
        $error = 'Username required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $new_user)) {
        $error = 'Invalid username.';
    } elseif (file_exists($users_file)) {
        foreach (file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            list($user, ) = explode(':', $line, 2);
            if ($user === $new_user) {
                $error = 'Username already exists.';
                break;
            }
        }
    }
    if (!$error) {
        // Generate a strong invite code (crypto key style)
        $invite = bin2hex(random_bytes(16));
        file_put_contents($invites_file, $invite . ':' . $new_user . "\n", FILE_APPEND);
        $success = 'Invite code generated for ' . htmlspecialchars($new_user) . ': <b>' . htmlspecialchars($invite) . '</b>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Add User (Invite)</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Add User (Invite)</h2>
<a href="index.php">Back to Home</a>
<hr>
<form method="post" autocomplete="off">
    <label>Username: <input type="text" name="new_user" required></label>
    <button type="submit">Generate Invite Code</button>
</form>
<?php if ($success): ?>
    <div style="color:green;"> <?= $success ?> </div>
<?php elseif ($error): ?>
    <div style="color:red;"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>
<h3>Unused Invite Codes</h3>
<ul>
<?php 
$invites = file_exists($invites_file) ? file($invites_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
foreach ($invites as $line): 
    list($code, $user) = explode(':', $line, 2);
?>
    <li><b><?= htmlspecialchars($user) ?></b>: <?= htmlspecialchars($code) ?></li>
<?php endforeach; ?>
</ul>
<form method="post" autocomplete="off">
    <label>Username: <input type="text" name="new_user" required></label><br>
    <label>Password: <input type="password" name="new_pass" required></label><br>
    <label>Confirm Password: <input type="password" name="confirm_pass" required></label><br>
    <button type="submit">Add User</button>
</form>
<p><a href="/index.php">Back to Home</a></p>
</body>
</html>

<?php
session_start();
$users_file = __DIR__ . '/users.txt';

if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

$username = $_SESSION['user'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $lines = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated = false;
        foreach ($lines as $i => $line) {
            list($user, $hash) = explode(':', $line, 2);
            if ($user === $username && password_verify($old_password, $hash)) {
                $lines[$i] = $user . ':' . password_hash($new_password, PASSWORD_DEFAULT);
                $updated = true;
                break;
            }
        }
        if ($updated) {
            file_put_contents($users_file, implode("\n", $lines) . "\n");
            $success = 'Password changed successfully!';
        } else {
            $error = 'Old password incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Change Password</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Change Password</h2>
<?php if ($success): ?>
    <div style="color:green;"> <?= htmlspecialchars($success) ?> </div>
<?php elseif ($error): ?>
    <div style="color:red;"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>
<form method="post" autocomplete="off">
    <label>Old Password: <input type="password" name="old_password" required></label><br>
    <label>New Password: <input type="password" name="new_password" required></label><br>
    <label>Confirm New Password: <input type="password" name="confirm_password" required></label><br>
    <button type="submit">Change Password</button>
</form>
<p><a href="/index.php">Back to Home</a></p>
</body>
</html>

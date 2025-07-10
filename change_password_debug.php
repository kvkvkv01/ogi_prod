<?php
session_start();
$users_file = __DIR__ . '/users.txt';

// Debug information
echo "<h3>Debug Information:</h3>";
echo "Session user: " . ($_SESSION['user'] ?? 'NOT SET') . "<br>";
echo "Users file path: " . $users_file . "<br>";
echo "Users file exists: " . (file_exists($users_file) ? 'YES' : 'NO') . "<br>";
echo "Users file writable: " . (is_writable($users_file) ? 'YES' : 'NO') . "<br>";
echo "Current directory: " . __DIR__ . "<br><br>";

if (!isset($_SESSION['user'])) {
    echo "ERROR: User not logged in!<br>";
    echo "<a href='/login.php'>Go to Login</a>";
    exit();
}

$username = $_SESSION['user'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    echo "<h4>Form Data:</h4>";
    echo "Old password length: " . strlen($old_password) . "<br>";
    echo "New password length: " . strlen($new_password) . "<br>";
    echo "Confirm password length: " . strlen($confirm_password) . "<br>";

    if ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $lines = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated = false;
        
        echo "<h4>Processing users file:</h4>";
        echo "Total lines: " . count($lines) . "<br>";
        
        foreach ($lines as $i => $line) {
            list($user, $hash) = explode(':', $line, 2);
            echo "Checking user: $user<br>";
            
            if ($user === $username) {
                echo "Found matching user: $username<br>";
                echo "Password verify result: " . (password_verify($old_password, $hash) ? 'TRUE' : 'FALSE') . "<br>";
                
                if (password_verify($old_password, $hash)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $lines[$i] = $user . ':' . $new_hash;
                    $updated = true;
                    echo "Password updated for user: $username<br>";
                    break;
                }
            }
        }
        
        if ($updated) {
            $result = file_put_contents($users_file, implode("\n", $lines) . "\n");
            echo "File write result: " . ($result !== false ? "SUCCESS ($result bytes)" : "FAILED") . "<br>";
            if ($result !== false) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to write to users file.';
            }
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
    <title>Change Password (Debug)</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Change Password (Debug Version)</h2>
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
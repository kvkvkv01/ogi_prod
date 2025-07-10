<?php
session_start();
// Simple user data file (username:hashed_password) for demo
$users_file = __DIR__ . '/users.txt';

// Handle login form submission
// Always generate captcha if not set
if (!isset($_SESSION['captcha_answer'])) {
    $a = rand(1, 9);
    $b = rand(1, 9);
    $_SESSION['captcha_question'] = "$a + $b = ?";
    $_SESSION['captcha_answer'] = $a + $b;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    if ($captcha === '' || !isset($_SESSION['captcha_answer']) || intval($captcha) !== $_SESSION['captcha_answer']) {
        $error = 'Captcha incorrect. Please try again.';
        // Regenerate captcha
        unset($_SESSION['captcha_answer']);
        unset($_SESSION['captcha_question']);
    } else {
        $found = false;
        if (file_exists($users_file)) {
            $lines = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                list($user, $hash) = explode(':', $line, 2);
                if ($user === $username && password_verify($password, $hash)) {
                    $_SESSION['user'] = $username;
                    unset($_SESSION['captcha_answer']);
                    unset($_SESSION['captcha_question']);
                    header('Location: /index.php');
                    exit();
                }
            }
        }
        $error = 'Invalid username or password.';
        unset($_SESSION['captcha_answer']);
        unset($_SESSION['captcha_question']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Login</h2>
<?php if (!empty($error)): ?>
    <div style="color:red;"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>
<form method="post" autocomplete="off">
    <label>Username: <input type="text" name="username" required></label><br>
    <label>Password: <input type="password" name="password" required></label><br>
    <label>Captcha: <b><?= isset($_SESSION['captcha_question']) ? htmlspecialchars($_SESSION['captcha_question']) : '' ?></b>
        <input type="text" name="captcha" required style="width:40px;"></label><br>
    <button type="submit">Login</button>
</form>
</body>
</html>

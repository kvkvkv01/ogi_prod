<?php
// Registration with invite code, blog name, and description
$users_file = __DIR__ . '/users.txt';
$invites_file = __DIR__ . '/invites.txt';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $invite = trim($_POST['invite'] ?? '');
    $blogname = trim($_POST['blogname'] ?? '');
    $blogdesc = trim($_POST['blogdesc'] ?? '');

    // Validate
    if ($username === '' || $password === '' || $invite === '' || $blogname === '' || $blogdesc === '') {
        $error = 'All fields required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        $error = 'Invalid username.';
    } elseif (strlen($password) < 6) {
        $error = 'Password too short.';
    } elseif (!file_exists($invites_file)) {
        $error = 'Invalid invite code.';
    } else {
        // Invite code format: code:username
        $found = false;
        $invites = file($invites_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($invites as $line) {
            list($code, $inv_user) = explode(':', $line, 2);
            if ($invite === $code && $username === $inv_user) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $error = 'Invalid invite code or username does not match invite.';
        }
    }
    if (!$error && file_exists($users_file)) {
        foreach (file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            list($user, ) = explode(':', $line, 2);
            if ($user === $username) {
                $error = 'Username already exists.';
                break;
            }
        }
    }
    // If valid, create user and blog
    if (!$error) {
        // Remove invite code (move to used_invites.txt)
        $invites = file($invites_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $used_invites_file = __DIR__ . '/used_invites.txt';
        $new_invites = [];
        foreach ($invites as $line) {
            list($code, $inv_user) = explode(':', $line, 2);
            if ($invite === $code && $username === $inv_user) {
                // Move to used_invites.txt
                file_put_contents($used_invites_file, $line . "\n", FILE_APPEND);
            } else {
                $new_invites[] = $line;
            }
        }
        file_put_contents($invites_file, implode("\n", $new_invites) . "\n");
        // Add user (ensure newline before appending if file not empty)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $user_line = $username . ':' . $hash;
        $existing = file_get_contents($users_file);
        if (strlen(trim($existing)) > 0 && substr($existing, -1) !== "\n") {
            file_put_contents($users_file, "\n" . $user_line . "\n", FILE_APPEND);
        } else {
            file_put_contents($users_file, $user_line . "\n", FILE_APPEND);
        }
        // Create blog folder
        $blog_dir = __DIR__ . '/blog/' . $username;
        mkdir($blog_dir, 0777, true);
        mkdir($blog_dir . '/posts', 0777, true);
        mkdir($blog_dir . '/uploads', 0777, true);
        // Write header
        file_put_contents($blog_dir . '/header.txt', "Title: $blogname\nDescription: $blogdesc\n");
        // Create index.php for the blog
        $index_code = file_get_contents(__DIR__ . '/blog/kanon/index.php');
        file_put_contents($blog_dir . '/index.php', $index_code);
        // Copy edit_blog.php to new blog
        $edit_blog_src = __DIR__ . '/blog/kanon/edit_blog.php';
        $edit_blog_dst = $blog_dir . '/edit_blog.php';
        if (file_exists($edit_blog_src)) {
            copy($edit_blog_src, $edit_blog_dst);
        }
        $success = 'Account and blog created! You can now log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h1>Register</h1>
<a href="index.php">Back to Home</a>
<hr>
<?php if ($error): ?>
    <div style="color:red;"> <?= htmlspecialchars($error) ?> </div>
<?php elseif ($success): ?>
    <div style="color:green;"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<form method="post" autocomplete="off">
    <label>Username: <input type="text" name="username" required></label><br>
    <label>Password: <input type="password" name="password" required></label><br>
    <label>Invite Code: <input type="text" name="invite" required></label><br>
    <label>Blog Name: <input type="text" name="blogname" required></label><br>
    <label>Blog Description:<br><textarea name="blogdesc" rows="3" cols="40" required></textarea></label><br>
    <button type="submit">Register</button>
</form>
</body>
</html>

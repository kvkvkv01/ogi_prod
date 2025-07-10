<?php
session_start();
$admin_user = 'admin';
if (!isset($_SESSION['user']) || $_SESSION['user'] !== $admin_user) {
    header('Location: /login.php');
    exit();
}
$invites_file = __DIR__ . '/../invites.txt';
$invite = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate a random invite code
    $invite = bin2hex(random_bytes(8));
    file_put_contents($invites_file, $invite . "\n", FILE_APPEND);
}
// Show all unused invite codes
$invites = file_exists($invites_file) ? file($invites_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Generate Invite Code</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h1>Generate Invite Code</h1>
<a href="index.php">Back to Home</a>
<hr>
<form method="post">
    <button type="submit">Generate New Invite Code</button>
</form>
<?php if ($invite): ?>
    <div style="color:green;">New invite code: <b><?= htmlspecialchars($invite) ?></b></div>
<?php endif; ?>
<h2>Unused Invite Codes</h2>
<ul>
<?php foreach ($invites as $code): ?>
    <li><?= htmlspecialchars($code) ?></li>
<?php endforeach; ?>
</ul>
</body>
</html>

<?php
session_start();
// List blogs (folders inside /blog/)
$blogs = [];
foreach (glob(__DIR__ . '/*', GLOB_ONLYDIR) as $dir) {
    $name = basename($dir);
    if ($name[0] !== '.') {
        $blogs[] = $name;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Blog Index</title>
    <link rel="stylesheet" type="text/css" href="../css.css"/>
</head>
<body>
<h1>Blogs</h1>
<a href="../index.php">Back to Home</a>
<hr>
<h2>Available Blogs</h2>
<ul>
<?php foreach ($blogs as $blog): ?>
    <li><a href="<?= htmlspecialchars($blog) ?>/index.php">/blog/<?= htmlspecialchars($blog) ?>/</a></li>
<?php endforeach; ?>
</ul>
</body>
</html>

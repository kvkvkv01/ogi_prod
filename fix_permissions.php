<?php
session_start();

// Only allow admin to run this
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

$blog_dir = __DIR__ . '/blog';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fix permissions for all blog directories
    if (is_dir($blog_dir)) {
        $blogs = glob($blog_dir . '/*', GLOB_ONLYDIR);
        foreach ($blogs as $blog) {
            $blog_name = basename($blog);
            $posts_dir = $blog . '/posts';
            $uploads_dir = $blog . '/uploads';
            
            // Create directories if they don't exist
            if (!is_dir($posts_dir)) {
                mkdir($posts_dir, 0777, true);
                $results[] = "Created posts directory for $blog_name";
            }
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
                $results[] = "Created uploads directory for $blog_name";
            }
            
            // Set permissions (ignore errors on shared hosting)
            @chmod($blog, 0777);
            @chmod($posts_dir, 0777);
            @chmod($uploads_dir, 0777);
            
            $results[] = "Fixed permissions for $blog_name";
        }
    }
    
    $success = 'Permissions fixed for all blogs!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fix Blog Permissions</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Fix Blog Permissions</h2>
<a href="index.php">Back to Home</a>
<hr>

<?php if (!empty($success)): ?>
    <div style="color:green;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
    <h3>Results:</h3>
    <ul>
    <?php foreach ($results as $result): ?>
        <li><?= htmlspecialchars($result) ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post">
    <p>This will fix permissions for all existing blog directories.</p>
    <button type="submit">Fix Permissions</button>
</form>

<h3>Current Blog Directories:</h3>
<ul>
<?php
if (is_dir($blog_dir)) {
    $blogs = glob($blog_dir . '/*', GLOB_ONLYDIR);
    foreach ($blogs as $blog) {
        $blog_name = basename($blog);
        $posts_exists = is_dir($blog . '/posts') ? '✓' : '✗';
        $uploads_exists = is_dir($blog . '/uploads') ? '✓' : '✗';
        $posts_writable = is_writable($blog . '/posts') ? '✓' : '✗';
        $uploads_writable = is_writable($blog . '/uploads') ? '✓' : '✗';
        
        echo "<li><strong>$blog_name</strong>: posts($posts_exists), uploads($uploads_exists), posts writable($posts_writable), uploads writable($uploads_writable)</li>";
    }
}
?>
</ul>
</body>
</html> 
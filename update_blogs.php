<?php
session_start();
// Only allow admin to run this
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

$blog_root = __DIR__ . '/blog';
$kanon_index = $blog_root . '/kanon/index.php';
$kanon_thread = $blog_root . '/kanon/thread.php';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists($kanon_index)) {
        $results[] = 'Template index.php not found in blog/kanon!';
    } elseif (!file_exists($kanon_thread)) {
        $results[] = 'Template thread.php not found in blog/kanon!';
    } else {
        $blogs = glob($blog_root . '/*', GLOB_ONLYDIR);
        foreach ($blogs as $blog) {
            $blog_name = basename($blog);
            $target_index = $blog . '/index.php';
            $target_thread = $blog . '/thread.php';
            
            // Copy index.php
            if (copy($kanon_index, $target_index)) {
                $results[] = "Updated index.php for $blog_name";
            } else {
                $results[] = "Failed to update index.php for $blog_name";
            }
            
            // Copy thread.php
            if (copy($kanon_thread, $target_thread)) {
                $results[] = "Updated thread.php for $blog_name";
            } else {
                $results[] = "Failed to update thread.php for $blog_name";
            }
            
            // Ensure folders exist
            foreach (['posts', 'uploads', 'replies'] as $folder) {
                $dir = $blog . '/' . $folder;
                if (!is_dir($dir)) {
                    if (mkdir($dir, 0777, true)) {
                        $results[] = "Created $folder for $blog_name";
                    } else {
                        $results[] = "Failed to create $folder for $blog_name";
                    }
                }
                @chmod($dir, 0777);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Update All Blogs</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Update All Blogs</h2>
<a href="index.php">Back to Home</a>
<hr>
<form method="post">
    <p>This will update <b>index.php</b> and <b>thread.php</b> for all blogs and ensure <b>posts</b>, <b>uploads</b>, and <b>replies</b> folders exist in each blog directory.</p>
    <button type="submit">Update Blogs</button>
</form>
<?php if (!empty($results)): ?>
    <h3>Results:</h3>
    <ul>
    <?php foreach ($results as $result): ?>
        <li><?= htmlspecialchars($result) ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
</body>
</html> 
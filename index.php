<?php
session_start();
// List blogs (folders inside /blog/) with metadata
$blogs = [];
$blog_dir = __DIR__ . '/blog';
if (is_dir($blog_dir)) {
    foreach (glob($blog_dir . '/*', GLOB_ONLYDIR) as $dir) {
        $name = basename($dir);
        if ($name[0] !== '.') {
            $blog_data = [
                'name' => $name,
                'title' => $name,
                'started' => 'Unknown',
                'posts' => 0,
                'images' => 0
            ];
            
            // Get blog title from header.txt
            $header_file = $dir . '/header.txt';
            if (file_exists($header_file)) {
                $lines = file($header_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (stripos($line, 'Title:') === 0) {
                        $blog_data['title'] = trim(substr($line, 6));
                    }
                }
            }
            
            // Count posts
            $posts_dir = $dir . '/posts';
            if (is_dir($posts_dir)) {
                $blog_data['posts'] = count(glob($posts_dir . '/*_post.txt'));
                
                // Count images in posts
                foreach (glob($posts_dir . '/*_post.txt') as $post_file) {
                    $content = file_get_contents($post_file);
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        if (stripos($line, 'Image:') === 0) {
                            $img = trim(substr($line, 6));
                            if ($img && file_exists($dir . '/' . $img)) {
                                $blog_data['images']++;
                            }
                        }
                    }
                }
            }
            
            // Get start date from folder creation
            $blog_data['started'] = 'Unknown';
            if (is_dir($dir)) {
                $stat = stat($dir);
                if ($stat) {
                    $dt = new DateTime("@".$stat['ctime']);
                    $dt->setTimezone(new DateTimeZone("Asia/Tokyo"));
                    $blog_data['started'] = $dt->format('Y/m/d');
                }
            }
            
            $blogs[] = $blog_data;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Home</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
    <style>
        .blog-table {
            width: 555px;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: white;
            border: solid 2px #4e5053;
        }
        
        .blog-table th,
        .blog-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #4e5053;
            color: #4e5053;
        }
        
        .blog-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            border-bottom: 2px solid #4e5053;
        }
        
        .blog-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .blog-table a {
            color: #4e5053;
            text-decoration: none;
            font-weight: bold;
        }
        
        .blog-table a:hover {
            color: #000;
        }
    </style>
</head>
<body>
<div style="width:100%;text-align:center;margin:20px 0 30px 0;">
    <?php if (isset($_SESSION['user'])): ?>
        <a href="logout.php" style="color:#4e5053;text-decoration:none;font-size:1em;">logout</a> |
        <a href="change_password.php" style="color:#4e5053;text-decoration:none;font-size:1em;">change password</a>
        <?php if ($_SESSION['user'] === 'admin'): ?> |
            <a href="add_user.php" style="color:#4e5053;text-decoration:none;font-size:1em;">add user</a>
        <?php endif; ?>
    <?php else: ?>
        <a href="login.php" style="color:#4e5053;text-decoration:none;font-size:1em;">login</a>
    <?php endif; ?>
</div>

<div style="font-size: 14px !important; width: 555px; background-color: white; color: #4e5053; border: solid 2px #4e5053; margin-left: auto; margin-right: auto; padding: 1em; margin-bottom: 10px;">
    <h1 style="font-size: inherit !important; width: auto !important; background-color: transparent !important; color: inherit !important; border: none !important; margin: 0 !important; padding: 0 !important; margin-bottom: 0 !important;">Welcome<?php if (isset($_SESSION['user'])) echo ', ' . htmlspecialchars($_SESSION['user']); ?>!</h1>
    <p style="text-align: left; margin: 20px 0; color: #4e5053; font-size: 1em;">
        A minimalist <a href="https://en.wikipedia.org/wiki/Zettelkasten" target="_blank">zettelkasten</a> platform where users can create and manage their own personal notes. 
        Each blog supports text posts with optional image uploads, featuring a clean, imageboard-inspired design.
    </p>
</div>

<h2>Blogs</h2>
<?php if (empty($blogs)): ?>
    <p>No blogs available.</p>
<?php else: ?>
    <table class="blog-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Started</th>
                <th>Posts</th>
                <th>Images</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blogs as $blog): ?>
                <tr>
                    <td><a href="blog/<?= htmlspecialchars($blog['name']) ?>/index.php"><?= htmlspecialchars($blog['title']) ?></a></td>
                    <td><?= htmlspecialchars($blog['started']) ?></td>
                    <td><?= htmlspecialchars($blog['posts']) ?></td>
                    <td><?= htmlspecialchars($blog['images']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
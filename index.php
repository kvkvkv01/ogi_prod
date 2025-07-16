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
                'posts' => 0,
                'images' => 0,
                'replies' => 0
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
            
            // Count replies
            $replies_dir = $dir . '/replies';
            $reply_count = 0;
            if (is_dir($replies_dir)) {
                foreach (glob($replies_dir . '/*', GLOB_ONLYDIR) as $post_replies) {
                    $reply_count += count(glob($post_replies . '/*_reply.txt'));
                }
            }
            $blog_data['replies'] = $reply_count;
            $blog_data['activity_score'] = $blog_data['posts'] + $blog_data['images'] + $blog_data['replies'];
            $blogs[] = $blog_data;
        }
    }
}
// Sort blogs by activity_score descending
usort($blogs, function($a, $b) {
    return $b['activity_score'] <=> $a['activity_score'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Home</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
    <?php if (isset($_SESSION['user'])): ?>
      <?php 
      $user_css = __DIR__ . '/blog/' . $_SESSION['user'] . '/custom.css';
      if (file_exists($user_css)): ?>
        <link rel="stylesheet" type="text/css" href="blog/<?= htmlspecialchars($_SESSION['user']) ?>/custom.css?v=<?= filemtime($user_css) ?>" />
      <?php endif; ?>
    <?php endif; ?>
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
        | <a href="timeline.php" style="color:#4e5053;text-decoration:none;font-size:1em;">timeline</a>
        | <a href="chat.php" style="color:#4e5053;text-decoration:none;font-size:1em;">chat</a>
    <?php else: ?>
        <a href="login.php" style="color:#4e5053;text-decoration:none;font-size:1em;">login</a> |
        <a href="register.php" style="color:#4e5053;text-decoration:none;font-size:1em;">register</a>
        | <a href="timeline.php" style="color:#4e5053;text-decoration:none;font-size:1em;">timeline</a>
    <?php endif; ?>
</div>

<div style="font-size: 14px !important; width: 555px; background-color: white; color: #4e5053; border: solid 2px #4e5053; margin-left: auto; margin-right: auto; padding: 1em; margin-bottom: 10px;">
    <h1 style="font-size: inherit !important; width: auto !important; background-color: transparent !important; color: inherit !important; border: none !important; margin: 0 !important; padding: 0 !important; margin-bottom: 0 !important;">Welcome<?php if (isset($_SESSION['user'])) echo ', ' . htmlspecialchars($_SESSION['user']); ?>!</h1>
    <p style="text-align: left; margin: 20px 0; color: #4e5053; font-size: 1em;">
    Samefagging is a minimalist social-media-as-imageboard platform where users can create and manage their own personal threads. Each blog supports text posts with optional image uploads, featuring a clean, imageboard-inspired design. Due to safety concerns. For the moment, replies are text-only for anons.</p>
</div>

<h2>Blogs</h2>
<?php if (empty($blogs)): ?>
    <p>No blogs available.</p>
<?php else: ?>
    <table class="blog-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Posts</th>
                <th>Images</th>
                <th>Replies</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blogs as $blog): ?>
                <tr>
                    <td><a href="blog/<?= htmlspecialchars($blog['name']) ?>/index.php"><?= htmlspecialchars($blog['title']) ?></a></td>
                    <td><?= htmlspecialchars($blog['posts']) ?></td>
                    <td><?= htmlspecialchars($blog['images']) ?></td>
                    <td><?= htmlspecialchars($blog['replies']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>How to Use</h2>
<div style="font-size: 14px !important; width: 555px; background-color: white; color: #4e5053; border: solid 2px #4e5053; margin-left: auto; margin-right: auto; padding: 1em; margin-bottom: 10px;">
    <h3 style="margin-top: 0;">Text Formatting</h3>
    <ul style="text-align: left; margin: 10px 0; color: #4e5053; font-size: 1em;">
        <li><b>Bold:</b> <code>**text**</code> or <code>__text__</code></li>
        <li><i>Italic:</i> <code>*text*</code> or <code>_text_</code></li>
        <li><s>Strikethrough:</s> <code>~~text~~</code></li>
        <li><span style="font-size:1.2em;">Heading:</span> <code># text</code> (at line start)</li>
        <li>Spoiler: <code>||text||</code> (hover to reveal)</li>
    </ul>
    
    <h3>Code & Special Content</h3>
    <ul style="text-align: left; margin: 10px 0; color: #4e5053; font-size: 1em;">
        <li>Code blocks: <code>[code]your code here[/code]</code></li>
        <li>Shift_JIS art: <code>[jis]ｷﾀ━━━━━━(ﾟ∀ﾟ)━━━━━━!!!![/jis]</code></li>
        <li>LaTeX inline: <code>$70^{\circ}$</code></li>
        <li>LaTeX display: <code>$$\int_{-\infty}^{\infty} e^{-x^2} dx = \sqrt{\pi}$$</code></li>
    </ul>
    
    <h3>Quotes & References</h3>
    <ul style="text-align: left; margin: 10px 0; color: #4e5053; font-size: 1em;">
        <li>greentext: <code>&gt; quoted text</code> (appears in green)</li>
        <li>redtext: <code>&lt; referenced text</code> (appears in red)</li>
    </ul>

</div>

</body>
</html>
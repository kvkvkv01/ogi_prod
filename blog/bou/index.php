<?php
session_start();
$blog_dir = __DIR__;
$posts_dir = $blog_dir . '/posts';
if (!is_dir($posts_dir)) mkdir($posts_dir);

// Handle post deletion BEFORE any output
if (isset($_SESSION['user'], $_POST['delete_post'])) {
    $del_ts = preg_replace('/[^0-9]/', '', $_POST['delete_post']);
    $del_file = $posts_dir . "/{$del_ts}_post.txt";
    // Only allow if owner or admin
    $is_owner = ($_SESSION['user'] === basename($blog_dir));
    $is_admin = ($_SESSION['user'] === 'admin');
    if (($is_owner || $is_admin) && file_exists($del_file)) {
        // Remove image if present
        $lines = file($del_file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (stripos($line, 'Image:') === 0) {
                $img = trim(substr($line, 6));
                $img_path = $blog_dir . '/' . $img;
                if ($img && file_exists($img_path)) @unlink($img_path);
            }
        }
        unlink($del_file);
        // Refresh to avoid resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Load header (title/description)
$header_file = $blog_dir . '/header.txt';
$blog_title = 'Blog';
$blog_desc = '';
if (file_exists($header_file)) {
    $lines = file($header_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (stripos($line, 'Title:') === 0) {
            $blog_title = trim(substr($line, 6));
        } elseif (stripos($line, 'Description:') === 0) {
            $blog_desc = trim(substr($line, 12));
        }
    }
}

// Load posts (timestamp_post.txt)
$posts = [];
foreach (glob($posts_dir . '/*_post.txt') as $file) {
    $timestamp = basename($file, '_post.txt');
    $content = file_get_contents($file);
    $posts[$timestamp] = $content;
}
// Sort by timestamp descending
krsort($posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($blog_title) ?> ~ <?= htmlspecialchars($blog_desc) ?></title>
    <link rel="stylesheet" type="text/css" href="../../css.css"/>
    <?php if (file_exists($blog_dir . '/custom.css')): ?>
      <link rel="stylesheet" type="text/css" href="custom.css?v=<?= filemtime($blog_dir . '/custom.css') ?>" />
    <?php endif; ?>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']]
            },
            svg: {
                fontCache: 'global'
            }
        };
    </script>
</head>
<body>
<?php
$is_logged = isset($_SESSION['user']);
$is_owner = $is_logged && ($_SESSION['user'] === basename($blog_dir));
$is_admin = $is_logged && ($_SESSION['user'] === 'admin');
?>
<div style="width:100%;text-align:center;margin:20px 0 30px 0;">
  <a href="/" id="home-link" style="color:#4e5053;text-decoration:none;font-size:1em;">home</a>
  <?php if ($is_owner || $is_admin): ?> |
    <a href="edit_blog.php" id="edit-link" style="color:#4e5053;text-decoration:none;font-size:1em;">edit</a>
  <?php endif; ?>
</div>
<h1><?= htmlspecialchars($blog_title) ?><?php if ($blog_desc): ?> ~ <?= htmlspecialchars($blog_desc) ?><?php endif; ?></h1>
<?php if ($is_owner || $is_admin): ?>
    <form method="post" action="create_post.php" enctype="multipart/form-data">
        <h3>NEW POST</h3>
        <label>Title: <input type="text" name="title" required></label><br>
        <label>Content:<br><textarea name="content" rows="4" cols="50" required></textarea></label><br>
        <label>Image: <input type="file" name="image"></label><br>
        <button type="submit">Post</button>
    </form>
<?php endif; ?>
<h2>POSTS</h2>
<?php if (empty($posts)): ?>
    <p>No posts yet.</p>
<?php else: ?>
<?php
// Imageboard-style formatting function (declare only once)
if (!function_exists('format_post')) {
  function format_post($text) {
    // Escape HTML
    $text = htmlspecialchars($text);
    
    // Split out [jis] blocks first to preserve them exactly
    $parts = preg_split('/(\[jis\].*?\[\/jis\])/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    for ($i = 0; $i < count($parts); $i++) {
      if ($i % 2 == 1) { // [jis] content
        $jis_content = $parts[$i];
        // Remove the [jis] tags and wrap in pre
        $jis_content = preg_replace('/^\[jis\](.*?)\[\/jis\]$/is', '$1', $jis_content);
        $out .= '<pre style="font-family: \'MS Pgothic\', IPAMonaPGothic, Monapo, Mona, serif; font-size: 12px; line-height: 1.2; margin: 0; padding: 0; background: transparent; border: none; white-space: pre; display: inline-block; vertical-align: top;">' . $jis_content . '</pre>';
      } else { // normal text
        // Split out code blocks
        $code_parts = preg_split('/(```|\[code\])(.*?)(```|\[\/code\])/is', $parts[$i], -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($j = 0; $j < count($code_parts); $j++) {
          if ($j % 4 == 2) { // code content
            $code = trim($code_parts[$j], "\r\n");
            $out .= '<pre class="post_code">' . $code . '</pre>';
          } elseif ($j % 4 == 0) { // normal text
            $lines = explode("\n", $code_parts[$j]);
            foreach ($lines as $k => $line) {
              if (preg_match('/^&gt;.+/', $line)) {
                $line = '<span style="color:#789922">' . $line . '</span>';
              } elseif (preg_match('/^&lt;.+/', $line)) {
                $line = '<span style="color:#c22">' . $line . '</span>';
              }
              $out .= $line;
              if ($k < count($lines) - 1) $out .= "<br>";
            }
          }
        }
      }
    }
    return $out;
  }
}

foreach ($posts as $ts => $content):
    // Parse post file
    $lines = explode("\n", $content);
    $title = $img = $body = '';
    $in_content = false;
    foreach ($lines as $line) {
        if (stripos($line, 'Title:') === 0) {
            $title = trim(substr($line, 6));
        } elseif (stripos($line, 'Image:') === 0) {
            $img = trim(substr($line, 6));
        } elseif (stripos($line, 'Content:') === 0) {
            $in_content = true;
        } elseif ($in_content) {
            $body .= $line . "\n";
        }
    }
    $body = trim($body);

    // Date/time formatting
    $dt = new DateTime("@".$ts);
    $dt->setTimezone(new DateTimeZone("Asia/Tokyo"));
    $weekday_jp = ['日','月','火','水','木','金','土'];
    $w = (int)$dt->format('w');
    $date_str = $dt->format('Y/m/d') . '(' . $weekday_jp[$w] . ') ' . $dt->format('H:i');

    // File info
    $file_info = '';
    if ($img && file_exists($blog_dir . '/' . $img)) {
        $fsize = filesize($blog_dir . '/' . $img);
        $mb = round($fsize / 1048576, 2);
        // Fallback for mime type if mime_content_type is not available
        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
        $mime_map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webm' => 'video/webm', 'mp4' => 'video/mp4',
        ];
        $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';
        $file_info = basename($img) . " ({$mb}MB, $mime)";
    }
    // Check if user can delete
    $can_delete = false;
    if (isset($_SESSION['user'])) {
        $is_owner = ($_SESSION['user'] === basename($blog_dir));
        $is_admin = ($_SESSION['user'] === 'admin');
        if ($is_owner || $is_admin) $can_delete = true;
    }
?>
<div id='post'>
  <div class="post_meta">
    <?= htmlspecialchars($date_str) ?> No.<?= htmlspecialchars($ts) ?><br>
    <?php if ($img): ?>
    file: <a href="<?= htmlspecialchars($img) ?>"><?= htmlspecialchars(basename($img)) ?></a>
    (<?= htmlspecialchars($mb) ?>MB, <?= htmlspecialchars($mime) ?>)<br>
    <?php endif; ?>
    <?php if ($can_delete): ?>
      <form method="post" style="display:contents" onsubmit="return confirm('Delete this post?');">
        <input type="hidden" name="delete_post" value="<?= htmlspecialchars($ts) ?>">
        <br><button type="submit">Delete</button>
      </form>
    <?php endif; ?>
  </div>
    <div class="post_content">
    <?php if ($img): ?>
    <img class="thumbnail float-image" src="<?= htmlspecialchars($img) ?>" />
    <?php endif; ?>
    <b><?= htmlspecialchars($title) ?></b><br>
    <p><?= format_post($body) ?></p>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>

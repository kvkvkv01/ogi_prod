<?php
session_start();
$blog_dir = __DIR__;
$posts_dir = $blog_dir . '/posts';
if (!is_dir($posts_dir)) mkdir($posts_dir);

// Pagination settings
$posts_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $posts_per_page;

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
$post_sort_keys = [];
foreach (glob($posts_dir . '/*_post.txt') as $file) {
    $timestamp = basename($file, '_post.txt');
    $content = file_get_contents($file);
    // Find most recent reply timestamp
    $replies_dir = $blog_dir . '/replies/' . $timestamp;
    $latest_reply = 0;
    if (is_dir($replies_dir)) {
        $reply_files = glob($replies_dir . '/*_reply.txt');
        foreach ($reply_files as $rf) {
            $rf_time = (int)basename($rf, '_reply.txt');
            if ($rf_time > $latest_reply) $latest_reply = $rf_time;
        }
    }
    $sort_key = $latest_reply > 0 ? $latest_reply : (int)$timestamp;
    $posts[$timestamp] = $content;
    $post_sort_keys[$timestamp] = $sort_key;
}
// Sort by most recent reply (or post time) descending
arsort($post_sort_keys);
$posts = array_replace(array_flip(array_keys($post_sort_keys)), $posts);

// Calculate pagination
$total_posts = count($posts);
$total_pages = ceil($total_posts / $posts_per_page);
$current_page = min($current_page, $total_pages);
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $posts_per_page;

// Get posts for current page
$current_posts = array_slice($posts, $offset, $posts_per_page, true);
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
    
    // Split out [jis], [code], and [url] blocks first to preserve them exactly
    $parts = preg_split('/(\[jis\].*?\[\/jis\]|\[code\].*?\[\/code\]|\[url\].*?\[\/url\])/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    for ($i = 0; $i < count($parts); $i++) {
      if ($i % 2 == 1) { // [jis], [code], or [url] content
        $block_content = $parts[$i];
        
        // Handle [jis] blocks
        if (preg_match('/^\[jis\](.*?)\[\/jis\]$/is', $block_content, $matches)) {
          $jis_content = $matches[1];
          $out .= '<pre style="font-family: \'MS Pgothic\', IPAMonaPGothic, Monapo, Mona, serif; font-size: 12px; line-height: 1.2; margin: 0; padding: 0; background: transparent; border: none; white-space: pre; display: inline-block; vertical-align: top;">' . $jis_content . '</pre>';
        }
        // Handle [code] blocks
        elseif (preg_match('/^\[code\](.*?)\[\/code\]$/is', $block_content, $matches)) {
          $code = trim($matches[1], "\r\n");
          $out .= '<pre class="post_code">' . $code . '</pre>';
        }
        // Handle [url] blocks
        elseif (preg_match('/^\[url\](.*?)\[\/url\]$/is', $block_content, $matches)) {
          $url_content = $matches[1];
          // Check if it's a URL or text with URL
          if (preg_match('/^https?:\/\//i', $url_content)) {
            // It's a URL, use it as both href and text
            $out .= '<a href="' . $url_content . '" target="_blank" rel="noopener noreferrer">' . $url_content . '</a>';
          } else {
            // It's text, look for URL in the content
            if (preg_match('/(https?:\/\/[^\s]+)/i', $url_content, $url_matches)) {
              $url = $url_matches[1];
              $text = str_replace($url, '', $url_content);
              $text = trim($text);
              if (empty($text)) $text = $url;
              $out .= '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
            } else {
              // No URL found, just display the text
              $out .= $url_content;
            }
          }
        }
      } else { // normal text
        // Apply markdown formatting to normal text
        $text_part = format_markdown($parts[$i]);
        
        // Split out code blocks (```)
        $code_parts = preg_split('/(```)(.*?)(```)/is', $text_part, -1, PREG_SPLIT_DELIM_CAPTURE);
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
              $out .= nl2br($line);
            }
          }
        }
      }
    }
    return $out;
  }
  // Markdown formatting function
  function format_markdown($text) {
    // Spoiler: ||text||
    $text = preg_replace('/\|\|(.+?)\|\|/s', '<span class="spoiler">$1</span>', $text);
    // Bold: **text** or __text__
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text);
    $text = preg_replace('/__(.+?)__/s', '<b>$1</b>', $text);
    // Italic: *text* or _text_
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<i>$1</i>', $text);
    $text = preg_replace('/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<i>$1</i>', $text);
    // Strikethrough: ~~text~~
    $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text);
    // Heading: # text (at line start)
    $text = preg_replace('/^# (.+)$/m', '<span>$1</span>', $text);
    return $text;
  }
}

foreach ($current_posts as $ts => $content):
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

    // --- REPLIES LOGIC ---
    $replies_dir = $blog_dir . '/replies/' . $ts;
    if (!is_dir($replies_dir)) mkdir($replies_dir, 0777, true);
    $reply_files = glob($replies_dir . '/*_reply.txt');
    usort($reply_files, function($a, $b) { return filemtime($a) <=> filemtime($b); });
    $recent_replies = array_slice($reply_files, -3);
    $more_replies = count($reply_files) > 3;

    // Captcha for reply
    if (!isset($_SESSION['reply_captcha_'.$ts])) {
        $a = rand(1, 9); $b = rand(1, 9);
        $_SESSION['reply_captcha_'.$ts] = [$a, $b, $a+$b];
    }
    $captcha = $_SESSION['reply_captcha_'.$ts];

    // Handle reply deletion
    $reply_error = '';
    if (isset($_POST['delete_reply']) && $_POST['delete_reply_ts'] == $ts) {
        $del_file = $replies_dir . '/' . basename($_POST['delete_reply']);
        $is_owner = (isset($_SESSION['user']) && $_SESSION['user'] === basename($blog_dir));
        $is_admin = (isset($_SESSION['user']) && $_SESSION['user'] === 'admin');
        if (($is_owner || $is_admin) && file_exists($del_file)) {
            unlink($del_file);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Handle reply submission
    if (isset($_POST['reply_to']) && $_POST['reply_to'] == $ts && isset($_POST['reply_content'])) {
        $reply_content = trim($_POST['reply_content']);
        $reply_captcha = $_POST['reply_captcha'] ?? '';
        $reply_captcha_answer = $_POST['reply_captcha_answer'] ?? '';
        // Rate limit: 30s per user/IP/thread
        $rate_key = 'last_reply_'.$ts;
        $now = time();
        $last = $_SESSION[$rate_key] ?? 0;
        if ($now - $last < 30) {
            $reply_error = 'You must wait 30 seconds between replies.';
        } elseif (strlen($reply_content) > 2000) {
            $reply_error = 'Reply too long (max 2000 characters).';
        } elseif ($reply_captcha === '' || $reply_captcha_answer === '' || intval($reply_captcha) !== intval($reply_captcha_answer)) {
            $reply_error = 'Captcha incorrect.';
            unset($_SESSION['reply_captcha_'.$ts]);
        } elseif ($reply_content !== '') {
            $reply_user = isset($_SESSION['user']) ? $_SESSION['user'] : 'VIPPER';
            $reply_time = time();
            $reply_file = $replies_dir . '/' . $reply_time . '_reply.txt';
            
            // Handle image upload for logged-in users
            $image_path = '';
            if (isset($_SESSION['user']) && isset($_FILES['reply_image']) && $_FILES['reply_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = $blog_dir . '/uploads';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file = $_FILES['reply_image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webm', 'mp4'];
                
                if (in_array($ext, $allowed_exts) && $file['size'] <= 10485760) { // 10MB limit
                    $image_name = $reply_time . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $image_path = 'uploads/' . $image_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $blog_dir . '/' . $image_path)) {
                        // Image uploaded successfully
                    } else {
                        $image_path = '';
                    }
                }
            }
            
            $reply_text = "User: $reply_user\nTime: $reply_time\nContent: " . str_replace("\n", " ", $reply_content) . "\n";
            if ($image_path) {
                $reply_text .= "Image: $image_path\n";
            }
            
            file_put_contents($reply_file, $reply_text);
            $_SESSION[$rate_key] = $now;
            unset($_SESSION['reply_captcha_'.$ts]);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
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
    <!-- REPLIES SECTION -->
    <div class="replies_section" style="width:100%;margin:1em 0 0 0;padding:0;">
      <br>
      <?php foreach (
        $recent_replies as $rf):
        $reply = file($rf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $reply_user = $reply_time = $reply_content = $reply_image = '';
        foreach ($reply as $line) {
          if (stripos($line, 'User:') === 0) $reply_user = trim(substr($line, 5));
          elseif (stripos($line, 'Time:') === 0) $reply_time = trim(substr($line, 5));
          elseif (stripos($line, 'Content:') === 0) $reply_content = trim(substr($line, 8));
          elseif (stripos($line, 'Image:') === 0) $reply_image = trim(substr($line, 6));
        }
        $dt = $reply_time ? date('Y/m/d H:i', $reply_time) : '';
        
        // File info for reply image
        $file_info = '';
        if ($reply_image && file_exists($blog_dir . '/' . $reply_image)) {
            $fsize = filesize($blog_dir . '/' . $reply_image);
            $mb = round($fsize / 1048576, 2);
            $ext = strtolower(pathinfo($reply_image, PATHINFO_EXTENSION));
            $mime_map = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webm' => 'video/webm', 'mp4' => 'video/mp4',
            ];
            $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';
            $file_info = basename($reply_image) . " ({$mb}MB, $mime)";
        }
      ?>
        <div style="border-left:2px solid #ccc;padding-left:1em;margin-bottom:0.5em;max-width:100%;word-break:break-word;">
          <div class="post_meta" style="font-size:0.9em;">
            <span style="color:#789922;">[<?= htmlspecialchars($reply_user) ?>]</span>
            <?= htmlspecialchars($dt) ?><br>
            <?php if ($reply_image): ?>
            file: <a href="<?= htmlspecialchars($reply_image) ?>"><?= htmlspecialchars(basename($reply_image)) ?></a>
            (<?= htmlspecialchars($mb) ?>MB, <?= htmlspecialchars($mime) ?>)<br>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && ($_SESSION['user'] === basename($blog_dir) || $_SESSION['user'] === 'admin')): ?>
              <form method="post" style="display:inline;border: unset;background-color: unset;padding: 0px;padding-top: 5px;">
                <input type="hidden" name="delete_reply" value="<?= htmlspecialchars(basename($rf)) ?>">
                <input type="hidden" name="delete_reply_ts" value="<?= htmlspecialchars($ts) ?>">
                <button type="submit" onclick="return confirm('Delete this reply?');">Delete</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="post_content">
            <?php if ($reply_image && file_exists($blog_dir . '/' . $reply_image)): ?>
              <img class="thumbnail float-image" src="<?= htmlspecialchars($reply_image) ?>" />
            <?php endif; ?>
            <?= format_post($reply_content) ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($more_replies): ?>
        <a href="thread.php?post=<?= urlencode($ts) ?>">open thread (<?= count($reply_files) ?> replies)</a>
      <?php endif; ?>
      <!-- Reply button and form -->
      <button type="button" onclick="document.getElementById('replyform_<?= $ts ?>').style.display='block';this.style.display='none';" style="margin-top:0.5em;">Reply</button>
      <form id="replyform_<?= $ts ?>" method="post" enctype="multipart/form-data" style="display:none;margin-top:0.5em;width:80%;">
        <?php if (!empty($reply_error)): ?><div style="color:red;"> <?= htmlspecialchars($reply_error) ?> </div><?php endif; ?>
        <input type="hidden" name="reply_to" value="<?= htmlspecialchars($ts) ?>">
        <textarea name="reply_content" rows="2" style="width:98%;max-width:100%;box-sizing:border-box;resize:vertical;" maxlength="2000" required placeholder="Write a reply... (max 2000 chars)"></textarea><br>
        <?php if (isset($_SESSION['user'])): ?>
          <label>Image: <input type="file" name="reply_image" accept="image/*,video/*"></label><br>
        <?php endif; ?>
        <label>Captcha: <b><?= htmlspecialchars($captcha[0]) ?> + <?= htmlspecialchars($captcha[1]) ?> = ?</b>
          <input type="text" name="reply_captcha" required style="width:40px;">
        </label>
        <input type="hidden" name="reply_captcha_answer" value="<?= htmlspecialchars($captcha[2]) ?>">
        <button type="submit" style="margin-top:0.2em;">Reply</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="text-align: center; margin: 30px 0; font-family: monospace;">
  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <?php if ($i == $current_page): ?>
      <span style="background: #4e5053; color: white; padding: 5px 8px; margin: 0 2px;">[<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>]</span>
    <?php else: ?>
      <a href="?page=<?= $i ?>" style="background: #f0f0f0; color: #4e5053; padding: 5px 8px; margin: 0 2px; text-decoration: none;">[<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>]</a>
    <?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>
<style>
.spoiler {
  background: #222;
  color: #222;
  border-radius: 2px;
  padding: 0 2px;
  cursor: pointer;
}
.spoiler:hover, .spoiler:active {
  color: #fff;
  transition: color 0.2s;
}
</style>
</body>
</html>

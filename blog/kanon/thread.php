<?php
session_start();
$blog_dir = __DIR__;
$posts_dir = $blog_dir . '/posts';

// Get post ID from query
if (!isset($_GET['post']) || !preg_match('/^[0-9]+$/', $_GET['post'])) {
    die('Invalid post ID.');
}
$ts = $_GET['post'];
$post_file = $posts_dir . "/{$ts}_post.txt";
if (!file_exists($post_file)) {
    die('Post not found.');
}
$content = file_get_contents($post_file);
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
    $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
    $mime_map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webm' => 'video/webm', 'mp4' => 'video/mp4',
    ];
    $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';
    $file_info = basename($img) . " ({$mb}MB, $mime)";
}
// Replies logic
$replies_dir = $blog_dir . '/replies/' . $ts;
if (!is_dir($replies_dir)) mkdir($replies_dir, 0777, true);
$reply_files = glob($replies_dir . '/*_reply.txt');
usort($reply_files, function($a, $b) { return filemtime($a) <=> filemtime($b); });
// Handle reply submission
if (isset($_POST['reply_to']) && $_POST['reply_to'] == $ts && isset($_POST['reply_content'])) {
    $reply_content = trim($_POST['reply_content']);
    if ($reply_content !== '') {
        $reply_user = isset($_SESSION['user']) ? $_SESSION['user'] : 'VIPPER';
        $reply_time = time();
        $reply_file = $replies_dir . '/' . $reply_time . '_reply.txt';
        $reply_text = "User: $reply_user\nTime: $reply_time\nContent: " . str_replace("\n", " ", $reply_content) . "\n";
        file_put_contents($reply_file, $reply_text);
        // Refresh to avoid resubmission
        header("Location: thread.php?post=" . urlencode($ts));
        exit;
    }
}
// Formatting function (reuse from index.php)
if (!function_exists('format_post')) {
  function format_post($text) {
    $text = htmlspecialchars($text);
    $parts = preg_split('/(\[jis\].*?\[\/jis\])/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    for ($i = 0; $i < count($parts); $i++) {
      if ($i % 2 == 1) {
        $jis_content = $parts[$i];
        $jis_content = preg_replace('/^\[jis\](.*?)\[\/jis\]$/is', '$1', $jis_content);
        $out .= '<pre style="font-family: \'MS Pgothic\', IPAMonaPGothic, Monapo, Mona, serif; font-size: 12px; line-height: 1.2; margin: 0; padding: 0; background: transparent; border: none; white-space: pre; display: inline-block; vertical-align: top;">' . $jis_content . '</pre>';
      } else {
        $code_parts = preg_split('/(```|\[code\])(.*?)(```|\[\/code\])/is', $parts[$i], -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($j = 0; $j < count($code_parts); $j++) {
          if ($j % 4 == 2) {
            $code = trim($code_parts[$j], "\r\n");
            $out .= '<pre class="post_code">' . $code . '</pre>';
          } elseif ($j % 4 == 0) {
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
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title) ?> ~ Thread</title>
    <link rel="stylesheet" type="text/css" href="../../css.css"/>
    <?php if (file_exists($blog_dir . '/custom.css')): ?>
      <link rel="stylesheet" type="text/css" href="custom.css?v=<?= filemtime($blog_dir . '/custom.css') ?>" />
    <?php endif; ?>
</head>
<body>
<div style="width:100%;text-align:center;margin:20px 0 30px 0;">
  <a href="index.php" id="home-link" style="color:#4e5053;text-decoration:none;font-size:1em;">home</a>
</div>
<div id='post'>
  <div class="post_meta">
    <?= htmlspecialchars($date_str) ?> No.<?= htmlspecialchars($ts) ?><br>
    <?php if ($img): ?>
    file: <a href="<?= htmlspecialchars($img) ?>"><?= htmlspecialchars(basename($img)) ?></a>
    (<?= htmlspecialchars($mb) ?>MB, <?= htmlspecialchars($mime) ?>)<br>
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
    <b>Replies:</b><br>
    <?php foreach ($reply_files as $rf):
      $reply = file($rf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $reply_user = $reply_time = $reply_content = '';
      foreach ($reply as $line) {
        if (stripos($line, 'User:') === 0) $reply_user = trim(substr($line, 5));
        elseif (stripos($line, 'Time:') === 0) $reply_time = trim(substr($line, 5));
        elseif (stripos($line, 'Content:') === 0) $reply_content = trim(substr($line, 8));
      }
      $dt = $reply_time ? date('Y/m/d H:i', $reply_time) : '';
    ?>
      <div style="border-left:2px solid #ccc;padding-left:1em;margin-bottom:0.5em;max-width:100%;word-break:break-word;">
        <span style="color:#789922;">[<?= htmlspecialchars($reply_user) ?>]</span>
        <span style="color:#aaa;"> <?= htmlspecialchars($dt) ?></span><br>
        <?= htmlspecialchars($reply_content) ?>
      </div>
    <?php endforeach; ?>
    <!-- Reply button and form -->
    <button type="button" onclick="document.getElementById('replyform_<?= $ts ?>').style.display='block';this.style.display='none';" style="margin-top:0.5em;">Reply</button>
    <form id="replyform_<?= $ts ?>" method="post" style="display:none;margin-top:0.5em;width:80%;">
      <input type="hidden" name="reply_to" value="<?= htmlspecialchars($ts) ?>">
      <textarea name="reply_content" rows="2" style="width:98%;max-width:100%;box-sizing:border-box;resize:vertical;" required placeholder="Write a reply..."></textarea><br>
      <button type="submit" style="margin-top:0.2em;">Reply</button>
    </form>
  </div>
</div>
</body>
</html> 
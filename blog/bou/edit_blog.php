<?php
session_start();
$blog_dir = __DIR__;
$header_file = $blog_dir . '/header.txt';
$css_file = $blog_dir . '/custom.css';

// Only blog owner or admin can edit
if (!isset($_SESSION['user']) || ($_SESSION['user'] !== basename($blog_dir) && $_SESSION['user'] !== 'admin')) {
    http_response_code(403);
    echo "<p>Forbidden</p>";
    exit;
}

// Load current title/desc
$blog_title = '';
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

// Default CSS values (same as previous UI)
$defaults = [
    'body_bgcolor' => '#ffffff',
    'body_bgimg' => '',
    'body_bgsize' => 'auto',
    'body_bgpos' => 'left top',
    'body_bgrepeat' => 'repeat',
    'body_font' => 'Arial, sans-serif',
    'body_color' => '#b3b8c3',
    'block_bgcolor' => '#ffffff',
    'block_color' => '#4e5053',
    'block_border_style' => 'solid',
    'block_border_width' => '2',
    'block_border_color' => '#4e5053',
];
$values = $defaults;
if (file_exists($css_file)) {
    $css = file_get_contents($css_file);
    if (preg_match('/body\s*{([^}]*)}/i', $css, $m)) {
        foreach (explode(';', $m[1]) as $decl) {
            if (strpos($decl, ':') !== false) {
                list($k, $v) = explode(':', $decl, 2);
                $k = trim($k); $v = trim($v);
                if ($k === 'background-color') $values['body_bgcolor'] = $v;
                if ($k === 'background-image') $values['body_bgimg'] = trim(preg_replace('/url\((.*?)\)/', '$1', $v), "'\"");
                if ($k === 'background-size') $values['body_bgsize'] = $v;
                if ($k === 'background-position') $values['body_bgpos'] = $v;
                if ($k === 'background-repeat') $values['body_bgrepeat'] = $v;
                if ($k === 'font-family') $values['body_font'] = $v;
                if ($k === 'color') $values['body_color'] = $v;
            }
        }
    }
    if (preg_match('/h1, h2, #post, form\s*{([^}]*)}/i', $css, $m)) {
        foreach (explode(';', $m[1]) as $decl) {
            if (strpos($decl, ':') !== false) {
                list($k, $v) = explode(':', $decl, 2);
                $k = trim($k); $v = trim($v);
                if ($k === 'background-color') $values['block_bgcolor'] = $v;
                if ($k === 'color') $values['block_color'] = $v;
                if ($k === 'border') {
                    if (preg_match('/(solid|dashed|dotted|double|groove|ridge|inset|outset)\s*([0-9]+)px\s*(#[0-9a-fA-F]{3,6})/', $v, $bm)) {
                        $values['block_border_style'] = $bm[1];
                        $values['block_border_width'] = $bm[2];
                        $values['block_border_color'] = $bm[3];
                    }
                }
            }
        }
    }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save title/desc
    $new_title = trim($_POST['blog_title'] ?? '');
    $new_desc = trim($_POST['blog_desc'] ?? '');
    $header = "Title: $new_title\nDescription: $new_desc\n";
    file_put_contents($header_file, $header);
    $blog_title = $new_title;
    $blog_desc = $new_desc;
    // Sanitize and assign CSS
    $body_bgcolor = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['body_bgcolor']) ? $_POST['body_bgcolor'] : $defaults['body_bgcolor'];
    $body_bgimg = filter_var(trim($_POST['body_bgimg']), FILTER_VALIDATE_URL) ? trim($_POST['body_bgimg']) : '';
    $body_bgsize = in_array($_POST['body_bgsize'], ['auto','cover','contain']) ? $_POST['body_bgsize'] : 'auto';
    $body_bgpos = in_array($_POST['body_bgpos'], ['left top','center center','right bottom']) ? $_POST['body_bgpos'] : 'left top';
    $body_bgrepeat = in_array($_POST['body_bgrepeat'], ['repeat','no-repeat','repeat-x','repeat-y']) ? $_POST['body_bgrepeat'] : 'repeat';
    $body_font = in_array($_POST['body_font'], ['Arial, sans-serif','Times New Roman, serif','Courier New, monospace','Georgia, serif','Tahoma, sans-serif','Verdana, sans-serif']) ? $_POST['body_font'] : $defaults['body_font'];
    $body_color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['body_color']) ? $_POST['body_color'] : $defaults['body_color'];
    $block_bgcolor = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['block_bgcolor']) ? $_POST['block_bgcolor'] : $defaults['block_bgcolor'];
    $block_color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['block_color']) ? $_POST['block_color'] : $defaults['block_color'];
    $block_border_style = in_array($_POST['block_border_style'], ['solid','dashed','dotted','double','groove','ridge','inset','outset']) ? $_POST['block_border_style'] : $defaults['block_border_style'];
    $block_border_width = (is_numeric($_POST['block_border_width']) && $_POST['block_border_width'] > 0 && $_POST['block_border_width'] <= 10) ? $_POST['block_border_width'] : $defaults['block_border_width'];
    $block_border_color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['block_border_color']) ? $_POST['block_border_color'] : $defaults['block_border_color'];
    $css = "body {\n" .
        "  background-color: $body_bgcolor;\n" .
        ($body_bgimg ? "  background-image: url('$body_bgimg');\n" : "  background-image: none;\n") .
        "  background-size: $body_bgsize;\n" .
        "  background-position: $body_bgpos;\n" .
        "  background-repeat: $body_bgrepeat;\n" .
        "  color: $body_color;\n" .
        "  font-family: $body_font;\n" .
        "  padding: 20px;\n" .
        "}\n\n" .
        "h1, h2, #post, form {\n" .
        "  font-size: 14px !important;\n" .
        "  width: 555px;\n" .
        "  background-color: $block_bgcolor;\n" .
        "  color: $block_color;\n" .
        "  border: $block_border_style {$block_border_width}px $block_border_color;\n" .
        "  margin-left: auto;\n" .
        "  margin-right: auto;\n" .
        "  padding: 1em;\n" .
        "}\n";
    file_put_contents($css_file, $css);
    $msg = 'Blog settings saved!';
    $values = compact('body_bgcolor','body_bgimg','body_bgsize','body_bgpos','body_bgrepeat','body_font','body_color','block_bgcolor','block_color','block_border_style','block_border_width','block_border_color');
}
function h($s) { return htmlspecialchars($s, ENT_QUOTES); }
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Blog Settings</title>
    <link rel="stylesheet" type="text/css" href="../../css.css"/>
    <style>label { display:block; margin:8px 0 2px 0; } .desc { color:#888; font-size:0.95em; margin-left:4px; }</style>
</head>
<body>
<?php
$is_logged = isset($_SESSION['user']);
$is_owner = $is_logged && ($_SESSION['user'] === basename($blog_dir));
$is_admin = $is_logged && ($_SESSION['user'] === 'admin');
?>
<div style="width:100%;text-align:center;margin:20px 0 30px 0;">
  <a href="index.php" id="home-link" style="color:#4e5053;text-decoration:none;font-size:1em;">home</a>
  <?php if ($is_owner || $is_admin): ?> |
    <span id="edit-link" style="color:#4e5053;text-decoration:none;cursor:default;font-weight:bold;">edit</span>
  <?php endif; ?>
</div>
<h2>Edit Blog Settings</h2>
<?php if ($msg): ?><div style="color:green;\"><?= h($msg) ?></div><?php endif; ?>
<form method="post">
  <fieldset><legend>Blog Info</legend>
    <label>Title: <input type="text" name="blog_title" value="<?= h($blog_title) ?>" maxlength="100" required title="The title of your blog."></label>
    <span class="desc" title="The main title of your blog.">The main title of your blog.</span>
    <label>Description: <input type="text" name="blog_desc" value="<?= h($blog_desc) ?>" maxlength="200" title="A short description shown next to the title."></label>
    <span class="desc" title="A short description shown next to the title.">A short description shown next to the title.</span>
  </fieldset>
  <fieldset><legend>Body (Page Background)</legend>
    <label>Background Color: <input type="color" name="body_bgcolor" value="<?= h($values['body_bgcolor']) ?>" title="Background color for the whole page."></label>
    <span class="desc" title="The background color of the page.">The background color of the page.</span>
    <label>Background Image URL: <input type="url" name="body_bgimg" value="<?= h($values['body_bgimg']) ?>" style="width:300px" title="URL for a background image."></label>
    <span class="desc" title="A direct link to an image to use as the background. Leave blank for no image.">Direct link to an image for the background. Leave blank for none.</span>
    <label>Background Size:
      <select name="body_bgsize" title="How the background image is sized.">
        <option value="auto" <?= $values['body_bgsize']==='auto'?'selected':'' ?>>auto</option>
        <option value="cover" <?= $values['body_bgsize']==='cover'?'selected':'' ?>>cover</option>
        <option value="contain" <?= $values['body_bgsize']==='contain'?'selected':'' ?>>contain</option>
      </select>
    </label>
    <span class="desc" title="cover: fill, contain: fit, auto: original size.">cover: fill, contain: fit, auto: original size.</span>
    <label>Background Position:
      <select name="body_bgpos" title="Where the background image is placed.">
        <option value="left top" <?= $values['body_bgpos']==='left top'?'selected':'' ?>>left top</option>
        <option value="center center" <?= $values['body_bgpos']==='center center'?'selected':'' ?>>center center</option>
        <option value="right bottom" <?= $values['body_bgpos']==='right bottom'?'selected':'' ?>>right bottom</option>
      </select>
    </label>
    <span class="desc" title="Position of the background image.">Position of the background image.</span>
    <label>Background Repeat:
      <select name="body_bgrepeat" title="How the background image repeats.">
        <option value="repeat" <?= $values['body_bgrepeat']==='repeat'?'selected':'' ?>>repeat</option>
        <option value="no-repeat" <?= $values['body_bgrepeat']==='no-repeat'?'selected':'' ?>>no-repeat</option>
        <option value="repeat-x" <?= $values['body_bgrepeat']==='repeat-x'?'selected':'' ?>>repeat-x</option>
        <option value="repeat-y" <?= $values['body_bgrepeat']==='repeat-y'?'selected':'' ?>>repeat-y</option>
      </select>
    </label>
    <span class="desc" title="How the background image repeats.">How the background image repeats.</span>
    <label>Font Family:
      <select name="body_font" title="Font for the whole page.">
        <option value="Arial, sans-serif" <?= $values['body_font']==='Arial, sans-serif'?'selected':'' ?>>Arial</option>
        <option value="Times New Roman, serif" <?= $values['body_font']==='Times New Roman, serif'?'selected':'' ?>>Times New Roman</option>
        <option value="Courier New, monospace" <?= $values['body_font']==='Courier New, monospace'?'selected':'' ?>>Courier New</option>
        <option value="Georgia, serif" <?= $values['body_font']==='Georgia, serif'?'selected':'' ?>>Georgia</option>
        <option value="Tahoma, sans-serif" <?= $values['body_font']==='Tahoma, sans-serif'?'selected':'' ?>>Tahoma</option>
        <option value="Verdana, sans-serif" <?= $values['body_font']==='Verdana, sans-serif'?'selected':'' ?>>Verdana</option>
      </select>
    </label>
    <span class="desc" title="Font for the whole page.">Font for the whole page.</span>
    <label>Text Color: <input type="color" name="body_color" value="<?= h($values['body_color']) ?>" title="Text color for the whole page."></label>
    <span class="desc" title="Text color for the whole page.">Text color for the whole page.</span>
  </fieldset>
  <fieldset><legend>Posts/Blocks (h1, h2, #post, form)</legend>
    <label>Background Color: <input type="color" name="block_bgcolor" value="<?= h($values['block_bgcolor']) ?>" title="Background color for posts and forms."></label>
    <span class="desc" title="Background color for posts and forms.">Background color for posts and forms.</span>
    <label>Text Color: <input type="color" name="block_color" value="<?= h($values['block_color']) ?>" title="Text color for posts and forms."></label>
    <span class="desc" title="Text color for posts and forms.">Text color for posts and forms.</span>
    <label>Border Style:
      <select name="block_border_style" title="Border style for posts and forms.">
        <option value="solid" <?= $values['block_border_style']==='solid'?'selected':'' ?>>solid</option>
        <option value="dashed" <?= $values['block_border_style']==='dashed'?'selected':'' ?>>dashed</option>
        <option value="dotted" <?= $values['block_border_style']==='dotted'?'selected':'' ?>>dotted</option>
        <option value="double" <?= $values['block_border_style']==='double'?'selected':'' ?>>double</option>
        <option value="groove" <?= $values['block_border_style']==='groove'?'selected':'' ?>>groove</option>
        <option value="ridge" <?= $values['block_border_style']==='ridge'?'selected':'' ?>>ridge</option>
        <option value="inset" <?= $values['block_border_style']==='inset'?'selected':'' ?>>inset</option>
        <option value="outset" <?= $values['block_border_style']==='outset'?'selected':'' ?>>outset</option>
      </select>
    </label>
    <span class="desc" title="Border style for posts and forms.">Border style for posts and forms.</span>
    <label>Border Width (px): <input type="number" name="block_border_width" min="1" max="10" value="<?= h($values['block_border_width']) ?>" title="Border width in pixels."></label>
    <span class="desc" title="Border width in pixels.">Border width in pixels (1-10).</span>
    <label>Border Color: <input type="color" name="block_border_color" value="<?= h($values['block_border_color']) ?>" title="Border color for posts and forms."></label>
    <span class="desc" title="Border color for posts and forms.">Border color for posts and forms.</span>
  </fieldset>
  <button type="submit">Save Blog Settings</button>
</form>
</body>
</html>

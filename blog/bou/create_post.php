<?php
session_start();
$blog_dir = __DIR__;
$posts_dir = $blog_dir . '/posts';
$blog_owner = basename($blog_dir);

if (!isset($_SESSION['user']) || $_SESSION['user'] !== $blog_owner) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $error = '';
    $image_path = '';

    if ($title === '' || $content === '') {
        $error = 'Title and content required.';
    }
    // Handle image upload with size limit (2MB)
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['image']['tmp_name'];
        $name = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webm'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($_FILES['image']['size'] > $max_size) {
            $error = 'Image too large (max 2MB).';
        } elseif (in_array($ext, $allowed)) {
            $uploads_dir = $blog_dir . '/uploads';
            if (!is_dir($uploads_dir)) mkdir($uploads_dir);
            $img_name = time() . '_' . uniqid() . '.' . $ext;
            $dest = $uploads_dir . '/' . $img_name;
            if (move_uploaded_file($tmp, $dest)) {
                $image_path = 'uploads/' . $img_name;
            } else {
                $error = 'Failed to save image.';
            }
        } else {
            $error = 'Invalid image type.';
        }
    }
    if (!$error) {
        $timestamp = time();
        $postfile = $posts_dir . '/' . $timestamp . '_post.txt';
        $postdata = "Title: $title\n";
        if ($image_path) $postdata .= "Image: $image_path\n";
        $postdata .= "Content:\n$content\n";
        file_put_contents($postfile, $postdata);
        header('Location: index.php');
        exit();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create Post</title>
    <link rel="stylesheet" type="text/css" href="../../css.css"/>
</head>
<body>
<h2>Error creating post</h2>
<?php if (!empty($error)) echo '<div style="color:red;">' . htmlspecialchars($error) . '</div>'; ?>
<p><a href="index.php">Back to Blog</a></p>
</body>
</html>

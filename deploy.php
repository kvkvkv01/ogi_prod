<?php
session_start();
// Only allow admin to run this
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

$results = [];
$git_output = [];
$update_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Git pull
    $results[] = '<h3>Step 1: Git Pull</h3>';
    
    // Get current directory
    $current_dir = __DIR__;
    
    // Run git pull
    $git_command = 'cd "' . $current_dir . '" && git pull origin master 2>&1';
    exec($git_command, $git_output, $git_return_code);
    
    if ($git_return_code === 0) {
        $results[] = '<span style="color: green;">✓ Git pull successful</span>';
        if (!empty($git_output)) {
            $results[] = '<pre style="background: #f5f5f5; padding: 10px; margin: 5px 0;">' . htmlspecialchars(implode("\n", $git_output)) . '</pre>';
        }
    } else {
        $results[] = '<span style="color: red;">✗ Git pull failed</span>';
        if (!empty($git_output)) {
            $results[] = '<pre style="background: #f5f5f5; padding: 10px; margin: 5px 0; color: red;">' . htmlspecialchars(implode("\n", $git_output)) . '</pre>';
        }
        $results[] = 'Deployment stopped due to git pull failure.';
    }
    
    // Step 2: Update blogs (only if git pull succeeded)
    if ($git_return_code === 0) {
        $results[] = '<h3>Step 2: Update Blogs</h3>';
        
        // Include and run update_blogs.php logic
        $blog_root = __DIR__ . '/blog';
        $kanon_index = $blog_root . '/kanon/index.php';
        $kanon_thread = $blog_root . '/kanon/thread.php';
        
        if (!file_exists($kanon_index)) {
            $update_results[] = 'Template index.php not found in blog/kanon!';
        } elseif (!file_exists($kanon_thread)) {
            $update_results[] = 'Template thread.php not found in blog/kanon!';
        } else {
            $blogs = glob($blog_root . '/*', GLOB_ONLYDIR);
            foreach ($blogs as $blog) {
                $blog_name = basename($blog);
                $target_index = $blog . '/index.php';
                $target_thread = $blog . '/thread.php';
                
                // Copy index.php
                if (copy($kanon_index, $target_index)) {
                    $update_results[] = "Updated index.php for $blog_name";
                } else {
                    $update_results[] = "Failed to update index.php for $blog_name";
                }
                
                // Copy thread.php
                if (copy($kanon_thread, $target_thread)) {
                    $update_results[] = "Updated thread.php for $blog_name";
                } else {
                    $update_results[] = "Failed to update thread.php for $blog_name";
                }
                
                // Ensure folders exist
                foreach (['posts', 'uploads', 'replies'] as $folder) {
                    $dir = $blog . '/' . $folder;
                    if (!is_dir($dir)) {
                        if (mkdir($dir, 0777, true)) {
                            $update_results[] = "Created $folder for $blog_name";
                        } else {
                            $update_results[] = "Failed to create $folder for $blog_name";
                        }
                    }
                    @chmod($dir, 0777);
                }
            }
        }
        
        // Display update results
        if (!empty($update_results)) {
            $results[] = '<ul>';
            foreach ($update_results as $result) {
                $results[] = '<li>' . htmlspecialchars($result) . '</li>';
            }
            $results[] = '</ul>';
        }
        
        $results[] = '<span style="color: green;">✓ Blog update completed</span>';
    }
    
    // Final status
    if ($git_return_code === 0) {
        $results[] = '<h3 style="color: green;">✓ Deployment completed successfully!</h3>';
    } else {
        $results[] = '<h3 style="color: red;">✗ Deployment failed</h3>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Deploy Updates</title>
    <link rel="stylesheet" type="text/css" href="css.css"/>
</head>
<body>
<h2>Deploy Updates</h2>
<a href="index.php">Back to Home</a>
<hr>
<form method="post">
    <p>This will:</p>
    <ol>
        <li><b>Pull latest changes</b> from the git repository (origin/master)</li>
        <li><b>Update all blogs</b> with the latest index.php and thread.php files</li>
        <li><b>Ensure folders exist</b> (posts, uploads, replies) in each blog</li>
    </ol>
    <p><strong>Warning:</strong> This will overwrite any local changes to index.php and thread.php files in all blogs.</p>
    <button type="submit" onclick="return confirm('Are you sure you want to deploy? This will overwrite blog files.')">Deploy Updates</button>
</form>

<?php if (!empty($results)): ?>
    <h3>Deployment Results:</h3>
    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 20px;">
        <?php foreach ($results as $result): ?>
            <?= $result ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html> 
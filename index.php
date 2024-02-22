<?php
session_start();

$dbHost = 'dpg-cnbma26d3nmc73d14qeg-a';
$dbPort = 5432; 
$dbName = 'csci4140db';
$dbUser = 'admin';
$dbPass = 'GRDSrTt7ygmxJdlx0ogjYYvBvu0lnvKq';

try {
    $pdo = new PDO("pgsql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    try {
    $tableCheckStmt = $pdo->query("SELECT to_regclass('public.images')");
    if($tableCheckStmt->fetchColumn() === null) {
        $pdo->exec("CREATE TABLE images (
            id SERIAL PRIMARY KEY,
            photo BYTEA NOT NULL,
            username VARCHAR(255) NOT NULL,
            public BOOLEAN NOT NULL DEFAULT FALSE
        )");
    }
    } catch (PDOException $e) {
        die("Could not check or create the table 'images': " . $e->getMessage());
    }

    
} catch (PDOException $e) {
    die("Could not connect to the database $dbName :" . $e->getMessage());
}

$isLoggedIn = isset($_SESSION['username']); 
$username = $isLoggedIn ? $_SESSION['username'] : '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], ['image/jpeg', 'image/png'])) {
        $photoData = file_get_contents($file['tmp_name']);
        $public = isset($_POST['public']) ? (bool)$_POST['public'] : false;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO images (photo, username, public) VALUES (:photo, :username, :public)");
            $stmt->bindParam(':photo', $photoData, PDO::PARAM_LOB);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':public', $public, PDO::PARAM_BOOL);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$perPage = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

try {
    if ($isLoggedIn) {
        $stmt = $pdo->prepare("SELECT * FROM images WHERE public = TRUE OR username = :username ORDER BY id LIMIT :perPage OFFSET :offset");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM images WHERE public = TRUE ORDER BY id LIMIT :perPage OFFSET :offset");
    }
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($isLoggedIn) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM images WHERE public = TRUE OR username = :username");
        $countStmt->bindParam(':username', $username, PDO::PARAM_STR);
    } else {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM images WHERE public = TRUE");
    }
    $countStmt->execute();
    $totalImages = $countStmt->fetchColumn();
    $totalPages = ceil($totalImages / $perPage);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $images = [];
    $totalPages = 0;
}

$users = [
    ['username' => 'admin', 'password' => 'minda123'],
    ['username' => 'Student', 'password' => 'csci4140sp24'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    foreach ($users as $user) {
        if ($username === $user['username'] && $password === $user['password']) {
            $_SESSION['username'] = $username;
            $isLoggedIn = true;
            header('Location: index.php'); 
            exit;
        }
    }

    if (!$isLoggedIn) {
        $loginError = 'Invalid username or password.';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    unset($_SESSION['username']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<htmllang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple PHP Login with Image Gallery</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if ($isLoggedIn): ?>
    <p>Welcome, <?= htmlspecialchars($username); ?>!</p>
    <a href="index.php?action=logout">Logout</a>

    <form action="index.php" method="post" enctype="multipart/form-data">
        <input type="file" name="photo" accept=".jpg, .jpeg, .png" required>
        <input type="checkbox" name="public" value="1"> Public
        <button type="submit">Upload Photo</button>
    </form>
<?php else: ?>
    <?php if (isset($loginError)): ?>
        <p><?= htmlspecialchars($loginError) ?></p>
    <?php endif; ?>

    <form action="index.php" method="post">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
<?php endif; ?>

<div class="image-gallery">
    <?php foreach ($images as $image): ?>
        <div class="image">
            <?php
            if (!empty($image['photo'])) {
                $photoData = stream_get_contents($image['photo']);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($photoData);
                $base64Data = base64_encode($photoData);
                $srcData = "data:" . $mimeType . ";base64," . $base64Data;
            ?>
            <img src="<?= htmlspecialchars($srcData) ?>" alt="Image <?= htmlspecialchars($image['id'] ?? '') ?>">
            <?php
            } else {
                echo "<p>No image data.</p>";
            }
            ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= ($page === $i) ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</body>
</html>

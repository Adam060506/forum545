<?php
// Adatbázis helyett fájl alapú adattárolás
$topicsFile = "data.json";
$usersFile = "users.json";

// Munkamenet kezelés
session_start();

// Felhasználók betöltése
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?? [];
} else {
    $users = [];
}

// Bejelentkezési funkció
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            break;
        }
    }
}

// Kijelentkezés
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Regisztráció
if (isset($_POST['action']) && $_POST['action'] == 'register') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Ellenőrzés, hogy létezik-e már a felhasználó
    $userExists = false;
    foreach ($users as $user) {
        if ($user['username'] === $username || $user['email'] === $email) {
            $userExists = true;
            break;
        }
    }
    
    if (!$userExists) {
        $lastId = 0;
        if (!empty($users)) {
            $lastUser = end($users);
            $lastId = $lastUser['id'];
        }
        $newId = $lastId + 1;
        
        $newUser = [
            "id" => $newId,
            "username" => $username,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "email" => $email,
            "role" => "user",
            "registration_date" => date("Y-m-d H:i:s")
        ];
        
        $users[] = $newUser;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        
        $_SESSION['user_id'] = $newId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
    }
}

// Témák betöltése
if (file_exists($topicsFile)) {
    $jsonString = file_get_contents($topicsFile);
    $topics = json_decode($jsonString);
} else {
    $topics = [];
}

// Műveletek feldolgozása
if (isset($_POST['action'])) {
    $lastId = 0;
    if (!empty($topics)) {
        $lastItem = end($topics);
        $lastId = $lastItem->id;
    }
    $newId = $lastId + 1;

    if ($_POST['action'] == 'add') {
        array_push($topics, (object)[
            "id" => $newId,
            "name" => $_POST['topic'],
            "description" => $_POST['description'] ?? '',
            "author" => $_SESSION['username'] ?? 'Vendég',
            "author_id" => $_SESSION['user_id'] ?? 0,
            "date" => date("Y-m-d H:i:s"),
            "views" => 0,
            "replies" => 0,
            "pinned" => false,
            "locked" => false
        ]);
        $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
        file_put_contents($topicsFile, $jsonString);
    } elseif ($_POST['action'] == 'delete') {
        $idToDelete = $_POST['id'];
        $topics = array_filter($topics, function ($topic) use ($idToDelete) {
            return $topic->id != $idToDelete;
        });
        $topics = array_values($topics);
        $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
        file_put_contents($topicsFile, $jsonString);
    } elseif ($_POST['action'] == 'comment') {
        $topicId = $_POST['topic_id'];
        $commentFile = "comments_{$topicId}.json";
        
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile));
        } else {
            $comments = [];
        }

        $lastId = 0;
        if (!empty($comments)) {
            $lastItem = end($comments);
            $lastId = $lastItem->id;
        }
        $newId = $lastId + 1;

        $newComment = (object)[
            "id" => $newId,
            "topic_id" => $topicId,
            "author" => $_SESSION['username'] ?? 'Vendég',
            "author_id" => $_SESSION['user_id'] ?? 0,
            "message" => $_POST['message'],
            "date" => date("Y-m-d H:i:s"),
            "edited" => false,
            "edit_date" => null,
            "likes" => 0,
            "dislikes" => 0
        ];

        $comments[] = $newComment;
        file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
        
        // Frissítsük a témában a válaszok számát
        foreach ($topics as $topic) {
            if ($topic->id == $topicId) {
                $topic->replies++;
                break;
            }
        }
        file_put_contents($topicsFile, json_encode($topics, JSON_PRETTY_PRINT));
    } elseif ($_POST['action'] == 'edit_comment') {
        $topicId = $_POST['topic_id'];
        $commentId = $_POST['comment_id'];
        $newMessage = $_POST['message'];
        $commentFile = "comments_{$topicId}.json";
        
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile), true);
            foreach ($comments as &$comment) {
                if ($comment['id'] == $commentId) {
                    // Ellenőrizzük, hogy a felhasználó szerkesztheti-e a kommentet
                    $currentUserId = $_SESSION['user_id'] ?? 0;
                    $currentUserRole = $_SESSION['role'] ?? 'user';
                    if ($currentUserId == $comment['author_id'] || $currentUserRole == 'admin') {
                        $comment['message'] = $newMessage;
                        $comment['edited'] = true;
                        $comment['edit_date'] = date("Y-m-d H:i:s");
                        break;
                    }
                }
            }
            file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
        }
    } elseif ($_POST['action'] == 'delete_comment') {
        $topicId = $_POST['topic_id'];
        $commentId = $_POST['comment_id'];
        $commentFile = "comments_{$topicId}.json";
        
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile), true);
            $currentUserId = $_SESSION['user_id'] ?? 0;
            $currentUserRole = $_SESSION['role'] ?? 'user';
            
            $comments = array_filter($comments, function ($comment) use ($commentId, $currentUserId, $currentUserRole) {
                // Csak a saját kommentjeit törölheti, vagy admin
                if ($comment['id'] == $commentId) {
                    if ($currentUserId == $comment['author_id'] || $currentUserRole == 'admin') {
                        return false; // Töröljük
                    }
                }
                return true; // Megtartjuk
            });
            $comments = array_values($comments);
            file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
            
            // Csökkentsük a témában a válaszok számát
            foreach ($topics as $topic) {
                if ($topic->id == $topicId) {
                    $topic->replies = max(0, $topic->replies - 1);
                    break;
                }
            }
            file_put_contents($topicsFile, json_encode($topics, JSON_PRETTY_PRINT));
        }
    } elseif ($_POST['action'] == 'like_comment') {
        $topicId = $_POST['topic_id'];
        $commentId = $_POST['comment_id'];
        $commentFile = "comments_{$topicId}.json";
        
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile), true);
            foreach ($comments as &$comment) {
                if ($comment['id'] == $commentId) {
                    $comment['likes']++;
                    break;
                }
            }
            file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
        }
    } elseif ($_POST['action'] == 'dislike_comment') {
        $topicId = $_POST['topic_id'];
        $commentId = $_POST['comment_id'];
        $commentFile = "comments_{$topicId}.json";
        
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile), true);
            foreach ($comments as &$comment) {
                if ($comment['id'] == $commentId) {
                    $comment['dislikes']++;
                    break;
                }
            }
            file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
        }
    }
}

// Nézetszám növelése
if (isset($_GET['topic'])) {
    $selectedId = $_GET['topic'];
    foreach ($topics as $topic) {
        if ($topic->id == $selectedId) {
            $topic->views++;
            file_put_contents($topicsFile, json_encode($topics, JSON_PRETTY_PRINT));
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Fórum</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 2rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        nav a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            font-weight: 500;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger);
        }

        .btn-danger:hover {
            background-color: #d1145a;
        }

        .btn-success {
            background-color: var(--success);
        }

        .btn-success:hover {
            background-color: #3aa8d0;
        }

        .btn-warning {
            background-color: var(--warning);
        }

        .btn-warning:hover {
            background-color: #e07c00;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        .forum-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
            margin: 30px 0;
        }

        .main-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .sidebar {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
        }

        .section-header {
            padding: 20px;
            background-color: var(--light);
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .topics-list {
            padding: 0;
        }

        .topic-item {
            display: flex;
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }

        .topic-item:hover {
            background-color: var(--light);
        }

        .topic-icon {
            width: 40px;
            height: 40px;
            background-color: var(--light-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--gray);
        }

        .topic-content {
            flex: 1;
        }

        .topic-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .topic-title a {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .topic-title a:hover {
            color: var(--primary);
        }

        .topic-meta {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .topic-stats {
            text-align: right;
            min-width: 100px;
        }

        .topic-replies, .topic-views {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .topic-replies {
            color: var(--primary);
            font-weight: 600;
        }

        .topic-views {
            color: var(--gray);
        }

        .topic-date {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .pinned-topic {
            background-color: #fff9e6;
            border-left: 4px solid var(--warning);
        }

        .locked-topic .topic-title::after {
            content: " 🔒";
            font-size: 0.8rem;
        }

        .form-container {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .comment {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            position: relative;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary);
        }

        .comment-date {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .comment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .like-btn, .dislike-btn, .edit-btn, .delete-btn {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            font-size: 0.85rem;
        }

        .like-btn:hover {
            color: var(--success);
        }

        .dislike-btn:hover {
            color: var(--danger);
        }

        .edit-btn:hover {
            color: var(--warning);
        }

        .delete-btn:hover {
            color: var(--danger);
        }

        .like-btn.active {
            color: var(--success);
        }

        .dislike-btn.active {
            color: var(--danger);
        }

        .comment-edit-form {
            display: none;
            margin-top: 10px;
        }

        .comment-edit-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }

        .edit-info {
            font-size: 0.8rem;
            color: var(--gray);
            font-style: italic;
            margin-top: 5px;
        }

        .stats-card {
            background-color: var(--light);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
        }

        .stats-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .online-users {
            margin-top: 20px;
        }

        .user-avatar {
            width: 30px;
            height: 30px;
            background-color: var(--primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 10px;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
        }

        .page-link:hover {
            background-color: var(--light);
        }

        .page-link.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .modal-header {
            padding: 15px 20px;
            background-color: var(--light);
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
        }

        .auth-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .auth-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }

        .auth-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        footer {
            background-color: var(--dark);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
        }

        .footer-section {
            flex: 1;
            min-width: 250px;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: #adb5bd;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
        }

        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #adb5bd;
        }

        @media (max-width: 768px) {
            .forum-container {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-comments"></i>
                    <span>Modern Fórum</span>
                </div>
                <nav>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Főoldal</a></li>
                        <li><a href="#"><i class="fas fa-list"></i> Témák</a></li>
                        <li><a href="#"><i class="fas fa-users"></i> Tagok</a></li>
                        <li><a href="#"><i class="fas fa-search"></i> Keresés</a></li>
                    </ul>
                </nav>
                <div class="user-info">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span>Üdv, <?php echo $_SESSION['username']; ?>!</span>
                        <a href="?logout" class="btn btn-outline btn-small">Kijelentkezés</a>
                    <?php else: ?>
                        <button class="btn btn-outline btn-small" id="loginBtn">Bejelentkezés</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="forum-container">
            <div class="main-content">
                <?php if (!isset($_GET['topic'])): ?>
                    <div class="section-header">
                        <h2 class="section-title">Fórum témák</h2>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="btn" id="newTopicBtn"><i class="fas fa-plus"></i> Új téma</button>
                        <?php else: ?>
                            <button class="btn" id="loginRequiredBtn"><i class="fas fa-plus"></i> Új téma</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="topics-list">
                        <?php if (empty($topics)): ?>
                            <div class="topic-item" style="text-align: center; padding: 30px;">
                                <p>Még nincsenek témák a fórumban. Legyél te az első, aki létrehoz egyet!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topics as $value): ?>
                                <div class="topic-item <?php echo $value->pinned ? 'pinned-topic' : ''; ?> <?php echo $value->locked ? 'locked-topic' : ''; ?>">
                                    <div class="topic-icon">
                                        <i class="fas fa-comment"></i>
                                    </div>
                                    <div class="topic-content">
                                        <div class="topic-title">
                                            <a href="?topic=<?php echo $value->id; ?>"><?php echo htmlspecialchars($value->name); ?></a>
                                        </div>
                                        <div class="topic-meta">
                                            <?php if (!empty($value->description)): ?>
                                                <p><?php echo htmlspecialchars($value->description); ?></p>
                                            <?php endif; ?>
                                            <span>Kezdeményezte: <strong><?php echo htmlspecialchars($value->author); ?></strong> • <?php echo $value->date; ?></span>
                                        </div>
                                    </div>
                                    <div class="topic-stats">
                                        <div class="topic-replies"><?php echo $value->replies; ?> válasz</div>
                                        <div class="topic-views"><?php echo $value->views; ?> megtekintés</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination">
                        <a href="#" class="page-link active">1</a>
                        <a href="#" class="page-link">2</a>
                        <a href="#" class="page-link">3</a>
                        <a href="#" class="page-link">Következő</a>
                    </div>
                    
                <?php else: ?>
                    <?php
                    $selectedId = $_GET['topic'];
                    $selectedTopic = null;
                    foreach ($topics as $topic) {
                        if ($topic->id == $selectedId) {
                            $selectedTopic = $topic;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($selectedTopic): ?>
                        <div class="section-header">
                            <h2 class="section-title"><?php echo htmlspecialchars($selectedTopic->name); ?></h2>
                            <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Vissza</a>
                        </div>
                        
                        <div class="topic-meta" style="padding: 15px 20px; background-color: var(--light); border-bottom: 1px solid var(--light-gray);">
                            <p><strong>Kezdeményezte:</strong> <?php echo htmlspecialchars($selectedTopic->author); ?> • <strong>Dátum:</strong> <?php echo $selectedTopic->date; ?></p>
                            <?php if (!empty($selectedTopic->description)): ?>
                                <p><strong>Leírás:</strong> <?php echo htmlspecialchars($selectedTopic->description); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        $commentFile = "comments_{$selectedTopic->id}.json";
                        if (file_exists($commentFile)) {
                            $comments = json_decode(file_get_contents($commentFile), true);
                        } else {
                            $comments = [];
                        }
                        ?>
                        
                        <div class="comments-section">
                            <?php if (empty($comments)): ?>
                                <div style="padding: 30px; text-align: center;">
                                    <p>Még nincsenek hozzászólások ebben a témában. Legyél te az első!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment" id="comment-<?php echo $comment['id']; ?>">
                                        <div class="comment-header">
                                            <div class="comment-author"><?php echo htmlspecialchars($comment['author']); ?></div>
                                            <div class="comment-date">
                                                <?php echo $comment['date']; ?>
                                                <?php if ($comment['edited'] ?? false): ?>
                                                    <span class="edit-info">(módosítva: <?php echo $comment['edit_date']; ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="comment-body">
                                            <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></div>
                                            <div class="comment-edit-form" id="edit-form-<?php echo $comment['id']; ?>">
                                                <form method="POST" class="edit-comment-form">
                                                    <input type="hidden" name="action" value="edit_comment">
                                                    <input type="hidden" name="topic_id" value="<?php echo $selectedTopic->id; ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                    <textarea name="message" class="form-control" required><?php echo htmlspecialchars($comment['message']); ?></textarea>
                                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                        <button type="submit" class="btn btn-warning btn-small">Mentés</button>
                                                        <button type="button" class="btn btn-outline btn-small cancel-edit">Mégse</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="comment-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="like_comment">
                                                <input type="hidden" name="topic_id" value="<?php echo $selectedTopic->id; ?>">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                <button type="submit" class="like-btn"><i class="fas fa-thumbs-up"></i> Tetszik (<?php echo $comment['likes']; ?>)</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="dislike_comment">
                                                <input type="hidden" name="topic_id" value="<?php echo $selectedTopic->id; ?>">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                <button type="submit" class="dislike-btn"><i class="fas fa-thumbs-down"></i> Nem tetszik (<?php echo $comment['dislikes']; ?>)</button>
                                            </form>
                                            
                                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $comment['author_id'] || ($_SESSION['role'] ?? 'user') == 'admin')): ?>
                                                <button class="edit-btn" data-comment-id="<?php echo $comment['id']; ?>">
                                                    <i class="fas fa-edit"></i> Szerkesztés
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a hozzászólást?')">
                                                    <input type="hidden" name="action" value="delete_comment">
                                                    <input type="hidden" name="topic_id" value="<?php echo $selectedTopic->id; ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                    <button type="submit" class="delete-btn"><i class="fas fa-trash"></i> Törlés</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="form-container">
                                <h3>Új hozzászólás</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="topic_id" value="<?php echo $selectedTopic->id; ?>">
                                    <div class="form-group">
                                        <textarea name="message" class="form-control" placeholder="Írd ide a hozzászólásodat..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Hozzászólás küldése</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="form-container" style="text-align: center; padding: 30px;">
                                <p>A hozzászóláshoz be kell jelentkezned.</p>
                                <button class="btn" id="loginRequiredCommentBtn">Bejelentkezés</button>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="section-header">
                            <h2 class="section-title">Hiba</h2>
                        </div>
                        <div style="padding: 30px; text-align: center;">
                            <p>A megadott ID-hoz nem található téma.</p>
                            <a href="index.php" class="btn">Vissza a főoldalra</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="sidebar">
                <div class="stats-card">
                    <h3 class="stats-title">Fórum statisztika</h3>
                    <div class="stat-item">
                        <span>Témák:</span>
                        <strong><?php echo count($topics); ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Hozzászólások:</span>
                        <strong>
                            <?php
                            $totalReplies = 0;
                            foreach ($topics as $topic) {
                                $totalReplies += $topic->replies;
                            }
                            echo $totalReplies;
                            ?>
                        </strong>
                    </div>
                    <div class="stat-item">
                        <span>Tagok:</span>
                        <strong><?php echo count($users); ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Legújabb tag:</span>
                        <strong>
                            <?php
                            if (!empty($users)) {
                                $lastUser = end($users);
                                echo $lastUser['username'];
                            } else {
                                echo "Nincs";
                            }
                            ?>
                        </strong>
                    </div>
                </div>
                
                <div class="online-users">
                    <h3 class="stats-title">Jelenleg online</h3>
                    <div class="user-item">
                        <div class="user-avatar">A</div>
                        <span>Admin</span>
                    </div>
                    <div class="user-item">
                        <div class="user-avatar">T</div>
                        <span>TesztFelhasználó</span>
                    </div>
                    <div class="user-item">
                        <div class="user-avatar">J</div>
                        <span>JohnDoe</span>
                    </div>
                    <div class="user-item">
                        <div class="user-avatar">M</div>
                        <span>MaryJane</span>
                    </div>
                </div>
                
                <div class="popular-topics" style="margin-top: 20px;">
                    <h3 class="stats-title">Népszerű témák</h3>
                    <?php
                    // Rendezzük a témákat nézettség szerint csökkenő sorrendben
                    usort($topics, function($a, $b) {
                        return $b->views - $a->views;
                    });
                    
                    // Csak az első 5 témát jelenítjük meg
                    $popularTopics = array_slice($topics, 0, 5);
                    ?>
                    
                    <?php foreach ($popularTopics as $topic): ?>
                        <div class="topic-item" style="padding: 10px 0; border-bottom: 1px solid var(--light-gray);">
                            <div class="topic-title" style="font-size: 0.9rem;">
                                <a href="?topic=<?php echo $topic->id; ?>"><?php echo htmlspecialchars($topic->name); ?></a>
                            </div>
                            <div class="topic-meta" style="font-size: 0.8rem;">
                                <?php echo $topic->views; ?> megtekintés
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Új téma modal -->
    <div class="modal" id="newTopicModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Új téma létrehozása</h3>
                <button class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="newTopicForm">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label" for="topic">Téma címe</label>
                        <input type="text" id="topic" name="topic" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="description">Leírás (opcionális)</label>
                        <textarea id="description" name="description" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Téma létrehozása</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bejelentkezés modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Bejelentkezés / Regisztráció</h3>
                <button class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="auth-tabs">
                    <div class="auth-tab active" data-tab="login">Bejelentkezés</div>
                    <div class="auth-tab" data-tab="register">Regisztráció</div>
                </div>
                
                <form method="POST" id="loginForm" class="auth-form active">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label" for="username">Felhasználónév</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Jelszó</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn">Bejelentkezés</button>
                </form>
                
                <form method="POST" id="registerForm" class="auth-form">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label" for="reg_username">Felhasználónév</label>
                        <input type="text" id="reg_username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg_email">Email cím</label>
                        <input type="email" id="reg_email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg_password">Jelszó</label>
                        <input type="password" id="reg_password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn">Regisztráció</button>
                </form>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3 class="footer-title">Modern Fórum</h3>
                    <p>Ez egy modern, funkcionalitásban gazdag fórum alkalmazás, ahol szabadon megoszthatod véleményedet és gondolataidat.</p>
                </div>
                <div class="footer-section">
                    <h3 class="footer-title">Gyors linkek</h3>
                    <ul class="footer-links">
                        <li><a href="#">Főoldal</a></li>
                        <li><a href="#">Témák</a></li>
                        <li><a href="#">Tagok</a></li>
                        <li><a href="#">Keresés</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3 class="footer-title">Közösségi média</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2025 Modern Fórum. Minden jog fenntartva.
            </div>
        </div>
    </footer>

    <script>
        // Modal kezelés
        document.addEventListener('DOMContentLoaded', function() {
            const newTopicBtn = document.getElementById('newTopicBtn');
            const loginBtn = document.getElementById('loginBtn');
            const loginRequiredBtn = document.getElementById('loginRequiredBtn');
            const loginRequiredCommentBtn = document.getElementById('loginRequiredCommentBtn');
            const newTopicModal = document.getElementById('newTopicModal');
            const loginModal = document.getElementById('loginModal');
            const closeBtns = document.querySelectorAll('.close-btn');
            const authTabs = document.querySelectorAll('.auth-tab');
            const authForms = document.querySelectorAll('.auth-form');
            
            if (newTopicBtn) {
                newTopicBtn.addEventListener('click', function() {
                    newTopicModal.style.display = 'flex';
                });
            }
            
            if (loginBtn) {
                loginBtn.addEventListener('click', function() {
                    loginModal.style.display = 'flex';
                });
            }
            
            if (loginRequiredBtn) {
                loginRequiredBtn.addEventListener('click', function() {
                    loginModal.style.display = 'flex';
                });
            }
            
            if (loginRequiredCommentBtn) {
                loginRequiredCommentBtn.addEventListener('click', function() {
                    loginModal.style.display = 'flex';
                });
            }
            
            closeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    newTopicModal.style.display = 'none';
                    loginModal.style.display = 'none';
                });
            });
            
            // Kattintás a modalon kívülre zárja a modalt
            window.addEventListener('click', function(e) {
                if (e.target === newTopicModal) {
                    newTopicModal.style.display = 'none';
                }
                if (e.target === loginModal) {
                    loginModal.style.display = 'none';
                }
            });
            
            // Auth tab váltás
            authTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Tab aktívvá tétele
                    authTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Form megjelenítése/elrejtése
                    authForms.forEach(form => {
                        form.classList.remove('active');
                        if (form.id === tabId + 'Form') {
                            form.classList.add('active');
                        }
                    });
                });
            });
            
            // Téma létrehozása után modal bezárása
            const newTopicForm = document.getElementById('newTopicForm');
            if (newTopicForm) {
                newTopicForm.addEventListener('submit', function() {
                    setTimeout(function() {
                        newTopicModal.style.display = 'none';
                    }, 500);
                });
            }

            // Hozzászólás szerkesztése
            const editButtons = document.querySelectorAll('.edit-btn');
            const cancelButtons = document.querySelectorAll('.cancel-edit');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const commentText = document.querySelector(`#comment-${commentId} .comment-text`);
                    const editForm = document.querySelector(`#edit-form-${commentId}`);
                    
                    // Elrejtjük a szöveget és megjelenítjük a szerkesztő formot
                    commentText.style.display = 'none';
                    editForm.style.display = 'block';
                });
            });
            
            cancelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('.comment-edit-form');
                    const commentId = form.id.replace('edit-form-', '');
                    const commentText = document.querySelector(`#comment-${commentId} .comment-text`);
                    
                    // Elrejtjük a szerkesztő formot és megjelenítjük a szöveget
                    form.style.display = 'none';
                    commentText.style.display = 'block';
                });
            });

            // Szerkesztés form elküldése után újratöltjük az oldalt
            const editForms = document.querySelectorAll('.edit-comment-form');
            editForms.forEach(form => {
                form.addEventListener('submit', function() {
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                });
            });
        });
    </script>
</body>
</html>
<?php
$filename = "data.json";
if (file_exists($filename)) {
    $jsonString = file_get_contents($filename);
    $topics = json_decode($jsonString);
} else {
    $topics = [];
}

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
            "date" => date("Y-m-d H:i:s")
        ]);
        $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
        file_put_contents($filename, $jsonString);
    } elseif ($_POST['action'] == 'delete') {
        $idToDelete = $_POST['id'];
        $topics = array_filter($topics, function ($topic) use ($idToDelete) {
            return $topic->id != $idToDelete;
        });
        $topics = array_values($topics);
        $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
        file_put_contents($filename, $jsonString);
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
            "author" => $_POST['author'],
            "message" => $_POST['message'],
            "date" => date("Y-m-d H:i:s")
        ];

        $comments[] = $newComment;
        file_put_contents($commentFile, json_encode($comments, JSON_PRETTY_PRINT));
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Fórum</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 800px;
            margin: 30px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h1, h2, h3 {
            color: #333;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        li {
            padding: 10px;
            margin-bottom: 10px;
            background-color: #eef2f5;
            border-left: 5px solid #1976d2;
            border-radius: 4px;
        }

        a {
            text-decoration: none;
            color: #1976d2;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }

        form {
            margin-top: 15px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        input[type="submit"] {
            background-color: #1976d2;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #155a9c;
        }

        .back-link {
            margin-top: 20px;
            display: inline-block;
        }

        .comment {
            margin-bottom: 15px;
            background-color: #f1f1f1;
            padding: 10px;
            border-left: 3px solid #555;
            border-radius: 3px;
        }

        .comment strong {
            color: #444;
        }
    </style>
</head>
<body>
<div class="container">
<?php
if (!isset($_GET['topic'])) {
    echo "<h1>Fórum témák</h1><ul>";
    foreach ($topics as $value) {
        echo "<li class='topic-item'>
        <div class='topic-left'>
            <a href='?topic=" . $value->id . "'>" . htmlspecialchars($value->name) . "</a>
            <span class='topic-date'>(" . $value->date . ")</span>
        </div>
        <form method='post' class='delete-form'>
            <input type='hidden' name='id' value='" . $value->id . "'>
            <input type='hidden' name='action' value='delete'>
            <input type='submit' value='🗑️' title='Törlés'>
        </form>
      </li>";

    }
    echo "</ul>";
    echo '<h3>Új téma hozzáadása:</h3>
          <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="text" name="topic" required placeholder="Új téma neve">
            <input type="submit" value="Hozzáadás">
          </form>';
} else {
    $selectedId = $_GET['topic'];
    $selectedTopic = null;
    foreach ($topics as $topic) {
        if ($topic->id == $selectedId) {
            $selectedTopic = $topic;
            break;
        }
    }

    if ($selectedTopic) {
        echo "<h1>" . htmlspecialchars($selectedTopic->name) . "</h1>";

        // Hozzászólások megjelenítése
        $commentFile = "comments_{$selectedTopic->id}.json";
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile));
        } else {
            $comments = [];
        }

        echo "<h2>Hozzászólások:</h2>";
        if (empty($comments)) {
            echo "<p>Nincsenek hozzászólások.</p>";
        } else {
            foreach ($comments as $comment) {
                echo "<div class='comment'>
                        <strong>" . htmlspecialchars($comment->author) . "</strong> (" . $comment->date . ")<br>
                        " . nl2br(htmlspecialchars($comment->message)) . "
                      </div>";
            }
        }

        // Hozzászólás űrlap
        echo '<h3>Új hozzászólás:</h3>
              <form method="POST">
                  <input type="hidden" name="action" value="comment">
                  <input type="hidden" name="topic_id" value="' . $selectedTopic->id . '">
                  <input type="text" name="author" placeholder="Neved" required>
                  <textarea name="message" placeholder="Írd ide a hozzászólásodat..." rows="4" required></textarea>
                  <input type="submit" value="Hozzászólás küldése">
              </form>';
    } else {
        echo "<p>A megadott ID-hoz nem található téma.</p>";
    }

    echo '<p><a class="back-link" href="index.php">Vissza a főoldalra</a></p>';
}
?>
</div>
</body>
</html>

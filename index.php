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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fórum</title>
</head>
<body>

<?php
if (!isset($_GET['topic'])) {
    echo "<h1>Témák:</h1><ul>";
    foreach ($topics as $value) {
        echo "<li>
                <a href='?topic=" . $value->id . "'>" . htmlspecialchars($value->name) . "</a> (" . $value->date . ")
                <form method='post' style='display:inline; margin-left: 10px;'>
                    <input type='hidden' name='id' value='" . $value->id . "'>
                    <input type='hidden' name='action' value='delete'>
                    <input type='submit' value='Törlés'>
                </form>
              </li>";
    }
    echo "</ul>";
    echo '<form method="POST">
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
    } else {
        echo "<p>A megadott ID-hoz nem található téma.</p>";
    }
            // Hozzászólások megjelenítése
        $commentFile = "comments_{$selectedTopic->id}.json";
        if (file_exists($commentFile)) {
            $comments = json_decode(file_get_contents($commentFile));
        } else {
            $comments = [];
        }

        echo "<h2>Hozzászólások:</h2>";
        if (empty($comments)) {
            echo "<p>Ehhez a fórumhoz jelenleg nincsenek hozzászólások.</p>";
        } else {
            echo "<ul>";
            foreach ($comments as $comment) {
                echo "<li><strong>" . htmlspecialchars($comment->author) . "</strong> (" . $comment->date . "):<br>" .
                     nl2br(htmlspecialchars($comment->message)) . "</li>";
            }
            echo "</ul>";
        }

        // Hozzászólás űrlap
        echo '<h3>Új hozzászólás:</h3>
              <form method="POST">
                  <input type="hidden" name="action" value="comment">
                  <input type="hidden" name="topic_id" value="' . $selectedTopic->id . '">
                  <p><input type="text" name="author" placeholder="Neved" required></p>
                  <p><textarea name="message" placeholder="Üzenet" rows="4" cols="50" required></textarea></p>
                  <p><input type="submit" value="Hozzászólás küldése"></p>
              </form>';


    echo '<p><a href="index.php">Vissza a főoldalra</a></p>';
}
?>

</body>
</html>
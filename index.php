<?php
 $filename = "data.json";
if(file_exists($filename)){
    $jsonString = file_get_contents($filename);
    $topics = json_decode($jsonString);
} else
{
    $topics=[];
}

if(isset($_POST['action'])) 
    {
        $lastId = 0;
        if(!empty($topics)){
            $lastItem = end($topics);
            $lastId = $lastItem->id;
        }
    $newId = $lastId + 1;
    if ($_POST['action'] == 'add'){
        array_push($topics,
        (object)[
    "id" => $newId,
    "name" => $_POST['topic'],
    "date" => date("Y-m-d H:i:s")
   ] 
   );
   $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
   file_put_contents($filename,$jsonString);
  }
  elseif ($_POST['action'] == 'delete') {
    $idToDelete = $_POST['id'];
    $topics = array_filter($topics, function($topic) use ($idToDelete) {
        return $topic->id != $idToDelete;
    });
    $topics = array_values($topics);
    $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
    file_put_contents($filename, $jsonString);
}

}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum</title>
</head>
<body>
    <h1>Témák:</h1>
<ul>
    <?php
        foreach($topics as $value)
{
    echo "<li><strong>" . htmlspecialchars($value->name) . "</strong> <em>(" . $value->date . ")</em>
        <form method='post' style='display:inline; margin-left: 10px;'>
            <input type='hidden' name='id' value='" . $value->id . "'>
            <input type='hidden' name='action' value='delete'>
            <input type='submit' value='Törlés'>
        </form>
        </li>";
}

    ?>
</ul>
<form method = "POST">
    <input type="hidden" name="action" value="add">
    <input type="text" name = "topic">
    <input type="submit" value = "Add">
</form>
</body>
</html>
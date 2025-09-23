<?php
 $filename = "data.json";
if(file_exists($filename)){
    $jsonString = file_get_contents($filename);
    $topics = json_decode($jsonString);
} else{
    $topics=[];
}
if(isset ($_POST['topic'])) {
   array_push($topics, $_POST['topic']);
   $jsonString = json_encode($topics);
  file_put_contents($filename,$jsonString);
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
            echo "<li>" . $value . '
            <form method = "post">
            
            <input type=submit value="Törlés">
            </form>
            ';
        }
    ?>
</ul>
<form method = "POST">
    <input type="text" name = "topic">
    <input type="submit" value = "Add">
</form>
</body>
</html>
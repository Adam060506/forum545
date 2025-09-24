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
    if ($_POST['action'] == 'add'){
        array_push($topics,
        (object)
   [
    "id" => "1234",
    "name" => $_POST['topic']
   ]
   );
   $jsonString = json_encode($topics, JSON_PRETTY_PRINT);
   file_put_contents($filename,$jsonString);
  }
  elseif(($_POST['action'] == 'delete')){

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
            echo "<li>" . $value->name . '
            <form method = "post">
            <input type="hidden" name ="id" value="'.$value->id .'">
            <input type="hidden" name="action" value="delete">
            <input type=submit value="Törlés">
            </form>
            ';
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
<?php
require_once("../app/functions.php");

$posts = [];

if(file_exists("../data/posts.json")){
    $posts = json_decode(file_get_contents("../data/posts.json"), true);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Board</title>
<link rel="stylesheet" href="css/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

<div class="container">

<h1>Image Board</h1>

<a class="writebtn" href="index.php">글쓰기</a>

<div class="board">

<?php foreach(array_reverse($posts) as $post){ ?>

<div class="post">

<a href="../uploads/<?php echo $post['image']; ?>" target="_blank">
<img class="thumb" src="../uploads/<?php echo $post['image']; ?>">
</a>

<div class="postinfo">
<a class="postno" href="../uploads/<?php echo $post['image']; ?>" target="_blank">
No.<?php echo $post['id']; ?>
</a>

<p><?php echo htmlspecialchars($post['text']); ?></p>
</div>

</div>

<?php } ?>

</div>

</div>

</body>
</html>

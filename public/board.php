<?php

require_once "../app/functions.php";

$posts = loadPosts();

/* posts가 null이면 빈 배열 */
if(!$posts){
    $posts = [];
}

$posts = array_reverse($posts);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Board</title>

<link rel="stylesheet" href="css/style.css">

</head>

<body>

<h1>Image Board</h1>

<a href="index.php">글쓰기</a>

<div class="board">

<?php foreach($posts as $post){ ?>

<div class="card">

<a href="board.php?id=<?=$post['id']?>">

<p>No.<?=$post['id']?></p>

<?php if($post['image']){ ?>
<img src="uploads/<?=$post['image']?>" class="thumb">
<?php } ?>

</a>

<p><?=$post['text']?></p>

</div>

<?php } ?>

</div>

</body>
</html>

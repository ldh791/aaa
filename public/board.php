<?php

require_once "../app/functions.php";

$posts = loadPosts();

if(!$posts){
    $posts = [];
}

$posts = array_reverse($posts);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Image Board</title>

<link rel="stylesheet" href="css/style.css">

</head>

<body>

<h1>Image Board</h1>

<!-- 글쓰기 폼 -->
<div class="post-form">

<form action="post.php" method="post" enctype="multipart/form-data">

<textarea name="text" placeholder="내용 입력"></textarea>

<br>

<input type="file" name="image">

<br>

<button type="submit">글쓰기</button>

</form>

</div>

<hr>

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

<?php
require_once __DIR__.'/../app/functions.php';
$board = $_GET['b'] ?? 'b';
$posts = load_posts($board);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>/<?=$board?>/</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<a href="/">← back</a>
<h1>/<?=$board?>/</h1>

<form action="/post.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="board" value="<?=$board?>">
<textarea name="content" placeholder="text"></textarea>
<input type="file" name="image">
<button type="submit">post</button>
</form>

<hr>

<?php foreach($posts as $p){ ?>
<div class="post">
<div><?=htmlspecialchars($p['content'])?></div>
<?php if($p['image']){ ?>
<img src="/uploads/<?=$p['image']?>" class="thumb">
<?php } ?>
</div>
<?php } ?>

</body>
</html>

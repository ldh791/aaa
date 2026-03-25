<?php
require __DIR__.'/../app/storage/index.php';
require __DIR__.'/../app/functions.php';

$board=$_GET['board'] ?? 'test';

// 글 작성
if($_POST && !isset($_POST['reply_id'])){

  $imagePath = null;

  if(isset($_FILES['image']) && $_FILES['image']['tmp_name']){
    $filename = time().'_'.basename($_FILES['image']['name']);

    $uploadDir = __DIR__.'/uploads/';
    $thumbDir = __DIR__.'/thumbs/';

    if(!is_dir($uploadDir)) mkdir($uploadDir,0777,true);
    if(!is_dir($thumbDir)) mkdir($thumbDir,0777,true);

    $target = $uploadDir.$filename;

    move_uploaded_file($_FILES['image']['tmp_name'], $target);

    makeThumb($target, $thumbDir.$filename, 200);

    $imagePath = $filename;
  }

  addPost($board,$_POST['content'],$imagePath);
  header("Location: /$board");
  exit;
}

// 댓글
if(isset($_POST['reply_id'])){
  addReply($_POST['reply_id'],$_POST['reply']);
  header("Location: /$board");
  exit;
}

$posts=getPosts($board);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="/css/mobile.css">
</head>
<body>

<div class="container">

<h2>/<?= htmlspecialchars($board) ?></h2>

<form method="POST" enctype="multipart/form-data">
<textarea name="content"></textarea>
<input type="file" name="image">
<button>작성</button>
</form>

<?php foreach($posts as $p): ?>
<div class="post" id="post<?= $p['id'] ?>">

<a href="#post<?= $p['id'] ?>">No.<?= $p['id'] ?></a>

<div><?= nl2br(htmlspecialchars($p['content'])) ?></div>

<?php if(!empty($p['image'])): ?>
<a href="/uploads/<?= $p['image'] ?>" target="_blank">
<img src="/thumbs/<?= $p['image'] ?>" class="thumb">
</a>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="reply_id" value="<?= $p['id'] ?>">
<input name="reply">
<button>댓글</button>
</form>

<?php foreach(getReplies($p['id']) as $r): ?>
<div class="reply"><?= htmlspecialchars($r['content']) ?></div>
<?php endforeach; ?>

</div>
<?php endforeach; ?>

</div>

</body>
</html>

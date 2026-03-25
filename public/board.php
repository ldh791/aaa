<?php
$config=require '../app/config.php';
require '../app/functions.php';

$board=$_GET['board'];

// 글 작성
if($_POST && !isset($_POST['reply_id'])){

  $imagePath = null;

  if(isset($_FILES['image']) && $_FILES['image']['tmp_name']){
    $filename = time().'_'.basename($_FILES['image']['name']);
    $target = "uploads/".$filename;

    move_uploaded_file($_FILES['image']['tmp_name'], $target);

    // 썸네일 생성
    $thumb = "thumbs/".$filename;
    makeThumb($target, $thumb, 200);

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

<form method="POST" enctype="multipart/form-data" class="post-form">
<textarea name="content" placeholder="내용"></textarea>
<input type="file" name="image">
<button>작성</button>
</form>

<?php foreach($posts as $p): ?>
<div class="post">

<div class="post-header">
<a href="/<?= $board ?>#post<?= $p['id'] ?>">
No.<?= $p['id'] ?>
</a>
</div>

<div class="post-content">
<?= nl2br(htmlspecialchars($p['content'])) ?>
</div>

<?php if($p['image']): ?>
<div class="image-box">
<a href="/uploads/<?= $p['image'] ?>" target="_blank">
<img src="/thumbs/<?= $p['image'] ?>">
</a>
</div>
<?php endif; ?>

<form method="POST" class="reply-form">
<input type="hidden" name="reply_id" value="<?= $p['id'] ?>">
<input name="reply" placeholder="댓글">
<button>답글</button>
</form>

<?php foreach($p['replies'] as $r): ?>
<div class="reply">
<?= htmlspecialchars($r['content']) ?>
</div>
<?php endforeach; ?>

</div>
<?php endforeach; ?>

</div>

</body>
</html>

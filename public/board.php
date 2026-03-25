<?php
$config=require '../app/config.php';
require '../app/functions.php';

$board=$_GET['board'];

// 글 작성 처리
if($_POST && !isset($_POST['reply_id'])){
  addPost($board,$_POST['content']);
  header("Location: /$board");
  exit;
}

// 댓글 처리
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
<link rel="stylesheet" href="/css/style.css">
</head>
<body>

<h2>/<?= $board ?></h2>

<form method="POST">
<textarea name="content"></textarea>
<button>작성</button>
</form>

<?php foreach($posts as $p): ?>
<div>
<b>No.<?= $p['id'] ?></b><br>
<?= htmlspecialchars($p['content']) ?>

<form method="POST">
<input type="hidden" name="reply_id" value="<?= $p['id'] ?>">
<input name="reply">
<button>댓글</button>
</form>

<?php foreach($p['replies'] as $r): ?>
<div style="margin-left:20px;">
<?= htmlspecialchars($r['content']) ?>
</div>
<?php endforeach; ?>

</div>
<?php endforeach; ?>

</body>
</html>

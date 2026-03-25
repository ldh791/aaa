<?php

$uri = trim($_SERVER['REQUEST_URI'], '/');

// 홈
if($uri == ''){
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

<h1>이미지 게시판</h1>

<ul class="board-list">
<li><a href="/board.php?board=free">자유게시판</a></li>
<li><a href="/board.php?board=game">게임게시판</a></li>
<li><a href="/board.php?board=pic">이미지게시판</a></li>
</ul>

</div>

</body>
</html>
<?php
exit;
}

// 게시판 라우팅
header("Location: /board.php?board=".$uri);
exit;

<?php
$boards = ['b'=>'Random','g'=>'Technology'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Imageboard</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<h1>Boards</h1>
<ul>
<?php foreach($boards as $k=>$v){ ?>
<li><a href="/board.php?b=<?=$k?>">/<?=$k?>/ - <?=$v?></a></li>
<?php } ?>
</ul>
</body>
</html>

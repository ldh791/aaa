<?php $config=require '../app/config.php'; ?>
<!DOCTYPE html>
<html><head><link rel="stylesheet" href="/css/style.css"></head>
<body>
<h1>Imageboard</h1>
<?php foreach($config['boards'] as $k=>$v): ?>
<a href="/<?= $k ?>">/<?= $k ?> - <?= $v ?></a><br>
<?php endforeach; ?>
</body></html>

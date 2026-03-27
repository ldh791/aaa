<?php
require_once __DIR__.'/../app/functions.php';

$board = $_POST['board'];
$content = $_POST['content'];

$imageName = "";

if(isset($_FILES['image']) && $_FILES['image']['tmp_name']){
    $imageName = time()."_".basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], __DIR__."/uploads/".$imageName);
}

add_post($board,$content,$imageName);

header("Location: /board.php?b=".$board);
exit;

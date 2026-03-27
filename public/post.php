<?php

require_once "../app/functions.php";

$text = $_POST['text'];

$imageName = "";

if(isset($_FILES['image']) && $_FILES['image']['name']){

$uploadDir = "uploads/";

if(!is_dir($uploadDir)){
mkdir($uploadDir,0777,true);
}

$imageName = time()."_".$_FILES['image']['name'];

move_uploaded_file($_FILES['image']['tmp_name'],$uploadDir.$imageName);

}

$post = [

"id"=>time(),
"text"=>$text,
"image"=>$imageName

];

savePost($post);

header("Location: board.php");
exit;

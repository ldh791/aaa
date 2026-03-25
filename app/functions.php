<?php

function addPost($board,$content,$image=null){
  global $db;

  $stmt=$db->prepare("INSERT INTO posts (board,content,image) VALUES (?,?,?)");
  $stmt->execute([$board,$content,$image]);
}

function addReply($post_id,$content){
  global $db;

  $stmt=$db->prepare("INSERT INTO replies (post_id,content) VALUES (?,?)");
  $stmt->execute([$post_id,$content]);
}

function getPosts($board){
  global $db;

  $stmt=$db->prepare("SELECT * FROM posts WHERE board=? ORDER BY id DESC");
  $stmt->execute([$board]);
  $posts=$stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach($posts as &$p){
    $stmt2=$db->prepare("SELECT * FROM replies WHERE post_id=?");
    $stmt2->execute([$p['id']]);
    $p['replies']=$stmt2->fetchAll(PDO::FETCH_ASSOC);
  }

  return $posts;
}

// 썸네일 생성
function makeThumb($src,$dest,$size){
  $img=imagecreatefromstring(file_get_contents($src));
  $width=imagesx($img);
  $height=imagesy($img);

  $new_width=$size;
  $new_height=floor($height*($size/$width));

  $tmp=imagecreatetruecolor($new_width,$new_height);
  imagecopyresampled($tmp,$img,0,0,0,0,$new_width,$new_height,$width,$height);

  imagejpeg($tmp,$dest,80);
}

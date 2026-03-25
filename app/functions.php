<?php
require 'db.php';
function getPosts($board){
  $posts = loadPosts();
  return array_values(array_filter($posts, fn($p)=>$p['board']==$board));
}
function addPost($board, $content){
  $posts = loadPosts();
  $posts[] = [
    'id'=>time(),
    'board'=>$board,
    'content'=>$content,
    'replies'=>[]
  ];
  savePosts($posts);
}
function addReply($postId, $content){
  $posts = loadPosts();
  foreach($posts as &$p){
    if($p['id']==$postId){
      $p['replies'][] = ['id'=>time(),'content'=>$content];
    }
  }
  savePosts($posts);
}

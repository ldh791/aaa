<?php

function loadData(){
  $file = __DIR__.'/../data.json';

  if(!file_exists($file)){
    file_put_contents($file, json_encode([]));
  }

  return json_decode(file_get_contents($file), true);
}

function saveData($data){
  file_put_contents(__DIR__.'/../data.json', json_encode($data, JSON_PRETTY_PRINT));
}

function addPost($board,$content,$image=null){
  $data = loadData();

  $id = time();

  $data[] = [
    'id'=>$id,
    'board'=>$board,
    'content'=>$content,
    'image'=>$image
  ];

  saveData($data);
}

function addReply($post_id,$content){
  $data = loadData();

  foreach($data as &$p){
    if($p['id'] == $post_id){
      if(!isset($p['replies'])) $p['replies']=[];
      $p['replies'][] = ['content'=>$content];
    }
  }

  saveData($data);
}

function getPosts($board){
  $data = loadData();

  $result = array_filter($data, fn($p)=>$p['board']==$board);

  return array_reverse($result);
}

function getReplies($post_id){
  $data = loadData();

  foreach($data as $p){
    if($p['id'] == $post_id){
      return $p['replies'] ?? [];
    }
  }

  return [];
}

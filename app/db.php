<?php
function loadPosts(){
  if(!file_exists(__DIR__.'/../storage.json')) return [];
  return json_decode(file_get_contents(__DIR__.'/../storage.json'), true);
}
function savePosts($posts){
  file_put_contents(__DIR__.'/../storage.json', json_encode($posts));
}

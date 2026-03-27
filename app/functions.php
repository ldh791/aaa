<?php

function loadPosts(){

$file = __DIR__ . "/../data/b.json";

if(!file_exists($file)){
    return [];
}

$json = file_get_contents($file);

$data = json_decode($json,true);

if(!$data){
    $data = [];
}

return $data;

}

function savePost($post){

$file = __DIR__ . "/../data/b.json";

$posts = loadPosts();

$posts[] = $post;

file_put_contents($file,json_encode($posts));

}

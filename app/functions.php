<?php

function data_path($board){
    return __DIR__."/../data/".$board.".json";
}

function load_posts($board){
    $file = data_path($board);
    if(!file_exists($file)) return [];
    return json_decode(file_get_contents($file),true);
}

function add_post($board,$content,$image){
    $posts = load_posts($board);
    $posts[]=[
        "content"=>$content,
        "image"=>$image,
        "time"=>time()
    ];
    file_put_contents(data_path($board),json_encode($posts));
}

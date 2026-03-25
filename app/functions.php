<?php

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

<?php
// Placeholder location photos: 2400x1600 JPEGs, labeled so nobody mistakes them for real photos.
$out = $argv[1];
$items = ['eastside-pantry'=>'Eastside Pantry','northgate-pantry'=>'Northgate Pantry','riverside-pantry'=>'Riverside Pantry','southfield-pantry'=>'Southfield Pantry','community-kitchen'=>'Community Kitchen','millbrook-warehouse'=>'Millbrook Warehouse','mobile-pantry-van'=>'Mobile Pantry Van'];
$colors = [[47,123,177],[18,28,77],[91,155,213],[242,160,62],[60,120,90],[120,80,60],[90,90,140]];
$i = 0;
foreach ($items as $file => $label) {
  $w = 2400; $h = 1600; $im = imagecreatetruecolor($w, $h);
  [$r,$g,$b] = $colors[$i++];
  for ($y = 0; $y < $h; $y++) { $f = $y / $h; imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int)($r*(1-$f*.5)), (int)($g*(1-$f*.5)), (int)($b*(1-$f*.5)))); }
  // Shelves, so focal point and crops have something to show.
  $shelf = imagecolorallocatealpha($im, 255, 255, 255, 90);
  for ($s = 0; $s < 4; $s++) imagefilledrectangle($im, 200, 400 + $s*280, 2200, 430 + $s*280, $shelf);
  // A "person" on the left third, for the focal point exercise.
  $p = imagecolorallocate($im, 250, 230, 200);
  imagefilledellipse($im, 700, 620, 220, 220, $p);
  imagefilledrectangle($im, 600, 740, 800, 1200, $p);
  $white = imagecolorallocate($im, 255, 255, 255);
  $font = 5; $scale = 6;
  $txt = imagecreatetruecolor(imagefontwidth($font)*strlen("PLACEHOLDER: $label"), imagefontheight($font));
  imagefill($txt, 0, 0, imagecolorallocate($txt, 0, 0, 0)); imagecolortransparent($txt, 0);
  imagestring($txt, $font, 0, 0, "PLACEHOLDER: $label", imagecolorallocate($txt, 255, 255, 255));
  imagecopyresized($im, $txt, 120, 1380, 0, 0, imagesx($txt)*$scale, imagesy($txt)*$scale, imagesx($txt), imagesy($txt));
  imagejpeg($im, "$out/$file.jpg", 85);
  echo "$file.jpg\n";
}

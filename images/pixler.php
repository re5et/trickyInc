<?php
    $p = imagecreatetruecolor(1, 1);
    imagesavealpha($p, true);
	$o = (int) (isset($_GET['o'])) ? $_GET['o'] : 0;
	$o = (100 - $o) * 1.27;
    $c = imagecolorallocatealpha($p, (int) (isset($_GET['r'])) ? $_GET['r'] : 0, (int) (isset($_GET['g'])) ? $_GET['g'] : 0, (int) (isset($_GET['b'])) ? $_GET['b'] : 0, $o);
    imagefill($p, 0, 0, $c);
    header("Content-type: image/png");
    imagepng($p);
?>
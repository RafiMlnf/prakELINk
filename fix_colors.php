<?php
$f = 'c:\xampp\htdocs\ELINA\assets\css\style.css';
$css = file_get_contents($f);
$css = str_replace('16, 185, 129', '0, 184, 76', $css);
$css = str_replace('239, 68, 68', '230, 25, 25', $css);
$css = str_replace('#ef4444', '#e61919', $css);
$css = str_replace('#10b981', '#00b84c', $css);
file_put_contents($f, $css);
echo "Done replacing RGBA colors.\n";

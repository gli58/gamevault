<?php
// ⭐ captcha.php 

session_start();

// Generate random captcha code
$captcha_code = '';
$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid confusing chars like I,1,O,0
for ($i = 0; $i < 5; $i++) {
    $captcha_code .= $characters[rand(0, strlen($characters) - 1)];
}

// Store in session for verification
$_SESSION['captcha_code'] = $captcha_code;

// Create image
$width = 120;
$height = 40;
$image = imagecreate($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 255, 255, 255); // White background
$text_color = imagecolorallocate($image, 0, 0, 0);     // Black text
$noise_color = imagecolorallocate($image, 100, 100, 100); // Gray noise

// Add background noise
for ($i = 0; $i < 50; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
}

// Add noise lines
for ($i = 0; $i < 5; $i++) {
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $noise_color);
}

// Add text with random positioning and rotation
$font_size = 5; // Built-in font size (1-5)
$x_start = 10;
$y_position = 15;

for ($i = 0; $i < strlen($captcha_code); $i++) {
    $x_pos = $x_start + ($i * 20) + rand(-3, 3);
    $y_pos = $y_position + rand(-5, 5);
    
    imagestring($image, $font_size, $x_pos, $y_pos, $captcha_code[$i], $text_color);
}

// Output image
header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

imagepng($image);
imagedestroy($image);
?>
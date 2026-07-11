<?php
/**
 * 验证码生成器
 * 生成图形验证码图片，并将验证码文字存入会话
 */

session_start();

// 验证码配置
$width = 140;
$height = 48;
$length = 4;
$font_size = 22;

// 可用字符（去掉易混淆的 0/O/1/l/I）
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';

// 生成随机验证码
$code = '';
$max = strlen($chars) - 1;
for ($i = 0; $i < $length; $i++) {
    $code .= $chars[random_int(0, $max)];
}
$_SESSION['captcha_code'] = strtolower($code);

// 创建图片
$image = imagecreatetruecolor($width, $height);

// 背景色（浅色随机）
$bgColor = imagecolorallocate($image, random_int(230, 255), random_int(230, 255), random_int(230, 255));
imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $bgColor);

// 绘制干扰线
for ($i = 0; $i < 6; $i++) {
    $lineColor = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
    imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
}

// 绘制干扰点
for ($i = 0; $i < 80; $i++) {
    $dotColor = imagecolorallocate($image, random_int(160, 230), random_int(160, 230), random_int(160, 230));
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
}

// 绘制验证码字符
$charWidth = ($width - 20) / $length;
for ($i = 0; $i < $length; $i++) {
    $textColor = imagecolorallocate($image, random_int(20, 100), random_int(20, 100), random_int(20, 100));
    $x = 12 + $i * $charWidth + random_int(-3, 3);
    $y = ($height + $font_size) / 2 + random_int(-4, 4);
    $angle = random_int(-15, 15);
    // 使用内置字体
    imagestring($image, 5, $x, $y - 14, $code[$i], $textColor);
}

// 输出图片
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image);

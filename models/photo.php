<?php

class ImageProcessor
{
    /**
     * Resize an image to specified dimensions
     */
    public static function resize($file_path, $width, $height)
    {
        if (!file_exists($file_path) || !extension_loaded('gd')) {
            return false;
        }

        $image_info = getimagesize($file_path);
        $image_type = $image_info[2];

        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($file_path);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file_path);
                break;
            default:
                return false;
        }

        $new_image = imagecreatetruecolor($width, $height);
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        $file_info = pathinfo($file_path);
        $new_file_path = $file_info['dirname'] . '/' . $file_info['filename'] . '_' . $width . 'x' . $height . '.' . $file_info['extension'];

        switch ($image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($new_image, $new_file_path, 90);
                break;
            case IMAGETYPE_GIF:
                imagegif($new_image, $new_file_path);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_image, $new_file_path, 9);
                break;
        }

        imagedestroy($image);
        imagedestroy($new_image);

        return $new_file_path;
    }

    /**
     * Apply a frame to a photo
     */
    public static function applyFrame($photo_path, $frame_path)
    {
        if (!file_exists($photo_path) || !file_exists($frame_path) || !extension_loaded('gd')) {
            return false;
        }

        $photo = imagecreatefromjpeg($photo_path);
        $frame = imagecreatefrompng($frame_path);

        $photo_width = imagesx($photo);
        $photo_height = imagesy($photo);

        $frame = imagescale($frame, $photo_width, $photo_height);

        $result = imagecreatetruecolor($photo_width, $photo_height);
        imagecopy($result, $photo, 0, 0, 0, 0, $photo_width, $photo_height);
        imagecopy($result, $frame, 0, 0, 0, 0, $photo_width, $photo_height);

        $file_info = pathinfo($photo_path);
        $new_file_path = $file_info['dirname'] . '/' . $file_info['filename'] . '_framed.' . $file_info['extension'];
        imagejpeg($result, $new_file_path, 90);

        imagedestroy($photo);
        imagedestroy($frame);
        imagedestroy($result);

        return $new_file_path;
    }

    /**
     * Apply a filter to a photo
     */
    public static function applyFilter($photo_path, $filter_type, $value = 50)
    {
        if (!file_exists($photo_path) || !extension_loaded('gd')) {
            return false;
        }

        $image = imagecreatefromjpeg($photo_path);
        if (!$image) {
            return false;
        }

        switch (strtolower($filter_type)) {
            case 'brightness':
                imagefilter($image, IMG_FILTER_BRIGHTNESS, ($value - 50) * 2); // Scale to -100 to +100
                break;

            case 'contrast':
                imagefilter($image, IMG_FILTER_CONTRAST, 50 - $value); // Scale to -50 to +50
                break;

            case 'saturation':
                // Custom saturation adjustment
                $hsv = self::rgbToHsv($image);
                $value = ($value / 100) * 2; // Scale to 0-2
                for ($x = 0; $x < imagesx($image); $x++) {
                    for ($y = 0; $y < imagesy($image); $y++) {
                        $rgb = imagecolorat($image, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $hsv = self::rgbToHsv($r, $g, $b);
                        $hsv[1] *= $value; // Adjust saturation
                        if ($hsv[1] > 1) $hsv[1] = 1;
                        $rgb = self::hsvToRgb($hsv[0], $hsv[1], $hsv[2]);
                        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
                        imagesetpixel($image, $x, $y, $color);
                    }
                }
                break;

            case 'black_white':
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                break;

            case 'sepia':
            case 'vintage':
                if (defined('IMG_FILTER_SEPIA') && PHP_VERSION_ID >= 70400) {
                    imagefilter($image, IMG_FILTER_SEPIA);
                } else {
                    // Fallback sepia effect for older PHP versions
                    imagefilter($image, IMG_FILTER_GRAYSCALE);
                    imagefilter($image, IMG_FILTER_COLORIZE, 90, 60, 40);
                }
                break;

            default:
                imagedestroy($image);
                return $photo_path; // No filter applied
        }

        $file_info = pathinfo($photo_path);
        $new_file_path = $file_info['dirname'] . '/' . $file_info['filename'] . '_' . $filter_type . '.' . $file_info['extension'];
        imagejpeg($image, $new_file_path, 90);

        imagedestroy($image);
        return $new_file_path;
    }

    /**
     * Generate a PDF for a photobook
     */
    public static function generatePDF($photobook_id, $photos)
    {
        if (!class_exists('TCPDF')) {
            require_once 'vendor/autoload.php';
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('PixelMemories');
        $pdf->SetTitle('Photobook');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);

        foreach ($photos as $photo) {
            $pdf->AddPage();
            $image_width = 190; // A4 width minus margins
            $image_height = 0;

            // Get image dimensions
            if (file_exists($photo['file_path'])) {
                list($width, $height) = getimagesize($photo['file_path']);
                $image_height = ($height / $width) * $image_width;
                $pdf->Image($photo['file_path'], 10, 10, $image_width, $image_height, '', '', '', true, 300);
            }

            if (!empty($photo['caption'])) {
                $pdf->SetY($image_height + 15);
                $pdf->SetFont('helvetica', '', 12);
                $pdf->MultiCell(190, 10, $photo['caption'], 0, 'C');
            }
        }

        $output_path = 'uploads/photobooks/photobook_' . $photobook_id . '.pdf';
        $pdf->Output($output_path, 'F');
        return $output_path;
    }

    /**
     * Convert RGB to HSV
     */
    private static function rgbToHsv($r, $g, $b)
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $v = $max;

        $delta = $max - $min;
        $s = $max == 0 ? 0 : $delta / $max;

        if ($delta == 0) {
            $h = 0;
        } else {
            switch ($max) {
                case $r:
                    $h = ($g - $b) / $delta;
                    break;
                case $g:
                    $h = 2 + ($b - $r) / $delta;
                    break;
                case $b:
                    $h = 4 + ($r - $g) / $delta;
                    break;
            }
            $h *= 60;
            if ($h < 0) $h += 360;
        }

        return [$h, $s, $v];
    }

    /**
     * Convert HSV to RGB
     */
    private static function hsvToRgb($h, $s, $v)
    {
        if ($s == 0) {
            $r = $g = $b = $v;
        } else {
            $h /= 60;
            $i = floor($h);
            $f = $h - $i;
            $p = $v * (1 - $s);
            $q = $v * (1 - $s * $f);
            $t = $v * (1 - $s * (1 - $f));

            switch ($i) {
                case 0:
                    $r = $v;
                    $g = $t;
                    $b = $p;
                    break;
                case 1:
                    $r = $q;
                    $g = $v;
                    $b = $p;
                    break;
                case 2:
                    $r = $p;
                    $g = $v;
                    $b = $t;
                    break;
                case 3:
                    $r = $p;
                    $g = $q;
                    $b = $v;
                    break;
                case 4:
                    $r = $t;
                    $g = $p;
                    $b = $v;
                    break;
                default:
                    $r = $v;
                    $g = $p;
                    $b = $q;
                    break;
            }
        }

        return [round($r * 255), round($g * 255), round($b * 255)];
    }
}

<?php

namespace Acme\Captcha;

class ImageCaptcha
{
    /** Width of the image */
    public $width = 120;

    /** Height of the image */
    public $height = 50;
    public $resourcesPath = '/resources';
    public $minWordLength = 5;
    public $maxWordLength = 5;

    /** Session name to store the original text */
    public $sessionVar = 'captcha';

    /** Background color in RGB-array */
    public $backgroundColor = array(255, 255, 255);

    public $lineColor = array(125, 125, 125);

    /** Foreground colors in RGB-array */
    public $textColors = array(
        array(27, 78, 181), // blue
        array(22, 163, 35), // green
        array(214, 36, 7), // red
        array(99, 0, 0),
        array(66, 66, 66),
    );

    /** Shadow color in RGB-array or null */
    public $shadowColor = null; //array(0, 0, 0);

    /** Horizontal line through the text */
    public $lineWidth = 0;

    /**
     * Font configuration
     *
     * - font: TTF file
     * - spacing: relative pixel space between character
     * - minSize: min font size
     * - maxSize: max font size
     */
    public $fonts = array(
        'Candice'  => array('spacing' =>- 1.5,'minSize' => 20, 'maxSize' => 24, 'font' => 'Candice.ttf'),
        'DingDong' => array('spacing' => -2, 'minSize' => 20, 'maxSize' => 24, 'font' => 'Ding-DongDaddyO.ttf'),
        'Times' => array('spacing' => -2, 'minSize' => 20, 'maxSize' => 26, 'font' => 'TimesNewRomanBold.ttf'),
        'VeraSans' => array('spacing' => -1, 'minSize' => 18, 'maxSize' => 22, 'font' => 'VeraSansBold.ttf'),
    );

    public $textColor = null; //array(0, 0, 0);

    /** Wave configuracion in X and Y axes */
    //public $Xperiod = 11;
    //public $Xamplitude = 5;
    //public $Yperiod = 12;
    //public $Yamplitude = 14;

    public $Xperiod = 9;
    public $Xamplitude = 5;
    public $Yperiod = 6;
    public $Yamplitude = 8;

    /** letter rotation clockwise */
    //public $maxRotation = 6;
    public $maxRotation = 2;

    /**
     * Internal image size factor (for better image quality)
     * 1: low, 2: medium, 3: high
     */
    public $scale = 2;

    /**
     * Blur effect for better image quality (but slower image processing).
     * Better image results with scale=3
     */
    public $blur = false;

    /** Debug? */
    public $debug = false;

    /** Image format: jpeg or png */
    public $imageFormat = 'png';

    /** GD image */
    public $img;
    
    public $randomText;

    public function __construct($randomText = '')
    {
        $this->resourcesPath = __DIR__ . $this->resourcesPath;
        
        if ($randomText) {
            $this->randomText = $randomText;
        } else {
            $this->randomText = $this->getRandomText();
        }
    }

    public function createImage()
    {
        $ini = microtime(true);

        /** Initialization */
        $this->imageAllocate();

        $this->waveImage(0, 100, 1, 3, 1, 2);
        
        /** Text insertion */
        $text = $this->randomText; //$this->getRandomText();
        $fontCfg = $this->fonts[array_rand($this->fonts)];
        $this->writeText($text, $fontCfg);

        //$_SESSION[$this->sessionVar] = $text;

        /** Transformations */
        if (!empty($this->lineWidth)) {
            $this->writeOverTextLine();
        }

        $this->waveImage(100, 100, 2, 3, 2, 2);
        
        if ($this->blur && function_exists('imagefilter')) {
            imagefilter($this->img, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $this->reduceImage();

        if ($this->debug) {
            imagestring($this->img, 1, 1, $this->height - 8, "$text {$fontCfg['font']} " . round((microtime(true) - $ini) * 1000) . "ms", $this->textColor);
        }
                
        /** Output */
        $this->writeImage();
        $this->cleanup();
    }

    /**
     * Creates the image resources
     */
    protected function imageAllocate() {
        // Cleanup
        if (!empty($this->img)) {
            imagedestroy($this->img);
        }

        $this->img = imagecreatetruecolor($this->width * $this->scale, $this->height * $this->scale);
        
        // Prepare alpha channel for transparent background
//        $alphaChannel = imagecolorallocatealpha($this->img, 0, 0, 0, 127); 
//        $this->img = imagecolortransparent($this->img, $alphaChannel); 

        // Background color
        $backgroundColor = imagecolorallocate($this->img, $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
        $this->gdBgColor = imagefilledrectangle($this->img, 0, 0, $this->width * $this->scale, $this->height * $this->scale, $backgroundColor);
        
        // Foreground color
        $textColor = $this->textColors[mt_rand(0, sizeof($this->textColors) - 1)];
        $this->textColor = imagecolorallocate($this->img, $textColor[0], $textColor[1], $textColor[2]);

        // Shadow color
        if (!empty($this->shadowColor) && is_array($this->shadowColor) && sizeof($this->shadowColor) >= 3) {
            $this->GdShadowColor = imagecolorallocate($this->img, $this->shadowColor[0], $this->shadowColor[1], $this->shadowColor[2]);
        }
    }

    protected function getRandomText($length = null)
    {
        if (empty($length)) {
            $length = rand($this->minWordLength, $this->maxWordLength);
        }

        $words = 'abcdefghijlmnopqrstvwyz123456789'; //ABCDEFGHIJKLMNOPQRSTUVXYZ";
        $vocals = 'aeiou';

        $text = '';
        $vocal = rand(0, 1);
        for ($i = 0; $i < $length; $i++) {
            if ($vocal) {
                $text .= substr($vocals, mt_rand(0, strlen($vocals) - 1), 1);
            } else {
                $text .= substr($words, mt_rand(0, strlen($words) - 1), 1);
            }
            $vocal = !$vocal;
        }
        return $text;
    }

    /**
     * Text insertion
     */
    protected function writeText($text, $fontcfg = array())
    {
        if (empty($fontcfg)) {
            // Select the font configuration
            $fontcfg = $this->fonts[array_rand($this->fonts)];
        }

        // Full path of font file
        $fontfile = $this->resourcesPath . '/fonts/' . $fontcfg['font'];

        /** Increase font-size for shortest words: 9% for each glyp missing */
        $lettersMissing = $this->maxWordLength - strlen($text);
        $fontSizefactor = 1 + ($lettersMissing * 0.09);

        // Text generation (char by char)
        $x = 8 * $this->scale;
        $y = round(($this->height * 27 / 40) * $this->scale);
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $degree = rand($this->maxRotation * -1, $this->maxRotation);
            $fontsize = rand($fontcfg['minSize'], $fontcfg['maxSize']) * $this->scale * $fontSizefactor;
            $letter = substr($text, $i, 1);

            if ($this->shadowColor) {
                $coords = imagettftext($this->img, $fontsize, $degree, $x + $this->scale, $y + $this->scale, $this->GdShadowColor, $fontfile, $letter);
            }
            $coords = imagettftext($this->img, $fontsize, $degree, $x, $y, $this->textColor, $fontfile, $letter);
            $x += ($coords[2] - $x) + ($fontcfg['spacing'] * $this->scale);
        }

        $this->textFinalX = $x;
    }

    /**
     * Wave filter
     */
    protected function waveImage($kRnd1 = 0, $kRnd2 = 100, $xRnd1 = 1, $xRnd2 = 3, $yRnd1 = 1, $yRnd2 = 3)
    {
        // X-axis wave generation
        $xp = $this->scale * $this->Xperiod * rand($xRnd1, $xRnd2);
        $k = rand($kRnd1, $kRnd2);
        for ($i = 0; $i < ($this->width * $this->scale); $i++) {
            imagecopy($this->img, $this->img, $i - 1, sin($k + $i / $xp) * ($this->scale * $this->Xamplitude), $i, 0, 1, $this->height * $this->scale);
        }

        // Y-axis wave generation
        $k = rand($kRnd1, $kRnd2);
        $yp = $this->scale * $this->Yperiod * rand($yRnd1, $yRnd2);
        for ($i = 0; $i < ($this->height * $this->scale); $i++) {
            imagecopy($this->img, $this->img, sin($k + $i / $yp) * ($this->scale * $this->Yamplitude), $i - 1, 0, $i, $this->width * $this->scale, 1);
        }
    }
    
    /**
     * Line over the text
     */
    protected function writeOverTextLine()
    {
        $x1 = $this->width * $this->scale * 0.15;
        $x2 = $this->textFinalX;
        $y1 = rand($this->height * $this->scale * 0.40, $this->height * $this->scale * 0.65);
        $y2 = rand($this->height * $this->scale * 0.40, $this->height * $this->scale * 0.65);
        $width = $this->lineWidth / 2 * $this->scale;

        for ($i = $width * -1; $i <= $width; $i++) {
            imageline($this->img, $x1, $y1 + $i, $x2, $y2 + $i, $this->textColor);
        }
    }
    
    /**
     * Grid on the background
     */
    protected function writeLines()
    {
        $lineColor = imagecolorallocate($this->img, $this->lineColor[0], $this->lineColor[1], $this->lineColor[2]);
        imagesetthickness($this->img, 1);

        //vertical lines
        for ($x = 0; $x < $this->width; $x += 10) {
            imageline($this->img, $x, 0, $x, $this->height, $lineColor);
        }
        imageline($this->img, $this->width - 1, 0, $this->width - 1, $this->height, $lineColor);

        //horizontal lines
        for ($y = 0; $y < $this->height; $y += 10) {
            imageline($this->img, 0, $y, $this->width, $y, $lineColor);
        }

        imageline($this->img, 0, $this->height -1, $this->width, $this->height -1, $lineColor);
    }

    /**
     * Reduce the image to the final size
     */
    protected function reduceImage()
    {
        $imgResampled = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($imgResampled, $this->img, 0, 0, 0, 0, $this->width, $this->height, $this->width * $this->scale, $this->height * $this->scale);
        imagedestroy($this->img);
        
//        $imgBackground = imagecreatetruecolor($this->width, $this->height);
//        $this->img = $imgBackground;
//        $backgroundColor = imagecolorallocate($this->img, $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
//        $this->gdBgColor = imagefilledrectangle($this->img, 0, 0, $this->width * $this->scale, $this->height * $this->scale, $backgroundColor);
//        $this->writeLines();
        
        $this->img = $imgResampled;
    }

    /**
     * File generation
     */
    protected function writeImage()
    {
        if ($this->imageFormat == 'png' && function_exists('imagepng')) {
            header("Content-type: image/png");
            imagepng($this->img);
        } else {
            header("Content-type: image/jpeg");
            imagejpeg($this->img, null, 80);
        }
    }

    /**
     * Cleanup
     */
    protected function cleanup()
    {
        imagedestroy($this->img);
    }

}

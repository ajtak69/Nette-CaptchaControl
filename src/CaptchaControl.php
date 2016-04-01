<?php
/**
 * Captcha Control
 *
 * @author    Ing. Radek Dostál, Ph.D. <radek.dostal@gmail.com>
 * @copyright Copyright (c) 2016 Radek Dostál
 * @author    Pavel Máca <http://www.inseo.cz>
 * @copyright Copyright (c) 2010 Pavel Máca
 * @license   MIT License
 * @link      http://www.radekdostal.cz
 */

namespace RadekDostal\NetteComponents;

use Nette;
use Nette\Forms\Container;
use Nette\Forms\Controls\HiddenField;
use Nette\Forms\Controls\TextBase;
use Nette\Forms\Form;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Http\Session;
use Nette\Utils\Html;
use Nette\Utils\Image;

/**
 * Generates image with text as label for text input
 *
 * @author Radek Dostál
 * @author Pavel Máca
 */
class CaptchaControl extends TextBase
{
  /** #@+ Character groups */
  const CONSONANTS = 'bcdfghjkmnpqrstvwxz';  // not 'l'
  const VOWELS = 'aeiuy';  // not 'o'
  const NUMBERS = '23456789';  // not '0' and '1'
  /** #@- */

  /**
   * Default font file
   *
   * @var string
   */
  public static $defaultFontFile;

  /**
   * Default font size
   *
   * @var int
   */
  public static $defaultFontSize = 30;

  /**
   * Default text margin in px
   *
   * @var int
   */
  public static $defaultTextMargin = 25;

  /**
   * Default text color (from \Nette\Utils\Image::rgb())
   *
   * @var array
   */
  public static $defaultTextColor = array(
    'red' => 0,
    'green' => 0,
    'blue' => 0
  );

  /**
   * Default background color (from \Nette\Utils\Image::rgb())
   *
   * @var array
   */
  public static $defaultBackgroundColor = array(
    'red' => 255,
    'green' => 255,
    'blue' => 255
  );

  /**
   * Default word length
   *
   * @var int
   */
  public static $defaultLength = 5;

  /**
   * Default image width in px
   *
   * @var int
   */
  public static $defaultImageWidth = 0;

  /**
   * Default image height in px
   *
   * @var int
   */
  public static $defaultImageHeight = 0;

  /**
   * Default filter smooth
   *
   * @var int|bool
   */
  public static $defaultFilterSmooth = 1;

  /**
   * Default filter contrast
   *
   * @var int|bool
   */
  public static $defaultFilterContrast = -60;

  /**
   * Default session expire time in seconds
   *
   * @var int
   */
  public static $defaultExpire = 10800;  // 3 hours

  /**
   * Default use numbers
   *
   * @var bool
   */
  public static $defaultUseNumbers = TRUE;

  /**
   * Control registered state
   *
   * @var bool
   */
  private static $registered = FALSE;

  /**
   * Session
   *
   * @var \Nette\Http\Session
   */
  private static $session;

  /**
   * Font file
   *
   * @var string
   */
  private $fontFile;

  /**
   * Font size
   *
   * @var int
   */
  private $fontSize;

  /**
   * Text margin in px
   *
   * @var int
   */
  private $textMargin;

  /**
   * Text color (from \Nette\Utils\Image::rgb())
   *
   * @var array
   */
  private $textColor;

  /**
   * Background color (from \Nette\Utils\Image::rgb())
   *
   * @var array
   */
  private $backgroundColor;

  /**
   * Word length
   *
   * @var int
   */
  private $length;

  /**
   * Image width in px
   *
   * @var int
   */
  private $imageWidth;

  /**
   * Image height in px
   *
   * @var int
   */
  private $imageHeight;

  /**
   * Filter smooth
   *
   * @var int|bool
   */
  private $filterSmooth;

  /**
   * Filter contrast
   *
   * @var int|bool
   */
  private $filterContrast;

  /**
   * Unique ID
   *
   * @var int unique ID
   */
  private $uid;

  /**
   * Word
   *
   * @var string
   */
  private $word;

  /**
   * Session expire time in seconds
   *
   * @var int
   */
  private $expire;

  /**
   * Use numbers in word?
   *
   * @var bool
   */
  private $useNumbers;

  /**
   * Initialization
   *
   * @throws \Exception
   */
  public function __construct()
  {
    if (extension_loaded('gd') === FALSE)
      throw new \Exception('PHP extension GD is not loaded.');

    parent::__construct();

    $this->addFilter('strtolower');

    $this->label = Html::el('img');

    $this->setFontFile(self::$defaultFontFile);
    $this->setFontSize(self::$defaultFontSize);
    $this->setTextColor(self::$defaultTextColor);
    $this->setTextMargin(self::$defaultTextMargin);
    $this->setBackgroundColor(self::$defaultBackgroundColor);
    $this->setLength(self::$defaultLength);
    $this->setImageHeight(self::$defaultImageHeight);
    $this->setImageWidth(self::$defaultImageWidth);
    $this->setFilterSmooth(self::$defaultFilterSmooth);
    $this->setFilterContrast(self::$defaultFilterContrast);
    $this->setExpire(self::$defaultExpire);
    $this->useNumbers(self::$defaultUseNumbers);

    $this->setUid(uniqid());
  }

  /**
   * Registers CaptchaControl to the \Nette\Forms\Container, starts session and sets $defaultFontFile (if not set)
   *
   * @param \Nette\Http\Session $session session
   * @throws \Nette\InvalidStateException
   * @return void
   */
  public static function register(Session $session)
  {
    if (self::$registered === TRUE)
      throw new InvalidStateException(__CLASS__.' is already registered.');

    if ($session->isStarted() === FALSE)
      $session->start();

    self::$session = $session->getSection(__CLASS__);

    if (!self::$defaultFontFile)
      self::$defaultFontFile = __DIR__.'/fonts/Vera.ttf';

    Container::extensionMethod('addCaptcha', function($container, $name)
    {
      return $container[$name] = new static;
    });

    self::$registered = TRUE;
  }

  /**
   * Sets path to font file
   *
   * @param string $path path to font file
   * @throws \Nette\InvalidArgumentException
   * @return self
   */
  public function setFontFile($path)
  {
    if (!empty($path) && file_exists($path) === TRUE)
      $this->fontFile = $path;
    else
      throw new InvalidArgumentException('Font file "'.$path.'" not found.');

    return $this;
  }

  /**
   * Gets path to font file
   *
   * @return string
   */
  public function getFontFile()
  {
    return $this->fontFile;
  }

  /**
   * Sets word length
   *
   * @param int $length length
   * @return self
   */
  public function setLength($length)
  {
    $this->length = (int) $length;

    return $this;
  }

  /**
   * Gets word length
   *
   * @return int
   */
  public function getLength()
  {
    return $this->length;
  }

  /**
   * Sets font size
   *
   * @param int $size size
   * @return self
   */
  public function setFontSize($size)
  {
    $this->fontSize = (int) $size;

    return $this;
  }

  /**
   * Gets font size
   *
   * @return int
   */
  public function getFontSize()
  {
    return $this->fontSize;
  }

  /**
   * Sets text color
   *
   * @param array $rgb [red => 0-255, green => 0-255, blue => 0-255]
   * @throws \Nette\InvalidArgumentException
   * @return self
   */
  public function setTextColor(array $rgb)
  {
    if (!isset($rgb['red']) || !isset($rgb['green']) || !isset($rgb['blue']))
      throw new InvalidArgumentException('TextColor must be valid RGB array, see Nette\Utils\Image::rgb().');

    $this->textColor = Image::rgb($rgb['red'], $rgb['green'], $rgb['blue']);

    return $this;
  }

  /**
   * Gets text color
   *
   * @return array
   */
  public function getTextColor()
  {
    return $this->textColor;
  }

  /**
   * Sets text margin
   *
   * @param int $margin margin
   * @return self
   */
  public function setTextMargin($margin)
  {
    $this->textMargin = (int) $margin;

    return $this;
  }

  /**
   * Gets text margin
   *
   * @return int
   */
  public function getTextMargin()
  {
    return $this->textMargin;
  }

  /**
   * Sets background color
   *
   * @param array $rgb [red 0-255, green 0-255, blue 0-255]
   * @throws \Nette\InvalidArgumentException
   * @return self
   */
  public function setBackgroundColor(array $rgb)
  {
    if (!isset($rgb['red']) || !isset($rgb['green']) || !isset($rgb['blue']))
      throw new InvalidArgumentException('BackgroundColor must be valid RGB array, see Nette\Utils\Image::rgb().');

    $this->backgroundColor = Image::rgb($rgb['red'], $rgb['green'], $rgb['blue']);

    return $this;
  }

  /**
   * Gets background color
   *
   * @return array
   */
  public function getBackgroundColor()
  {
    return $this->backgroundColor;
  }

  /**
   * Sets image height
   *
   * @param int $height height
   * @return self
   */
  public function setImageHeight($height)
  {
    $this->imageHeight = (int) $height;

    return $this;
  }

  /**
   * Gets image height
   *
   * @return int
   */
  public function getImageHeight()
  {
    return $this->imageHeight;
  }

  /**
   * Sets image width
   *
   * @param int $width width
   * @return self
   */
  public function setImageWidth($width)
  {
    $this->imageWidth = (int) $width;

    return $this;
  }

  /**
   * Gets image width
   *
   * @return int
   */
  public function getImageWidth()
  {
    return $this->imageWidth;
  }

  /**
   * Sets filter smooth
   *
   * @param int|bool $smooth smooth
   * @return self
   */
  public function setFilterSmooth($smooth)
  {
    $this->filterSmooth = $smooth;

    return $this;
  }

  /**
   * Gets filter smooth
   *
   * @return int|bool
   */
  public function getFilterSmooth()
  {
    return $this->filterSmooth;
  }

  /**
   * Sets filter contrast
   *
   * @param int|bool $contrast contrast
   * @return self
   */
  public function setFilterContrast($contrast)
  {
    $this->filterContrast = $contrast;

    return $this;
  }

  /**
   * Gets filter contrast
   *
   * @return int|bool
   */
  public function getFilterContrast()
  {
    return $this->filterContrast;
  }

  /**
   * Sets session expiration time
   *
   * @param int $expire
   * @return self
   */
  public function setExpire($expire)
  {
    $this->expire = (int) $expire;

    return $this;
  }

  /**
   * Gets session expiration time
   *
   * @return int
   */
  public function getExpire()
  {
    return $this->expire;
  }

  /**
   * Use numbers in captcha image?
   *
   * @param bool $useNumbers use numbers?
   * @return self
   */
  public function useNumbers($useNumbers = TRUE)
  {
    $this->useNumbers = (bool) $useNumbers;

    return $this;
  }

  /**
   * Generates label's HTML element
   *
   * @param string $caption caption
   * @return \Nette\Utils\Html
   */
  public function getLabel($caption = NULL)
  {
    $this->setSession($this->getUid(), $this->getWord());

    $image = clone $this->label;
    $image->src = $this->getImageUri();

    if (!isset($image->alt))
      $image->alt = 'Captcha';

    return $image;
  }

  /**
   * Generates control's HTML element
   *
   * @return \Nette\Utils\Html|string
   */
  public function getControl()
  {
    // TODO Make sure captcha is validated at this time
    $parent = $this->getParent();
    $parent[$this->getUidFieldName()]->setValue($this->getUid());

    return parent::getControl();
  }

  /**
   * Validates control (don't call directly)
   *
   * @param CaptchaControl $control Captcha Control
   * @throws \Nette\InvalidStateException
   * @return bool
   */
  public function validateCaptcha(CaptchaControl $control)
  {
    $parent = $control->getParent();
    $uidFieldName = $control->getUidFieldName();

    if (!isset($parent[$uidFieldName]))
      throw new InvalidStateException('Can\'t find '.__CLASS__.' uid field '.$uidFieldName.' in parent.');

    $uid = $parent[$uidFieldName]->getValue();
    $sessionValue = $control->getSession($uid);

    $control->unsetSession($uid);

    return ($sessionValue === $control->getValue());
  }

  /**
   * Gets validator
   *
   * @return Nette\Callback
   */
  public function getValidator()
  {
    return callback($this, 'validateCaptcha');
  }

  /**
   * This method will be called when the component (or component's parent)
   * becomes attached to a monitored object. Do not call this method yourself.
   *
   * @param \Nette\ComponentModel\IComponent $form form
   * @return void
   */
  protected function attached($form)
  {
    parent::attached($form);

    if ($form instanceof Form)
      $form[$this->getUidFieldName()] = new HiddenField();
  }

  /**
   * Gets image URI
   *
   * @return string
   */
  protected function getImageUri()
  {
    return 'data:image/png;base64,'.base64_encode($this->getImageData());
  }

  /**
   * Draws captcha image
   *
   * @return string
   */
  protected function getImageData()
  {
    $word = $this->getWord();
    $font = $this->getFontFile();
    $size = $this->getFontSize();
    $textColor = $this->getTextColor();
    $bgColor = $this->getBackgroundColor();

    $box = $this->getDimensions();
    $width = $this->getImageWidth();
    $height = $this->getImageHeight();

    $first = Image::fromBlank($width, $height, $bgColor);
    $second = Image::fromBlank($width, $height, $bgColor);

    $x = ($width - $box['width']) / 2;
    $y = ($height + $box['height']) / 2;

    $first->fttext($size, 0, $x, $y, $textColor, $font, $word);

    $frequency = $this->getRandom(0.05, 0.1);
    $amplitude = $this->getRandom(2, 4);
    $phase = $this->getRandom(0, 6);

    for ($x = 0; $x < $width; $x++)
    {
      for ($y = 0; $y < $height; $y++)
      {
        $sy = round($y + sin($x * $frequency + $phase) * $amplitude);
        $sx = round($x + sin($y * $frequency + $phase) * $amplitude);

        $color = $first->colorat($x, $y);
        $second->setpixel($sx, $sy, $color);
      }
    }

    $first->destroy();

    if (defined('IMG_FILTER_SMOOTH') === TRUE)
      $second->filter(IMG_FILTER_SMOOTH, $this->getFilterSmooth());

    if (defined('IMG_FILTER_CONTRAST') === TRUE)
      $second->filter(IMG_FILTER_CONTRAST, $this->getFilterContrast());

    ob_start();
    imagepng($second->getImageResource());
    $contents = ob_get_contents();
    ob_end_clean();

    return $contents;
  }

  /**
   * Sets unique ID
   *
   * @param int $uid unique ID
   * @return void
   */
  private function setUid($uid)
  {
    $this->uid = $uid;
  }

  /**
   * Gets unique ID
   *
   * @return int
   */
  protected function getUid()
  {
    return $this->uid;
  }

  /**
   * Sets session
   *
   * @param string $uid unique ID
   * @param string $word word
   * @throws \Nette\InvalidStateException
   * @return void
   */
  private function setSession($uid, $word)
  {
    if (!self::$session)
      throw new InvalidStateException(__CLASS__.' session not found.');

    self::$session->$uid = $word;
    self::$session->setExpiration($this->getExpire());
  }

  /**
   * Gets session
   *
   * @param string $uid unique ID
   * @throws \Nette\InvalidStateException
   * @return string|bool return false if key not found
   */
  private function getSession($uid)
  {
    if (!self::$session)
      throw new InvalidStateException(__CLASS__.' session not found.');

    return isset(self::$session[$uid]) ? self::$session[$uid] : FALSE;
  }

  /**
   * Unsets session key
   *
   * @param string $uid unique ID
   * @return void
   */
  private function unsetSession($uid)
  {
    if (self::$session && isset(self::$session[$uid]))
      unset(self::$session[$uid]);
  }

  /**
   * Gets unique ID field name
   *
   * @return string
   */
  private function getUidFieldName()
  {
    return '_uid_'.$this->getName();
  }

  /**
   * Gets or generates random word for image
   *
   * @return string
   */
  private function getWord()
  {
    if (!$this->word)
    {
      $s = '';

      for ($i = 0; $i < $this->getLength(); $i++)
      {
        if ($this->useNumbers === TRUE && mt_rand(0, 10) % 3 === 0)
        {
          $group = self::NUMBERS;
          $s .= $group{mt_rand(0, strlen($group) - 1)};
          continue;
        }

        $group = $i % 2 === 0 ? self::CONSONANTS : self::VOWELS;
        $s .= $group{mt_rand(0, strlen($group) - 1)};
      }

      $this->word = $s;
    }

    return $this->word;
  }

  /**
   * Detects image dimensions and returns image text bounding box
   *
   * @return array
   */
  private function getDimensions()
  {
    $box = imagettfbbox($this->getFontSize(), 0, $this->getFontFile(), $this->getWord());
    $box['width'] = $box[2] - $box[0];
    $box['height'] = $box[3] - $box[5];

    if ($this->getImageWidth() === 0)
      $this->setImageWidth($box['width'] + $this->getTextMargin());

    if ($this->getImageHeight() === 0)
      $this->setImageHeight($box['height'] + $this->getTextMargin());

    return $box;
  }

  /**
   * Returns a random number within the specified range
   *
   * @param float $min lowest value
   * @param float $max highest value
   * @return float
   */
  private function getRandom($min, $max)
  {
    return mt_rand() / mt_getrandmax() * ($max - $min) + $min;
  }
}
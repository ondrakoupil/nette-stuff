<?php

namespace OndraKoupil\Images;

/**
 * Pastes an image into another. Useful for watermarks.
 * See setup() and set*() methods, how you can specify size and/or position of pasted image.
 * @see setup()
 * @see setPosition()
 * @see setCenterPosition()
 * @see setSize()
 * @see setAlpha()
 * @author Ondřej Koupil koupil@animato.cz
 * @todo Tests for PasteTransformation
 */
class PasteTransformation extends Transformation {

	const LEFT = 1;
	const TOP = 1;
	const CENTER = 2;
	const MIDDLE = 2;
	const RIGHT = 3;
	const BOTTOM = 3;

	/**
	 * @var ImageResource
	 */
	protected $image;

	protected $isImageProcessed;

	protected $positionX,$positionY,$positionModeX,$positionModeY;
	protected $sizeW,$sizeH,$sizeMode,$alpha;
	protected $transformations;

	public function getSignature() {
		$base="paste:".$this->positionX.":"
			.$this->positionY.":"
			.$this->positionModeX.":"
			.$this->positionModeY.":"
			.$this->sizeW.":"
			.$this->sizeH.":"
			.$this->sizeMode.":"
			.$this->alpha.":";
		if ($this->image) {
			$base.="image=".$this->image->getSignature().":";
		}
		if ($this->transformations) {
			foreach($this->transformations as $tr) {
				$base.=$tr->getSignature().":";
			}
		}
		return md5($base);
	}

	public function apply(ImageResource $image) {
		if (!$this->image) throw new \Exception("An image to paste must be specified before applying PasteTransformation.");
		$this->preprocessPastedImage();

		$mainW=$image->getWidth();
		$mainH=$image->getHeight();

		$doResize=false;


		if ($this->sizeW) {
			$pastedWidth=ImageResource::parseSize($this->sizeW, $mainW );
			$doResize=true;
		} else {
			$pastedWidth=null;
		}

		if ($this->sizeH) {
			$pastedHeight=ImageResource::parseSize($this->sizeH, $mainH );
			$doResize=true;
		} else {
			$pastedHeight=null;
		}

		if ($doResize) {
			$resize=new ResizeTransformation($pastedWidth, $pastedHeight, $this->sizeMode, null, false);
			$pastedImage=$resize->applyCopy($this->image);
		} else {
			$pastedImage=$this->image;
		}

		$pastedWidth=$pastedImage->getWidth();
		$pastedHeight=$pastedImage->getHeight();

		$posX=$this->calculatePosition($this->positionX,$this->positionModeX,$mainW,$pastedWidth);
		$posY=$this->calculatePosition($this->positionY,$this->positionModeY,$mainH,$pastedHeight);

		$res=$image->getResource();
		imagealphablending($res, true);
		imagecopy($res, $pastedImage->getResource(), $posX, $posY, 0, 0, $pastedWidth, $pastedHeight );
		imagealphablending($res, false);

		$image->addToSignature($this->getSignature());

		return $image;
	}

	public function reset() {
		$this->positionX="50%";
		$this->positionY="50%";
		$this->positionModeX=self::CENTER;
		$this->positionModeY=self::CENTER;
		$this->sizeW="100%";
		$this->sizeH="100%";
		$this->sizeMode=ResizeTransformation::FIT;
		$this->alpha=1;
		$this->transformations=array();
		$this->image=null;
		$this->isImageProcessed=false;
		return $this;
	}

	/**
	 * Can call setup() when initialising.
	 * @param string|ImageResource|resource $image
	 * @param mixed $width
	 * @param mixed $height
	 * @param mixed $alpha
	 * @see setup()
	 */
	public function __construct($image=null,$width=null,$height=null,$alpha=1) {
		if ($image) {
			$this->setup($image,$width,$height,$alpha);
		} else {
			$this->reset();
		}
	}

	/**
	 * Most common pasting can be setup using this method. Image will be pasted
	 * to center, optionally resized to $width, $height using FIT mode.
	 * @param ImageResource|resource|string $image Image to be pasted, see setImage()
	 * @param mixed $width Width of pasted image, see setSize()
	 * @param mixed $height
	 * @param mixed $alpha Opacity of pasted image, see setAlpha() and AlphaTransformation
	 * @see setImage()
	 * @see setSize()
	 * @see setAlpha()
	 * @see ResizeTransformation
	 * @see AlphaTransformation
	 * @return \Imagoid\PasteTransformation
	 */
	public function setup($image=null,$width=null,$height=null,$alpha=1) {
		$this->reset();
		if (!$image) return $this;
		$this->setImage($image);
		$this->setCenterPosition();
		$this->setSize($width,$height);
		$this->setAlpha($alpha);
		return $this;
	}

	/**
	 * Sets image to be pasted into the image, on which the transformation is applied.
	 * For example, for pasting watermarks info photos,
	 * you use setImage() to watermark picture and then apply() the transformation
	 * on photo or photos.
	 * Pass ImageResource, resource or path to file (as in constructor of ImageResource).
	 * <br />Note that $image will not be modified in any way, although during pasting,
	 * several transformations are made to the pasted image.
	 * @param ImageResource|string|resource $image
	 * @return \Imagoid\PasteTransformation
	 */
	public function setImage($image) {
		$this->image=new ImageResource($image);
		$this->isImageProcessed=false;
		return $this;
	}

	/**
	 * Set position to paste the image to. Accepts any coordinates that can be used in ImageResource::parsePosition().
	 * Note that you can specify mode, which says from which side should be $x or $y measured,
	 * which is similar to positioning in HTML/CSS.
	 * <br />If you want to set center position, you can use setCenterPosition().
	 * <br /><code>
	 * // Paste to center, similar to setCenterPosition()
	 * $transformation -> setPosition("50%","50%","center","center");
	 *
	 * // Left top corner should be at [10,10]
	 * $transformation -> setPosition("10","10");
	 *
	 * // Right bottom corner should be 10 pixels from right bottom corner
	 * $transformation -> setPosition("10","10","right","bottom");
	 *
	 * // Horizontally, place left corner 10px left from center. Vertically, center the image.
	 * $transformation -> setPosition("center-10","50%","left","center");
	 *
	 * // Place left top corner 50px from right border and 20% from bottom border
	 * $transformation -> setPosition("-50","-20%");
	 * </code>
	 * @param mixed $x
	 * @param mixed $y
	 * @param int|string $modeX Use either class constants or keywords as defined in parseMode().
	 * @param int|string $modeY
	 * @return \Imagoid\PasteTransformation
	 * @see parseMode()
	 * @see setCenterPosition()
	 */
	public function setPosition($x,$y,$modeX=self::LEFT,$modeY=self::TOP) {
		$this->positionX=$x;
		$this->positionY=$y;
		$this->positionModeX=$this->parseMode($modeX);
		$this->positionModeY=$this->parseMode($modeY);
		return $this;
	}

	/**
	 * Similar to setPosition(), but with setting to center, center mode as default
	 * @param mixed $x
	 * @param mixed $y
	 * @return \Imagoid\PasteTransformation
	 * @see setPosition()
	 */
	public function setCenterPosition($x="50%",$y="50%") {
		$this->setPosition($x, $y, self::CENTER, self::CENTER);
		return $this;
	}

	/**
	 * Set size of pasted image. Relative units (percentage) will be calculated
	 * using main image's size. Set one or both dimensions to null to keep original size of pasted image.
	 * <br />
	 * <code>
	 * // Pasted image should have exactly the same dimensions as it had
	 * $transformation -> setSize();
	 *
	 * // Stretch disproportionally to 50% of underlying image
	 * $transformation -> setPosition("50%","50%", "stretch");
	 *
	 * // Pasted image should be 20px smaller than the underlying one.
	 * $transformation -> setPosition("-20","-20");
	 *
	 * </code>
	 * @param mixed $width
	 * @param mixed $height
	 * @param int|string $mode Resize mode as defined in ResizeTransformation
	 * @return \Imagoid\PasteTransformation
	 * @see ResizeTransformation
	 */
	public function setSize($width=null,$height=null,$mode=ResizeTransformation::FIT) {
		$this->sizeW=$width;
		$this->sizeH=$height;
		$this->sizeMode=$mode;
		return $this;
	}

	/**
	 * Set alpha to pasted image. Accepts any modes that AlphaTransformation
	 * can understand.<br />
	 * <code>
	 * // Subtract 40% of alpha, so that 60% remains
	 * $transformation -> setAlpha("60%");
	 *
	 * // This works better if pasted image has some semi-transparent areas.
	 * $transformation -> setAlpha("*=60%");
	 * </code>
	 * @param mixed $alpha
	 * @return \Imagoid\PasteTransformation
	 * @see AlphaTransformation
	 */
	public function setAlpha($alpha=1) {
		$this->alpha=$alpha;
		return $this;
	}

	/**
	 * Optionally, you may want to do some transformation to the pasted image.
	 * Using this method, you can set any number of transformations that will be
	 * applied before the image is pasted.
	 * @param \Imagoid\Transformation $transformation
	 * @return \Imagoid\PasteTransformation
	 */
	public function addTransformation(Transformation $transformation) {
		$this->transformations[]=$transformation;
		return $this;
	}

	/**
	 * You can specify position modes using class constants or keywords:
	 * <ul>
	 * <li>self::LEFT, "left", "l"</li>
	 * <li>self::RIGHT, "right", "r"</li>
	 * <li>self::TOP, "top", "t"</li>
	 * <li>self::BOTTOM, "bottom", "b"</li>
	 * <li>self::CENTER, "center", "c"</li>
	 * <li>self::MIDDLE, "middle", "m"</li>
	 * </ul>
	 * <br />
	 * Note that in fact, there are only three modes. LEFT and TOP are equivalent and can be swapped
	 * with exactly teh same effect. Similarly RIGHT and BOTTOM, and also CENTER and MIDDLE.
	 * @param mixed $mode
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	protected function parseMode($mode) {
		if (is_numeric($mode) and ($mode==self::LEFT or $mode==self::CENTER or $mode==self::RIGHT)) {
			return $mode;
		}
		$mode=trim(strtolower($mode));
		switch ($mode) {
			case "left": case "l":
				return self::LEFT;
			case "right": case "r":
				return self::RIGHT;
			case "top": case "t":
				return self::TOP;
			case "bottom": case "b":
				return self::BOTTOM;
			case "middle": case "m":
				return self::MIDDLE;
			case "center": case "c":
				return self::CENTER;
		}
		throw new \InvalidArgumentException("Invalid mode: \"$mode\".");
	}

	/**
	 * @ignore
	 */
	protected function preprocessPastedImage() {
		if ($this->isImageProcessed) return;
		if ($this->transformations) {
			foreach($this->transformations as $tr) {
				$this->image->apply($tr);
			}
		}
		if ($this->alpha!=1 and $this->alpha) {
			$trans=new AlphaTransformation($this->alpha);
			$trans->apply($this->image);
		}
		$this->isImageProcessed=true;
	}

	/**
	 * @ignore
	 */
	protected function calculatePosition($position,$mode,$original,$pastedSize) {
		switch ($mode) {
			case self::LEFT:
				return ImageResource::parsePosition($position, $original);

			case self::CENTER:
				$center=ImageResource::parsePosition($position, $original);
				return round($center-$pastedSize/2);

			case self::RIGHT:
				return $original-ImageResource::parsePosition($position, $original) - $pastedSize;

			default:
				throw new \Exception("Invalid position mode: \"$mode\".");
		}
	}
}

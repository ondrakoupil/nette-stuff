<?php

namespace OndraKoupil\Images;

/**
 * Resize the image.
 * <br />Basically, you can:
 * <ul>
 * <li>Specify only width or height and leave the other null/false. Final image will retain its original aspect ratio.</li>
 * <li>Specify both dimensions and mode which says Imagoid how to handle the resizing. </li>
 * </ul>
 * <br />Modes can be expressed with string or corresponding constant:
 * <ul>
 * <li>fit (default) - Keep aspect ratio and fit the image so that it has maximally the requested dimensions (ie. one dimension will be exactly as requested, other will be smaller or same)</li>
 * <li>fill - Keep aspect ratio and make the image at least that big as the requested diemnsions (ie. one dimension will be exactly as requested, other will be bigger or same)</li>
 * <li>stretch - Do not keep aspect ratio and stretch exactly to requested diemnsions</li>
 * <li>crop - Keep aspect ratio and make resulting image have exactly the requested dimensions, centering and cropping the parts that do not fit. Similar to fill, but with exact size.</li>
 * <li>exact - Keep aspect ratio and make resulting image have exactly the requested dimensions. Image will be fitted into the resulting image and remaining canvas will have defined or default background color. Similar to fit, but with exact size</li>
 * </ul>
 * <br />
 * What happens if the original image is smaller than requested dimensions, depends on $shrinkOnly:
 * <ul>
 * <li>True = do not make image bigger. If "crop" mode is used, fallback to "exact" mode.</li>
 * <li>False = make image bigger and perform the transformation as usual.</li>
 * </ul>
 * When using "stretch" mode, the image will be always stretched to requested dimension regardless on this setting.
 * <br /><br />
 * @author Ondřej Koupil koupil@animato.cz
 */
class ResizeTransformation extends Transformation {

	const FIT = 1;
	const FILL = 2;
	const CROP = 3;
	const STRETCH = 4;
	const EXACT = 5;

	protected $mode = self::FIT;
	protected $width=0;
	protected $height=0;
	protected $backgroundColor=null;
	protected $doNotOversize=null;

	/**
	 * Normalises input mode.
	 * @param string|int $string
	 * @return int
	 * @throws Exception
	 */
	protected function parseMode($string) {
		if (!$string) return self::FIT;
		if (is_numeric($string) and $string<=5 and $string>=1) return $string;
		$string=trim(strtolower($string));
		switch($string) {
			case "fit": case "": return self::FIT;
			case "fill": case "fil": return self::FILL;
			case "crop": case "cropped": return self::CROP;
			case "stretch": return self::STRETCH;
			case "exact": return self::EXACT;
		}
		throw new \Exception("\"$string\" is not valid resize mode. Use ResizeTransformation's constants or check documentation for valid values.");
	}

	public function getSignature() {
		$base="resize:".$this->mode.":".$this->width.":".$this->height.":".$this->backgroundColor.":".serialize($this->doNotOversize);
		return md5($base);
	}

		public function reset() {
		$this->mode=self::FIT;
		$this->width=0;
		$this->height=0;
		$this->backgroundColor=null;
		$this->doNotOversize=null;
		return $this;
	}


	/**
	 * See constructor
	 * @param mixed $width
	 * @param mixed $height
	 * @param string $mode
	 * @param mixed $backgroundColor
	 * @param bool|null $shrinkOnly
	 * @return ResizeTransformation Fluent interface
	 */
	function setup($width=null,$height=null,$mode=null,$backgroundColor=null, $shrinkOnly = null) {
		if (!$width) $width=null;
		if (!$height) $height=null;
		$mode=$this->parseMode($mode);

		if ($mode==self::EXACT or $mode==self::CROP) { //Crop sometimes fallbacks to Exact
			if (!$backgroundColor) {
				$backgroundColor=new Color(Color::TRANSPARENT);
			}
			if (!($backgroundColor instanceof Color)) {
				$backgroundColor=new Color($backgroundColor);
			}
		}
		$this->width=$width;
		$this->height=$height;
		$this->mode=$mode;
		$this->backgroundColor=$backgroundColor;
		if ($shrinkOnly===null) {
			$shrinkOnly=true;
		}
		$this->doNotOversize=$shrinkOnly;
		return $this;
	}

	/**
	 * Sets up the transformation.
	 * @param string|int $width Requested width in any format that ImageResource::parseSize can understand. Leave null or false to resize to exact $height.
	 * @param string|int|null $height Similar to $width. Leave null or false to resize to exact $width
	 * @param string|int $mode Resize mode
	 * @param Color|string|null $backgroundColor Background color when using Exact mode, null to transparent color
	 * @param bool|null $shrinkOnly Null = true
	 */
	function __construct($width=null,$height=null,$mode=null,$backgroundColor=null,$shrinkOnly=null) {
		if (func_num_args()) {
			$this->setup($width, $height, $mode, $backgroundColor, $shrinkOnly);
		}
	}

	/**
	 * Create a new, resized image. Use applyTo() to resize the original image.
	 * @param ImageResource $image
	 * @return ImageResource A new image
	 */
	public function apply(ImageResource $image) {
		$oldW=$image->getWidth();
		$oldH=$image->getHeight();
		$mode=$this->mode;

		if (!$this->height) {
			// Exact width mode
			$newW=ImageResource::parseSize($this->width, $oldW);
			$ratio=$newW/$oldW;
			if ($ratio>1 and $this->doNotOversize and $this->mode!=self::STRETCH) {
				$ratio=1;
			}
			$newW=$oldW*$ratio;
			$newH=$oldH*$ratio;
			$mode="";
		} elseif (!$this->width) {
			// Exact height mode
			$newH=ImageResource::parseSize($this->height, $oldH);
			$ratio=$newH/$oldH;
			if ($ratio>1 and $this->doNotOversize and $this->mode!=self::STRETCH) {
				$ratio=1;
			}
			$newW=$oldW*$ratio;
			$newH=$oldH*$ratio;
			$mode="";
		} else {
			// Both dimensions specified, use custom modes
			$newW=ImageResource::parseSize($this->width, $oldW);
			$newH=ImageResource::parseSize($this->height, $oldH);

			$originalNewW=$newW;
			$originalNewH=$newH;

			// Calculate final image size
			if ($mode != self::STRETCH) {
				$ratioW=$newW/$oldW;
				$ratioH=$newH/$oldH;
				if ($mode == self::FIT or $mode == self::EXACT) { // use lower of them
					if ($ratioW>$ratioH) $ratio=$ratioH;
						else $ratio=$ratioW;
				} else { // use higher of them - for FILL or CROP
					if ($ratioW<$ratioH) $ratio=$ratioH;
						else $ratio=$ratioW;
				}
				if ($ratio>1 and $this->doNotOversize) {
					$ratio=1;
					if ($mode == self::CROP) {
						$mode = self::EXACT;
					}
				}
				$newW=round($oldW*$ratio);
				$newH=round($oldH*$ratio);
			}
		}

		// Prepare canvas
		if ($mode == self::EXACT or $mode == self::CROP) {
			$newImage=ImageResource::create($originalNewW,$originalNewH,$this->backgroundColor);
		} else {
			$newImage=ImageResource::create($newW,$newH,$this->backgroundColor);
		}

		if ($mode == self::EXACT) {
			$posX=ImageResource::parsePosition("50%-".round($newW/2), $originalNewW);
			$posY=ImageResource::parsePosition("50%-".round($newH/2), $originalNewH);
			$this->rawResizing($image->getResource(), $newImage->getResource(), $newW, $newH, $oldW, $oldH, $posX, $posY);
		} elseif ($mode == self::CROP) {
			$newToOrigRatio=$newW/$oldW;
			if ($newToOrigRatio>1 and $this->doNotOversize) {
				$newToOrigRatio=1;
			}
			$origPosX=round(($oldW-$originalNewW/$newToOrigRatio)/2);
			$origW=round($originalNewW/$newToOrigRatio);
			$origPosY=round(($oldH-$originalNewH/$newToOrigRatio)/2);
			$origH=round($originalNewH/$newToOrigRatio);
			$this->rawResizing($image->getResource(), $newImage->getResource(), $originalNewW, $originalNewH, $origW, $origH, 0, 0, $origPosX, $origPosY);
		} else {
			$this->rawResizing($image->getResource(), $newImage->getResource(), $newW, $newH, $oldW, $oldH);
		}

		$image->injectResource($newImage);
		$image->addToSignature($this->getSignature());

		return $image;
	}

	protected function rawResizing($fromResource,$toResource,$width,$height,$oldWidth,$oldHeight,$positionX=0,$positionY=0,$originalPosX=0,$originalPosY=0) {
		imagecopyresampled($toResource, $fromResource, $positionX, $positionY, $originalPosX, $originalPosY, $width, $height, $oldWidth, $oldHeight);
	}
}

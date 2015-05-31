<?php

namespace OndraKoupil\Images;

/**
 * Wrapper for image resource, capable of opening and saving image files and converting formats.
 *
 * @author Ondřej Koupil koupil@animato.cz
 *
 */
class ImageResource {

	/**
	 * @var resource
	 */
	protected $resource;
	protected $originalFileName;
	protected $originalType;

	protected $signatureHistory="";

	const PNG = IMAGETYPE_PNG;
	const JPG = IMAGETYPE_JPEG;
	const JPEG = IMAGETYPE_JPEG;
	const GIF = IMAGETYPE_GIF;

	const QUALITY_LOW=60;
	const QUALITY_MEDIUM=75;
	const QUALITY_HIGH=85;
	const QUALITY_PERFECT=100;

	const COMPRESSION_STANDARD=6;
	const COMPRESSION_FAST=1;
	const COMPRESSION_BEST=9;

	/**
	 * @param string|resource|ImageResource $initWith Either pass a filename, image resource or another ImageResource to be wrapped.
	 * When passing an image resource, be careful not to destroy the image resource. Do not pass another ImageResource->getResource()!
	 */
	function __construct($initWith=null) {
		if ($initWith) {
			if (is_string($initWith)) {
				$this->open($initWith);
			} elseif (is_resource($initWith)) {
				if (get_resource_type($initWith)=="gd") {
					$this->setResource($initWith);
				} else {
					throw new \InvalidArgumentException("Passed argument is a resource, but not a GD image resource.");
				}
			} elseif ($initWith instanceof ImageResource) {
				$newResource=self::createResourceClone($initWith->getResource());
				$this->setResource($newResource);
				$this->originalFileName=$initWith->originalFileName;
				$this->originalType=$initWith->originalType;
			} else {
				throw new \InvalidArgumentException("Invalid argument for constructor.");
			}
		}
	}

	function __clone() {
		if ($this->isOpen()) {
			$this->resource=self::createResourceClone($this->resource);
		}
	}

	/**
	 * Creates a clone of an image resource. Keeps alpha channel.
	 * @param resource $res
	 * @return resource
	 * @throws \InvalidArgumentException
	 */
	static function createResourceClone($res) {
		if (!is_resource($res) or get_resource_type($res)!="gd") {
			throw new \InvalidArgumentException;
		}
		$w=imagesx($res);
		$h=imagesy($res);
		$copy=imagecreatetruecolor($w,$h);
		imagealphablending($copy, false);
		imagesavealpha($copy, true);
		$transparent=imagecolorallocatealpha($copy, 255, 255, 255, 127);
		imagefilledrectangle($copy, 0, 0, $w, $h, $transparent);
		imagecopy($copy, $res, 0, 0, 0, 0, $w, $h);
		return $copy;
	}

	/**
	 * Load all meta-data (original file, type etc.) from $originalResource, but do not change resource.
	 * @param \Imagoid\ImageResource $originalResource
	 * @return ImageResource Fluent interface
	 */
	function loadWithoutResource(ImageResource $originalResource) {
		$this->originalFileName=$originalResource->originalFileName;
		$this->originalType=$originalResource->originalType;
		return $this;
	}

	/**
	 * Creates a copy with same original filename, type etc., but with no image resource in it.
	 * @return ImageResource New ImageResource
	 */
	function cloneWithoutResource() {
		$a=new ImageResource();
		$a->originalFileName=$this->originalFileName;
		$a->originalType=$this->originalType;
		return $a;
	}

	/**
	 * Set this object's resource to $newResource and destroy the original resource.
	 * <br />If $newResource is an ImageResource object, its resource will be unlinked, so that
	 * no two ImageResource objects share the same resource (which would cause problems whene destroying one of the objects).
	 * <br />If $newResource is a resource handle, do not destroy it afterwards manually.
	 * @param resource $newResource
	 * @return \Imagoid\ImageResource Fluent interface.
	 */
	function injectResource($newResource) {
		if ($newResource instanceof ImageResource) {
			$res=$newResource->getResource();
			$newResource->unlinkResource();
			$newResource=$res;
		}
		if (!is_resource($newResource) or get_resource_type($newResource)!="gd") {
			throw new \Exception("injectResource requires a gd resource!");
		}
		imagedestroy($this->resource);
		$this->resource=$newResource;
		return $this;
	}

	function __destruct() {
		$this->destroyIfNecessary();
	}

	/**
	 * Frees the image resource from memory and returns the object to its factory settings.
	 * Image is automatically freed when destroyung (unsetting) the object.
	 * @return \Imagoid\ImageResource
	 */
	public function destroy() {
		$this->destroyIfNecessary();
		return $this;
	}

	/**
	 * Removes resource from ImageResource object. Does not destroy the resource. Use with caution.
	 * @return ImageResource Fluent interface
	 */
	public function unlinkResource() {
		$this->resource=null;
		return $this;
	}

	/**
	 * Returns path to the original file or null if image was created from blank.
	 * @return string|null
	 */
	public function getPath() {
		return $this->originalFileName;
	}

	/**
	 * Get the image representation as binary string.
	 * @param int $quality Quality. Leave null or empty to keep original or default.
	 * @param int $type File type. Leave null or empty to keep original or default.
	 * @return string
	 */
	public function getAsString($quality=null,$type=null) {
		$this->openIfNecessary();
		ob_start();
		$this->writeFile(null, $quality, $type);
		return ob_get_clean();
	}

	/**
	 * Calls getAsString()
	 * @return string
	 */
	public function __toString() {
		return $this->getAsString();
	}

	/**
	 * Get current file's signature.
	 * Signature is calculated from original file path and signature of all transformations made to it.
	 * <br />Signature of two Image objects should be same only if they originates from same file and
	 * same transformations were applied to them.
	 * @return string
	 */
	public function getSignature() {
		$base="image:".$this->originalFileName.":".$this->signatureHistory;
		return md5($base);
	}

	/**
	 * Add some string to file's signature.
	 * This method is called whenever a transformation is applied.
	 * @param string $string
	 * @return \Imagoid\ImageResource
	 */
	public function addToSignature($string) {
		$this->signatureHistory.=$string;
		return $this;
	}

	/**
	 * Cleans signature and sets it back to the state as if no transformations were made.
	 * <br />Called in destroy().
	 * <br />Whenever you manipulate with this object's resource, you should set correct signature history
	 * or clear it.
	 * @return \Imagoid\ImageResource
	 */
	public function clearSignatureHistory() {
		$this->signatureHistory="";
		return $this;
	}

	/**
	 * Parses a string-defined size of an image.
	 * @param string $size Pattern, ie. "30", "25%", "+=50%"
	 * @param int $original Original size, so that percentages and addings can be calculated.
	 * @return int
	 */
	static function parseSize($size,$original=null) {
		if (!$original) $original=$size;
		if (!$size) $size=$original;
		if (!$size and !$original) return 1;

		// Simple value
		if (is_numeric($size)) {
			if ($size<0) return round($original+$size);
			return round($size);
		}

		$value=0;
		$operator="";

		//Operator
		if (preg_match('~^\s*([+-])\s?=(.*)~',$size,$parts)) {
			$operator=$parts[1];
			$size=$parts[2];
		}

		//Percentage value
		if (preg_match('~^\s*(.*)\%\s*$~',$size,$parts)) {
			$percentage=$parts[1];
			$value=$percentage*$original/100;
		} else {
			$value=$size;
		}
		if ($value<0) $value=$value+$original;

		if (!$operator) {
			return round($value);
		} else {
			switch($operator) {
				case "+": return round($value+$original);
				case "-": return round($value+$original);
				return round($value);
			}
		}
	}

	/**
	 * Parses a string-defined position in an image. ie. "50", "25%", "center", "-50" (50 px from right/bottom), "center-40", "center+15%", "15%-40" etc.
	 * @param string $position
	 * @param int $original
	 */
	static function parsePosition($position,$original=null) {
		if (!$original) $original=$position;
		if ($position===null or $position===false) $position=$original;
		if (!$position and !$original) return 0;

		// Simple value
		if (is_numeric($position)) {
			if ($position<0) return round($original+$position);
			return round($position);
		}

		$words=array("left","right","top","bottom","middle","center");
		$wordMeanings=array("0","100%","0","100%","50%","50%");

		$position=str_replace($words, $wordMeanings, $position);

		// one value
		if (preg_match('~^\s*([\-\d]+)\s*(\%?)\s*$~',$position,$parts)) {
			if ($parts[2]=="%") {
				$percentage=$parts[1]/100;
				if ($percentage<0) return round($original+$percentage*$original);
				else return round($percentage*$original);
			} else {
				$number=$parts[1];
				if ($number<0) return round($original-$number);
				else return round($number);
			}
		}

		// compound value
		if (preg_match('~^\s*([\-\d]+\s*\%?)\s*([+-])\s*([\d]+\s*\%?)\s*$~',$position,$parts)) {
			$part1=self::parsePosition($parts[1], $original);
			$part2=self::parsePosition($parts[3], $original);
			$operator=$parts[2];
			if ($operator=="+") {
				return round($part1+$part2);
			} else {
				return round($part1-$part2);
			}
		}
		return 0;
	}

	/**
	 * Calculates width of the image.
	 * @param string $width ie. "50", "25%", "+=25%" etc.
	 * @return int
	 * @see parseSize()
	 */
	function calculateWidth($width=null) {
		return self::parseSize($width,$this->getWidth());
	}

	/**
	 * Calculates height of the image.
	 * @param string $height ie. "50", "25%", "+=25%" etc.
	 * @return int
	 * @see parseSize()
	 */
	function calculateHeight($height=null) {
		return self::parseSize($height,$this->getHeight());
	}


	/**
	 * Outputs the image to browser
	 * @param string|bool $forceDownload Should I send headers to force download?
	 * True = yes, use original filename or default filename.
	 * String = use this as filename. Else do not send headers.
	 * @param int $quality Quality. Leave null or empty to keep original or default.
	 * @param int $type File type. Leave null or empty to keep original or default or guessed from $forceDownload.
	 */
	public function output($forceDownload=false,$quality=null,$type=null) {
		if (!$type) {
			$type=$this->guessTypeByFilename($forceDownload);
		}
		if (!headers_sent()) {
			header("Content-Type: ".image_type_to_mime_type($type));
			if ($forceDownload) {
				if ($forceDownload===true) {
					if ($this->originalFileName) {
						$filename=basename($this->originalFileName);
					} else {
						$filename="image.".image_type_to_extension($type);
					}
				} else {
					$filename=$forceDownload;
				}
				header("Content-Disposition: attachment; filename=$filename");
			}
		}
		$this->writeFile(null, $quality, $type);
	}

	/**
	 * Opens file with image.
	 * In fact, it does nothing, only marks which file you want to work with. In the moment when
	 * you really need to open the image file, Imagoid does it silently.
	 * <br />If any image file was open, it will be closed and the resource destroyed.
	 * @param string $filename
	 * @return ImageResource Fluent interface
	 */
	function open($filename) {
		$this->destroyIfNecessary(true);
		$this->originalType=null;
		$this->originalFileName=$filename;
		return $this;
	}

	/**
	 * If necessary, saves the image into a file.
	 * <br />File is not saved if there was no transformation on it and the filename is same as original.
	 * <br />If you specify a new filename but make no transformation, the original file is copied to new location.
	 * <br />For more complex operations or changing type or quality, see writeFile()
	 * @param string $filename See prepareFilename()
	 * @return \Imagoid\ImageResource
	 * @throws Exception
	 * @see writeFile()
	 */
	function save($filename=false) {
		$filename=$this->prepareFilename($filename);
		if (!$this->isOpen()) {
			if ($filename!=$this->originalFileName) {
				$origType=$this->guessTypeByFilename($this->originalFileName);
				$newType=$this->guessTypeByFilename($filename);
				if ($origType==$newType) {
					$ok=@copy($this->originalFileName,$filename);
					if (!$ok) throw new Exception("Could not write file \"$filename\".");
				} else {
					$this->writeFile($filename, null, $newType);
				}
			}
		} else {
			$this->writeFile($filename);
		}
		return $this;
	}

	/**
	 * Closes the image, optionally saves it, and destroy it from PHP's memory.
	 * @param bool|string $saveOrFilename False = do not save. True = save with original filename. String = save to new filename.
	 * @return \Imagoid\ImageResource
	 */
	function close($saveOrFilename=false) {
		if ($save) {
			if ($save===true) {
				$this->save;
			} else { //String
				$this->save($save);
			}
		}
		$this->destroyIfNecessary();
		return $this;
	}

	/**
	 * Create a new, blank image.
	 * <br />One param: ($widthAndHeight)
	 * <br />Two params: ($widthAndHeight, $color) OR ($width, $height)
	 * <br />Three params: ($width, $height, $color)
	 * @param number $width
	 * @param number $height
	 * @param Color|string $color Default color is transparent white.
	 * @return \Imagoid\ImageResource
	 * @throws \InvalidArgumentException
	 */
	static function create($width,$height=null,$color=null) {
		if (!is_numeric($width)) throw new \InvalidArgumentException("Width must be number.");
		if (func_num_args()==2 and (!is_numeric($height))) {
			$color=Color::build($height);
			$height=$width;
		}
		if (!$color) $color=new Color(Color::TRANSPARENT);
		$color=Color::build($color);
		if (!$height) $height=$width;

		$newImage=imagecreatetruecolor($width, $height);
		imagealphablending($newImage, false);
		imagesavealpha($newImage, true);
		$baseColor=imagecolorallocatealpha($newImage, $color->r*255, $color->g*255, $color->b*255, 127-($color->a*127));
		imagefilledrectangle($newImage, 0, 0, $width, $height, $baseColor);

		$image=new ImageResource($newImage);

		return $image;
	}

	/**
	 * Applies a transformation to this image.
	 * @param Transformation $transformation
	 * @return ImageResource fluent interface
	 */
	function apply(Transformation $transformation) {
		$transformation->apply($this);
		return $this;
	}

	/**
	 * Gets the image as GD resource. Do not destroy it or haggle with it unless you know exactly what you are doing.
	 * Imagoid manages resources automatically.
	 * @return resource
	 */
	function getResource() {
		$this->openIfNecessary();
		return $this->resource;
	}

	/**
	 * Sets the object's resource.
	 * <br />Use with caution. injectResource() is safer.
	 * <br />Never do $oneImage->setResource($secondImage->getResource());
	 * @param resource $res
	 * @param string $originalFileName Pass a string to fake the object as it was opened from some file with this name.
	 * @param int $originalType Pass a filetype to fake the object as it was opened from some file of this type.
	 * @return \Imagoid\ImageResource
	 * @throws \InvalidArgumentException
	 */
	function setResource($res,$originalFileName=null,$originalType=null) {
		if (is_resource($res) and get_resource_type($res)=="gd") {
			$this->resource=$res;
			if ($originalFileName!==null) $this->originalFileName=$originalFileName;
			if ($originalType!==null and $originalType and $originalType==self::PNG or $originalType==self::JPG or $originalType==self::GIF) {
				$this->originalType=$originalType;
			}
			return $this;
		} else {
			throw new \InvalidArgumentException("Passed argument is not a GD image resource.");
		}
	}

	/**
	 * Converts the image resource to truecolor if necessary.
	 * Some transformations does not work with palette images, so they might want to use this function.
	 * @return \Imagoid\ImageResource Fluent interface.
	 */
	function toTrueColor() {
		$this->openIfNecessary();
		if (!imageistruecolor($this->resource)) {
			$newRes=self::createResourceClone($this->resource);
			imagedestroy($this->resource);
			$this->resource=$newRes;
		}
		return $this;
	}

	/**
	 * If needs, converts the image resource to palette using PHP GD's built-in algorithm.
	 * Destroys any transparency information. Does not do anything on images that are already
	 * using palettes, therefore transparent GIFs are safe.
	 * @param int $maxColors
	 * @param bool $dither
	 * @return \Imagoid\ImageResource
	 * @todo může fungovat s alfa kanálem?
	 */
	function toPalette($maxColors=255,$dither=false) {
		$this->openIfNecessary();
		if (!$maxColors) $maxColors=255;
		if (imageistruecolor($this->resource)) {
			imagetruecolortopalette($this->resource, $dither, $maxColors);
		}
		return $this;
	}

	/**
	 * Converts the image resource to grayscale palette, precisely conserving all colours
	 * (256 shades of gray fits exactly into 256 colors that can fit into a palette). PHP's
	 * built-in algorithm usually messes this up.
	 * <br />However, using toPaletteGrayscale() destroys any transparency and alpha channel information and
	 * when used on images with alpha channel, results might be weird.
	 * @param bool $doGrayscaling If set to true, image will be grayscaled before converting to palette mode.
	 * If not, Imagoid presumes you have already grayscaled it before. Using toPaletteGrayscale
	 * on non-grayscaled image usually ends up tragically.
	 * @return \Imagoid\ImageResource
	 */
	function toPaletteGrayscale($doGrayscaling=false) {
		$this->openIfNecessary();
		if ($doGrayscaling) imagefilter($this->resource, IMG_FILTER_GRAYSCALE);
		if (imageistruecolor($this->resource)) {
			$w=$this->getWidth();
			$h=$this->getHeight();
			$res=$this->resource;
			$res2=imagecreate($w, $h);
			for ($i=0;$i<256;$i++) {
				imagecolorset($res2, $i, $i, $i, $i);
			}
			imagecopy($res2,$res,0,0,0,0,$w,$h);
			$this->resource=$res2;
			imagedestroy($res);
		}
		return $this;
	}

	/**
	 * @return number Current image's width.
	 */
	function getWidth() {
		$this->openIfNecessary();
		return imagesx($this->resource);
	}

	/**
	 * @return number Current image's height.
	 */
	function getHeight() {
		$this->openIfNecessary();
		return imagesy($this->resource);
	}

	/**
	 * If the image has not yet been loaded, do so. Else do nothing.
	 * @return \Imagoid\ImageResource Fluent interface
	 */
	protected function openIfNecessary() {
		if (!$this->isOpen()) {
			$this->readFromFile();
		}
		return $this;
	}

	/**
	 * Frees resource from memory, if the resource was already loaded.
	 * Clears signature hostory.
	 * @throws \Exception
	 * @return \Imagoid\ImageResource Fluent interface
	 */
	protected function destroyIfNecessary($clearVars=true) {
		if ($this->resource) {
			$ok=imagedestroy($this->resource);
			if (!$ok) throw new \Exception("Could not free image resource. This can happen when passing another ImageResource->getResource() to constructor.");
		}
		if ($clearVars) {
			$this->originalFileName=null;
			$this->originalType=null;
		}
		$this->signatureHistory="";
		$this->resource=null;
		return $this;
	}

	/**
	 * Saves the image info file.
	 * @param string $filename See prepareFilename(). Null for outputting the image instead of saving into file.
	 * @param number $quality Compression level (0-10) for PNG, quality level (0-100) for JPEG.
	 * @param int $type Use filetype constants. Leave empty or null to be guessed from filename.
	 * @return \Imagoid\ImageResource
	 * @throws \Exception
	 * @see save()
	 */
	function writeFile($filename=false,$quality=null,$type=null) {
		$this->openIfNecessary();
		if (!$this->isOpen()) throw new \Exception("File was not open.");
		$filename=$this->prepareFilename($filename);
		if (!$type) $type=$this->guessTypeByFilename($filename);

		$ok=false;
		switch ($type) {
			case self::PNG:
				if (!$quality) $quality=self::COMPRESSION_STANDARD;
				elseif (!is_numeric($quality) or $quality<0 or $quality>10) {
					$quality=COMPRESSION_STANDARD;
				}
				$ok=imagepng($this->resource, $filename, $quality);
				break;
			case self::JPEG:
				if (!$quality) $quality=self::QUALITY_HIGH;
				elseif (!is_numeric($quality) or $quality<0 or $quality>100) {
					$quality=self::QUALITY_HIGH;
				}
				$ok=imagejpeg($this->resource, $filename, $quality);
				break;
			case self::GIF:
				imagesavealpha($this->resource, false);
				$ok=imagegif($this->resource, $filename);
				imagesavealpha($this->resource, true);
				break;
		}
		if (!$ok) {
			throw new \Exception("Could not save the image.");
		}
		$chmod=0666;
		if ($chmod) {
			@chmod($chmod,$filename);
		}
		return $this;
	}

	/**
	 * Returns color from certain position of the image.
	 * @param string $x Position of pixel in image
	 * @param string $y Position of pixel in image
	 * @param bool $onlyAsArray True = return array(red,green,blue,alpha), numbers from 0 to 1.
	 * False (default) = return Color.
	 * @return Color|array
	 */
	function pixel($x,$y,$onlyAsArray=false) {
		$this->openIfNecessary();
		$x=self::parsePosition($x, $this->getWidth());
		$y=self::parsePosition($y, $this->getHeight());
		$res=$this->resource;
		$pixel=imagecolorat($res, $x, $y);
		$array=imagecolorsforindex($res, $pixel);
		$array["red"]/=255;
		$array["green"]/=255;
		$array["blue"]/=255;
		$array["alpha"]=1-$array["alpha"]/127;
		$returnArray=array($array["red"],$array["green"],$array["blue"],$array["alpha"]);
		if ($onlyAsArray) return $returnArray;
		return Color::build($returnArray);
	}

	/**
	 * Fast version of pixel().
	 * <br />Image must be already loaded. $x and $y are not parsed, must be int.
	 * @param int $x
	 * @param int $y
	 * @return array [red], [green], [blue], [alpha] as in imagecolorsforindex
	 */
	function pixelFast($x,$y) {
		return imagecolorsforindex(
			$this->resource,
			imagecolorat($this->resource, $x, $y)
		);
	}

	/**
	 * Builds filename to save a file into. You can specify absolute or relative path.
	 * Also, you can pass an empty string or false to use original filename of the opened image
	 * (if it was not created from blank) or a directory name, so that original file's basename will
	 * be used, but placed in another directory. Passing null will keep null (so that writeFile will output image directly).
	 * @param string|bool|null $filename
	 * @return string
	 * @throws \Exception
	 */
	public function prepareFilename($filename) {
		if ($filename===null) return null;
		if (!$filename) {
			if ($this->originalFileName) return $this->originalFileName;
			throw new \Exception("Image is not from file, you must specify filename.");
		}
		if (file_exists($filename)) {
			if (is_dir($filename)) {
				if (!is_writable($filename)) {
					throw new \Exception("Directory \"$filename\" is not writable.");
				}
				$f=basename($this->originalFileName);
				$filename=$filename."/".$f;
				if (is_dir($filename)) {
					throw new \Exception("Filename \"$filename\" is a directory.");
				}
			} elseif (!is_writable($filename)) {
				throw new \Exception("Filename \"$filename\" is not writable.");
			}
		} else {
			$dir=dirname($filename);
			if (!is_writable($dir)) {
				throw new \Exception("Directory \"$dir\" is not writable.");
			}
		}
		return $filename;
	}

	/**
	 * Tries to guess filetype from filename. Returns default type or $default if type can't be decided.
	 * @param string $filename
	 * @param int $default If can not be guessed, use this. If not set, use original type or use defaultFileType from config
	 * @return int
	 */
	function guessTypeByFilename($filename,$default=null) {
		if (preg_match('~\.([a-z]{3,4}\s*$)~i',$filename,$parts)) {
			$extension=strtolower($parts[1]);
			$type=$this->guessTypeFromExtension($extension);
			if ($type) return $type;
		}
		if (!$default) {
			if ($this->originalType) {
				return $this->originalType;
			}
			return self::guessTypeFromExtension("jpg");
		} else {
			return $default;
		}
	}

	/**
	 * Converts type string ("png", ...) to constant (self::PNG, ...).
	 * @param string $extension
	 * @return string|null Null for unknown types.
	 */
	static function guessTypeFromExtension($extension) {
		$extension=strtolower($extension);
		switch ($extension) {
			case "png":
				return self::PNG;
			case "jpg":
			case "jpeg":
				return self::JPEG;
			case "gif":
				return self::GIF;
		}
		return null;
	}

	/**
	 * Really reads the image from file.
	 * @throws \Exception
	 * @return ImageResource Fluent interface
	 */
	protected function readFromFile() {
		if (!$this->originalFileName) {
			throw new \Exception("Could not open image file, you must specify file path first.");
		}
		if (!file_exists($this->originalFileName)) {
			throw new \Exception("Could not open image file, file \"".$this->originalFileName."\" not found.");
		}
		$this->destroyIfNecessary(FALSE);
		$data=@getimagesize($this->originalFileName);
		if (!$data) {
			throw new \Exception("Could not open image file, file \"".$this->originalFileName."\" is not an image file or is not in supported format.");
		}

		$type=$data[2];

		if ($type!=self::GIF and $type!=self::JPEG and $type!=self::PNG) {
			throw new \Exception("Could not open image file, file \"".$this->originalFileName."\" is not in supported format (png, gif, jpg).");
		}

		$this->originalType=$type;
		switch ($type) {
			case self::GIF:
				$image=imagecreatefromgif($this->originalFileName);
				break;
			case self::JPEG:
				$image=imagecreatefromjpeg($this->originalFileName);
				break;
			case self::PNG:
				$image=imagecreatefrompng($this->originalFileName);
				break;
		}
		if ($image===false) {
			throw new \Exception("Could not read file \"".$this->originalFileName."\" - file is corrupt or not in supported format.");
		}
		imagealphablending($image, false);
		imagesavealpha($image, true);

		$this->resource=$image;

		return $this;
	}

	/**
	 * Create a copy of this image (same width, height, metadata) with blank resource (image will be a blank canvas)
	 * @param Color|string $bgColor
	 * @return ImageResource
	 */
	public function createBlankClone($bgColor=Color::TRANSPARENT) {
		$newImage=self::create($this->getWidth(), $this->getHeight(), $bgColor);
		$newImage->loadWithoutResource($this);
		return $newImage;
	}

	/**
	 * Check if the image was already loaded from file.
	 * @return bool
	 */
	function isOpen() {
		return ($this->resource !== null);
	}
}



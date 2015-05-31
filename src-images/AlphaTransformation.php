<?php

namespace OndraKoupil\Images;

/**
 * Changes opacity of an image. Works safely also with transparent alpha images.
 * <br /><br />
 * Most simple setup is just setting a number or percentage, so that fast native mode will be used and certain amount of alpha will be subtracted.
 * <br />You can use percentages, 0-100 numbers or 0-1 numbers.
 * In this mode, (100%-$amount) of alpha will be SUBTRACTED, as it is most common operation (you usually want to make a fully opaque image semi-transparent)
 * <br />Examples:<br />
 * <b>50%</b> - decrease alpha to 50%, ie. by -50%. When used on pixel with 100%, result will be 50% alpha. Pixel with 80% alpha will have 30% remaining.
 * <br /><b>30%</b> - decrease alpha to 30%, ie. by -70%. When used on pixel with 100%, result will be 30% alpha. Pixel with 80% alpha will have 10% remaining. Pixel with 50% will have 0%.
 * <br /><b>-30%</b> - decrease alpha by -30%. When used on pixel with 100%, result will be 70% alpha. Pixel with 80% alpha will have 50% remaining.
 * <br /><b>0.5</b> or <b>50</b> - the same asi 50%
 * <br /><b>-10</b> - the same as 90
 * <br /><b>-60</b> - the same as 40
 * <br /><br />
 * Basically, the transformation can operate in two modes:
 * <ul>
 * <li>NATIVE - is much faster and uses GD imagefilter() method. However, there are some limitations (see later)</li>
 * <li>PHP-BASED algorithm changing alpha on pixel-by-pixel iteration basis. Is much slower, but can do much more interesting changes to alpha channel.
 * Works efficiently on pallette-based images of any size and with small truecolor images.</li>
 * </ul>
 * <br />
 * To setup the transformation, you can use:
 * <br />Number on 0-1 scale
 * <br />Number on 0-100 scale (sometimes more than 100)
 * <br />String with percentage (is the same as without the percent sign).
 * <br />Negative numbers will be considered as "opposite" number, ie. -10% equals to 90%. -40 equals to 60. etc.
 * Also, you can specify an OPERATOR:
 * <br />+= - ADD - means that certain amount of alpha will be added to the channel. For example, +=30% to a pixel with 50% alpha will result in 80% alpha.
 * <br />-= - SUBTRACT - similar to ADD
 * <br />*= - MULTIPLY - means that amount of alpha will be proportionally changed. For example, *=50% to a pixel with 60% alpha will result in 30% alpha.
 * <br />When MULTIPLYing, you can use values higher than 1 - they will be considered as divider the opaciteness, so that you can do the image actually more opaque (less transparent) than it was before.
 * Useful when working with transparent PNGs. For example, *=200% to a pixel with 60% alpha will result in 80% alpha, or *=300% to a pixel with 40% alpha will result in 80% alpha (remaining 60% will be divided by 3).
 * <br />
 * <ul>Native mode can be used only with SUBTRACT operator. All other transformation setups will use PHP-BASED mode.
 * <br /><br />When not using operator, NATIVE mode and SUBTRACT operator will be used, unless you set $useNativeModeIfPossible to false. Then, MULTIPLY operator will be used,
 * as it is better (it retains transparency ratio among pixels in images that were already using some transparency).
 * <br /><br />
 * Some more examples:<br />
 * <br /><b>+=20%</b> - add 20%, so that alpha 50% becomes alpha 70%, alpha 0% becomes 20% or remains 0% (depends on $keepZeroAlpha), 100% (fully opaque) remains 100%.
 * <br /><b>-=20%</b> - or <b>80%</b> - subtract 20%, so that alpha 50% becomes alpha 30%, alpha 0% remains 0%, 100% (fully opaque) becomes 80%. Native mode will be used unless $useNativeModeIfPossible is set to false.
 * <br /><b>*=50%</b> - multiply by 50%, so that alpha 50% becomes alpha 25%, alpha 0% remains 0%, 100% (fully opaque) becomes 50%.
 * <br /><b>*=200%</b> - leave only half of remaining transparency, so that alpha 50% becomes alpha 75%, alpha 0% becomes 50% or remains 0% (depends on $keepZeroAlpha), 100% (fully opaque) remains 100%.
 * <br /><b>*=300%</b> - leave only 1/3 of remaining transparency, so that alpha 50% becomes cca 83%, alpha 0% becomes 67% or remains 0% (depends on $keepZeroAlpha), 100% (fully opaque) remains 100%.
 * <br /><br />Specifying $keepZeroAlpha to TRUE (which is by default) makes all alpha-increasing operations ignore pixels or colors with zero alpha (which will be always kept at zero alpha). Works only in pixel-by-puxel mode.
 * <br /><br />Sorry for the difficulties, but GD quite sucks at alpha operations.
 *
 * @author Ondřej Koupil koupil@animato.cz
 * @todo Apigen generates only part of class doc - why?
 */

class AlphaTransformation extends Transformation {

	protected $amount=100;
	protected $method=self::NATIVE;
	protected $operator=self::MULTIPLY;
	protected $keepZero=true;

	const NATIVE=1;
	const OWN=2;

	const ADD=1;
	const MULTIPLY=2;
	const SUBTRACT=3;
	const MULTIPLY_UP=4;

	/**
	 * See class description for possible setups.
	 */
	function __construct($amount=100,$useNativeModeIfPossible=true,$keepZeroAlpha=true) {
		$this->setup($amount,$useNativeModeIfPossible,$keepZeroAlpha);
	}

	public function getSignature() {
		$base="alpha:".$this->amount.":".$this->method.":".$this->operator.":".serialize($this->keepZero);
		return md5($base);
	}

	public function apply(ImageResource $image) {
		$image->toTrueColor();
		$method=$this->method;
		$operator=$this->operator;
		if ($method==self::NATIVE and version_compare(phpversion(), "5.2.5", "<")) {
			// Fallback
			$method=self::OWN;
			$operator=self::ADD;
		}

		if ($method==self::OWN) {
			$this->applyOwnTransformation($image,$operator);
			$image->addToSignature($this->getSignature());
			return $image;
		}

		// Native mode
		$res = $image->getResource();
		$number=127 - round($this->amount * 127);
		imagefilter($res, IMG_FILTER_COLORIZE, 0, 0, 0, $number);
		$image->addToSignature($this->getSignature());
		return $image;
	}

	public function reset() {
		$this->amount=null;
		$this->method=self::NATIVE;
		$this->operator=self::MULTIPLY;
		$this->keepZero=true;
		return $this;
	}


	public function applyOwnTransformation(ImageResource $image,$operator=null) {
		if (!$operator) $operator=$this->operator;
		$res=$image->getResource();
		if (!imageistruecolor($res)) {
			// palette
			$colors=imagecolorstotal($res);
			switch ($this->operator) {
				case self::MULTIPLY: $this->processPaletteMultiplyMode($res, $colors); break;
				case self::ADD: $this->processPaletteAddMode($res, $colors); break;
				case self::SUBTRACT: $this->processPaletteSubtractMode($res, $colors); break;
				case self::MULTIPLY_UP: $this->processPaletteMultiplyUpMode($res, $colors); break;
			}
		} else {
			// truecolor
			$w=$image->getWidth();
			$h=$image->getHeight();
			switch ($this->operator) {
				case self::MULTIPLY: $this->processTruecolorMultiplyMode($res, $w, $h); break;
				case self::SUBTRACT: $this->processTruecolorSubtractMode($res, $w, $h); break;
				case self::ADD: $this->processTruecolorAddMode($res, $w, $h); break;
				case self::MULTIPLY_UP: $this->processTruecolorMultiplyUpMode($res, $w, $h); break;
			}
		}
	}


	function processTruecolorMultiplyMode($res,$w,$h) {
		for ($x=0;$x<$w;$x++) {
			for ($y=0;$y<$h;$y++) {
				$color=imagecolorat($res, $x, $y);
				$a = ($color >> 24) & 0xFF;
				$a=(int)(127-((127-$a)*$this->amount));
				$color = ($color & 0x00FFFFFF) | ($a << 24);
				imagesetpixel($res, $x, $y, $color);
			}
		}
	}
	function processPaletteMultiplyMode($res,$colors) {
		for ($i=0;$i<$colors;$i++) {
			$color=imagecolorsforindex($res, $i);
			$color["alpha"]=(int)(127-((127-$color["alpha"])*$this->amount));
			imagecolorset($res, $i, $color["red"], $color["green"], $color["blue"], $color["alpha"]);
		}
	}

	function processTruecolorAddMode($res,$w,$h) {
		for ($x=0;$x<$w;$x++) {
			for ($y=0;$y<$h;$y++) {
				$color=imagecolorat($res, $x, $y);
				$a = ($color >> 24) & 0xFF;
				if ($a==127 && $this->keepZero) continue;
				$a=(int)(127-((127-$a)+$this->amount*127));
				if ($a>127) $a=127;
				elseif ($a<0) $a=0;
				$color = ($color & 0x00FFFFFF) | ($a << 24);
				imagesetpixel($res, $x, $y, $color);
			}
		}
	}
	function processPaletteAddMode($res,$colors) {
		for ($i=0;$i<$colors;$i++) {
			$color=imagecolorsforindex($res, $i);
			if ($color["alpha"]==127 && $this->keepZero) continue;
			$color["alpha"]=(int)(127-((127-$color["alpha"])+$this->amount*127));
			if ($color["alpha"]>127) $color["alpha"]=127;
			elseif ($color["alpha"]<0) $color["alpha"]=0;
			imagecolorset($res, $i, $color["red"], $color["green"], $color["blue"], $color["alpha"]);
		}
	}

	function processTruecolorSubtractMode($res,$w,$h) {
		for ($x=0;$x<$w;$x++) {
			for ($y=0;$y<$h;$y++) {
				$color=imagecolorat($res, $x, $y);
				$a = ($color >> 24) & 0xFF;
				$a=(int)(127-((127-$a)-$this->amount*127));
				if ($a>127) $a=127;
				elseif ($a<0) $a=0;
				$color = ($color & 0x00FFFFFF) | ($a << 24);
				imagesetpixel($res, $x, $y, $color);
			}
		}
	}
	function processPaletteSubtractMode($res,$colors) {
		for ($i=0;$i<$colors;$i++) {
			$color=imagecolorsforindex($res, $i);
			$color["alpha"]=(int)(127-((127-$color["alpha"])-$this->amount*127));
			if ($color["alpha"]>127) $color["alpha"]=127;
			if ($color["alpha"]<0) $color["alpha"]=0;
			imagecolorset($res, $i, $color["red"], $color["green"], $color["blue"], $color["alpha"]);
		}
	}

	function processTruecolorMultiplyUpMode($res,$w,$h) {
		for ($x=0;$x<$w;$x++) {
			for ($y=0;$y<$h;$y++) {
				$color=imagecolorat($res, $x, $y);
				$a = ($color >> 24) & 0xFF;
				if ($a==127 && $this->keepZero) continue;
				$a=(int)($a*$this->amount);
				$color = ($color & 0x00FFFFFF) | ($a << 24);
				imagesetpixel($res, $x, $y, $color);
			}
		}
	}
	function processPaletteMultiplyUpMode($res,$colors) {
		for ($i=0;$i<$colors;$i++) {
			$color=imagecolorsforindex($res, $i);
			if ($color["alpha"]==127 && $this->keepZero) continue;
			$color["alpha"]=(int)($color["alpha"]*$this->amount);
			imagecolorset($res, $i, $color["red"], $color["green"], $color["blue"], $color["alpha"]);
		}
	}

	/**
	 * See constructor
	 * @param int|string $amount see class description
	 * @param bool $useNativeModeIfPossible see class description
	 * @param bool $keepZeroAlpha
	 * @return \Imagoid\AlphaTransformation Fluent interface
	 */
   	public function setup($amount=100,$useNativeModeIfPossible=true,$keepZeroAlpha=true) {

		$this->keepZero=$keepZeroAlpha?true:false;

		// With operator
		if (preg_match('~\s*([+\-*])\s*\=\s*(.*)\s*~',$amount,$parts)) {
			if ($parts[1]=="*") {
				$this->method=self::OWN;
				$amount=self::parseNumber($parts[2], true);
				if ($amount>1) {
					$this->operator=self::MULTIPLY_UP;
					$this->amount=1/$amount;
				} else {
					$this->operator=self::MULTIPLY;
					$this->amount=$amount;
				}
				return $this;
			}
			if ($parts[1]=="+") {
				$this->method=self::OWN;
				$this->operator=self::ADD;
				$this->amount=self::parseNumber($parts[2], false);
				return $this;
			}
			if ($parts[1]=="-") {
				if ($useNativeModeIfPossible) {
					$this->method=self::NATIVE;
					$this->amount=1-self::parseNumber($parts[2], false);
				} else {
					$this->method=self::OWN;
					$this->amount=self::parseNumber($parts[2], false);
				}
				$this->operator=self::SUBTRACT;
				return $this;
			}
		}

		// Plain number
		$amount=self::parseNumber($amount, true);
		if ($amount>1) {
			$this->method=self::OWN;
			$this->operator=self::MULTIPLY_UP;
			$this->amount=1/$amount;
		} else {
			if ($useNativeModeIfPossible) {
				$this->method=self::NATIVE;
				$this->operator=self::SUBTRACT;
				$this->amount=$amount;
			} else {
				$this->method=self::OWN;
				$this->operator=self::MULTIPLY;
				$this->amount=$amount;
			}
		}
		return $this;
	}

	/**
	 * @ignore
	 * @return float 0-1
	 */
	static function parseNumber($number,$allowMore=true) {
		if (is_numeric($number)) {
			if ($number<0) {
				return 1-self::parseNumber($number*-1,false);
			}
			if ($number>=0 and $number<=1) {
				return $number;
			} elseif ($number>1 and $number<=100) {
				return $number/100;
			} elseif ($number>100 and $allowMore) {
				return $number/100;
			} else {
				throw new \InvalidArgumentException("Invalid number \"$number\" for AlphaTransformation");
			}
		} elseif (preg_match('~^\s*([\d\.]+)\s*\%\s*$~',$number,$parts)) {
			$cislo=$parts[1];
			if ($cislo<0 or ($cislo>100 and !$allowMore)) {
				throw new \InvalidArgumentException("Invalid number \"$number\" for AlphaTransformation");
			}
			return $cislo/100;
		}
		throw new \InvalidArgumentException("Invalid number \"$number\" for AlphaTransformation");
	}

}

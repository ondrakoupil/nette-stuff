<?php

namespace OndraKoupil\Images;

/**
 * Helper class for defining, parsing and transforming colors.
 * <br /><br />Parsable numbers: 0.5 (0 to 1 scale), 50% (0% to 100% scale), 127 (0 to 255 scale, beware of numbers <= 1, which will be parsed using 0 to 1 scale)
 * <br />In some methods, negative numbers are allowed (therefore -1 to 1 scale, -100% to 100%, etc.)
 * <br />Parsable strings: #rgb, #rgba, #rrggbb, #rrggbbaa, #1-or-2-hexadecimal-digits-of-grayscale,
 * rgb({number},{number},{number}), rgba({number},{number},{number},{number}) or simply {number}.
 * @author Ondřej Koupil koupil@animato.cz
 */
class Color {

	/**
	 * @var number 0 to 1 for Red, settable via magic setter as any parsable number, readable via magic getter
	 */
	protected $r=1;

	/**
	 * @var number 0 to 1 for Green, settable via magic setter as any parsable number, readable via magic getter
	 */
	protected $g=1;

	/**
	 * @var number 0 to 1 for Blue, settable via magic setter as any parsable number, readable via magic getter
	 */
	protected $b=1;

	/**
	 * @var number 0 to 1 for Alpha, settable via magic setter as any parsable number, readable via magic getter
	 */
	protected $a=1;

	const WHITE = "#ffffff";
	const BLACK = "#000000";
	const RED = "#ff0000";
	const GREEN = "#00ff00";
	const BLUE = "#0000ff";
	const CYAN = "#00ffff";
	const MAGENTA = "#ff00ff";
	const YELLOW = "#ffff00";
	const TRANSPARENT = "#ffffff00";

	/**
	 * Constructor callable either with a parsable string, another Color object or three or four parsable numbers
	 * (for RGB, resp. RGBA channels).
	 * @param string|Color $color
	 * @param string|number $g
	 * @param string|number $b
	 * @param string|number $a
	 * @throws \InvalidArgumentException
	 */
	function __construct($color="",$g="",$b="",$a="") {
		if (func_num_args()>1) {
			$args=func_get_args();
			$this->load($args);
		} elseif ($color!=="") {
			$this->load($color);
		}
	}

	/**
	 * Loads any parsable string, parsable number or array of usable arguments
	 * @param Color|string|array $color
	 * @param string $g
	 * @param string $b
	 * @param string $a
	 * @return \Imagoid\Color Fluent interface
	 * @throws \InvalidArgumentException
	 */
	function load($color,$g="",$b="",$a="") {
		if (func_num_args()>1) {
			$args=func_get_args();
			return $this->load($args);
		}
		if (is_array($color)) {
			if (count($color)==1) {
				reset($color);
				$color=current($color);
			} elseif (count($color)==3) {
				$this->r=$this->normaliseNumber($color[0]);
				$this->g=$this->normaliseNumber($color[1]);
				$this->b=$this->normaliseNumber($color[2]);
				$this->a=1;
				return $this;
			} elseif (count($color)==4) {
				$this->r=$this->normaliseNumber($color[0]);
				$this->g=$this->normaliseNumber($color[1]);
				$this->b=$this->normaliseNumber($color[2]);
				$this->a=$this->normaliseNumber($color[3]);
				return $this;
			} else {
				throw new \InvalidArgumentException("Invalid array passed to Color object!");
			}
		}

		if ($color instanceof Color) {
			$this->r=$color->r;
			$this->g=$color->g;
			$this->b=$color->b;
			$this->a=$color->a;
			return $this;
		} elseif (is_numeric($color) and $color>=0 and $color<=1) {
			$this->r=$color;
			$this->g=$color;
			$this->b=$color;
			$this->a=1;
			return $this;
		} elseif ($color!=="") {
			return $this->parseString($color);
		}

		throw new \InvalidArgumentException("Invalid argument passed to Color->load() method!");
	}

	/**
	 * Magic getter for $r, $g, $b, $a
	 * @param string $name
	 * @return number
	 */
	function __get($name) {
		switch ($name) {
			case "r": case "R": case "red": return $this->r;
			case "g": case "G": case "green": return $this->g;
			case "b": case "B": case "blue": return $this->b;
			case "a": case "A": case "alpha": return $this->a;
		}
		return null;
	}

	/**
	 * Magic setter for $r, $g, $b, $a
	 * @param string $name You can enter any parsable number
	 * @return null
	 */
	function __set($name,$value) {
		$setValue=$this->normaliseNumber($value);
		switch ($name) {
			case "r": case "R": case "red": $this->r=$setValue;
			case "g": case "G": case "green": $this->g=$setValue;
			case "b": case "B": case "blue": $this->b=$setValue;
			case "a": case "A": case "alpha": $this->a=$setValue;
		}
		return null;
	}

	/**
	 * @ignore
	 * @param string $number
	 * @param bool $allowNegative Allow negative numbers?
	 * @return number
	 * @throws \InvalidArgumentException
	 */
	protected function normaliseNumber($number,$allowNegative=false) {
		if (is_numeric($number)) {
			if ($number>=0 and $number<=1) return $number*1;
			if ($number>=0 and $number<=255) return $number/255;
			if ($number<0) {
				if ($allowNegative) {
					if ($number>=-1) return $number*1;
					if ($number>=-255) return $number/255;
					if ($number<-255) return -1;
				}
				return 0;
			}
			if ($number>255) return 1;
		} elseif (preg_match('~\s*(['.($allowNegative?"\-":"").'\d\.]+)\s*\%\s*~',$number,$numberParts)) {
			$percentageValue=$numberParts[1];
			if ($percentageValue>=0 and $percentageValue<=100) {
				return $percentageValue/100;
			}
			if ($allowNegative and $percentageValue<0) {
				if ($percentageValue>=-100) {
					return $percentageValue/100;
				}
				return -1;
			}
			if ($percentageValue<0) return 0;
			if ($percentageValue>100) return 1;
		}
		try {
			$num=$this->hex2dec($number);
			return $num;
		} catch (Exception $e) {
			;
		}

		throw new \InvalidArgumentException("Value \"$number\" could not be parsed as a color value.");
	}

	/**
	 * @see getHex
	 * @return string
	 */
	function __toString() {
		return $this->getHex();
	}

	/**
	 * Converts decimal on 0 to 1 scale to two-digit hexa number
	 * @param number $dec
	 * @return string
	 */
	function dec2hex($dec) {
		$out=dechex(round($dec*255));
		if (strlen($out)==0) $out="00";
		if (strlen($out)==1) $out="0".$out;
		return $out;
	}

	/**
	 * Converts hexadecimal string (0 to ff) to decimal on scale 0 to 1
	 * @param string $hex
	 * @return number
	 */
	function hex2dec($hex) {
		if (strlen($hex)==1) {
			$hex=$hex.$hex;
		}
		$out=hexdec($hex);
		if ($out<0) $out=0;
		if ($out>255) $out=255;
		return $out/255;
	}

	/**
	 * Create a color identifier usable in GD functions.
	 * @param ImageResource|resource $image
	 * @return int
	 * @see imagecolorallocatealpha
	 */
	function getGd($image) {
		if ($image instanceof ImageResource) {
			$image=$image->getResource();
		}
		return imagecolorallocatealpha($image, $this->r*255, $this->g*255, $this->b*255, (1-$this->a)*127 );
	}

	/**
	 * Get #rrggbb or #rrggbbaa representation. Lowercase, always 6 or 8 digits.
	 * @return string
	 */
	function getHex() {
		$out="#"
			.$this->dec2hex($this->r)
			.$this->dec2hex($this->g)
			.$this->dec2hex($this->b);
		if ($this->a!=1) $out.=$this->dec2hex($this->a);
		return $out;
	}

	/**
	 * Get rgb() or rgba() representation, using numbers on 0 to 255 scale
	 * @return string
	 */
	function getRgb() {
		$out="rgb";
		if ($this->a!=1) {
			$out.="a";
		}
		$out.="(";
		$out.=round($this->r*255).",";
		$out.=round($this->g*255).",";
		$out.=round($this->b*255);
		if ($this->a!=1) {
			$out.=",".round($this->a*255);
		}
		$out.=")";
		return $out;
	}

	/**
	 * Get rgb() or rgba() representation, using numbers on 0 to 100% scale
	 * @return string
	 */
	function getRgbPercentage() {
		$out="rgb";
		if ($this->a!=1) {
			$out.="a";
		}
		$out.="(";
		$r=round($this->r*100);
		$g=round($this->g*100);
		$b=round($this->b*100);
		$a=round($this->a*100);

		$out.=$r.($r?"%":"").",";
		$out.=$g.($g?"%":"").",";
		$out.=$b.($b?"%":"");
		if ($a!=100) {
			$out.=",".$a.($a?"%":"");
		}
		$out.=")";
		return $out;
	}

	protected function parseString($str) {
		$str=trim($str);
		$str=preg_replace('~\s~','',$str);

		// Hex value?
		if (preg_match('~#([a-f0-9]{1,8})~i',$str,$parts)) {
			switch (strlen($parts[1])) {
				case 1: case 2:
					$num=$this->hex2dec($parts[1]);
					$this->r=$num;
					$this->g=$num;
					$this->b=$num;
					$this->a=1;
					break;
				case 3:
					$parts=$parts[1];
					$r=$this->hex2dec($parts[0]);
					$g=$this->hex2dec($parts[1]);
					$b=$this->hex2dec($parts[2]);
					$this->r=$r;
					$this->g=$g;
					$this->b=$b;
					$this->a=1;
					break;
				case 4:
					$parts=$parts[1];
					$r=$this->hex2dec($parts[0]);
					$g=$this->hex2dec($parts[1]);
					$b=$this->hex2dec($parts[2]);
					$a=$this->hex2dec($parts[3]);
					$this->r=$r;
					$this->g=$g;
					$this->b=$b;
					$this->a=$a;
					break;
				case 6:
					$parts=$parts[1];
					$r=$this->hex2dec(substr($parts,0,2));
					$g=$this->hex2dec(substr($parts,2,2));
					$b=$this->hex2dec(substr($parts,4,2));
					$this->r=$r;
					$this->g=$g;
					$this->b=$b;
					$this->a=1;
					break;
				case 8:
					$parts=$parts[1];
					$r=$this->hex2dec(substr($parts,0,2));
					$g=$this->hex2dec(substr($parts,2,2));
					$b=$this->hex2dec(substr($parts,4,2));
					$a=$this->hex2dec(substr($parts,6,2));
					$this->r=$r;
					$this->g=$g;
					$this->b=$b;
					$this->a=$a;
					break;
				default:
					throw new \InvalidArgumentException("Invalid hex string (must be 1, 2, 3, 4, 6 or 8 chars long!) - $parts[1]");
			}
			return $this;
		}

		//rgb value?
		if (preg_match('~rgb\(([\d\%\.]+),([\d\%\.]+),([\d\%\.]+)\)~i',$str,$parts)) {
			$r=$this->normaliseNumber($parts[1]);
			$g=$this->normaliseNumber($parts[2]);
			$b=$this->normaliseNumber($parts[3]);
			$this->r=$r;
			$this->g=$g;
			$this->b=$b;
			$this->a=1;
			return $this;
		}

		//rgba value?
		if (preg_match('~rgba\(([\d\.\%]+),([\d\.\%]+),([\d\.\%]+),([\d\.\%]+)\)~i',$str,$parts)) {
			$r=$this->normaliseNumber($parts[1]);
			$g=$this->normaliseNumber($parts[2]);
			$b=$this->normaliseNumber($parts[3]);
			$a=$this->normaliseNumber($parts[4]);
			$this->r=$r;
			$this->g=$g;
			$this->b=$b;
			$this->a=$a;
			return $this;
		}

		//single number?
		try {
			$cislo=$this->normaliseNumber($str);
			$this->r=$cislo;
			$this->g=$cislo;
			$this->b=$cislo;
			$this->a=1;
			return $this;
		} catch (Exception $e) {
			;
		}

		throw new \InvalidArgumentException("Invalid color string: $str");
	}

	/**
	 * Mixes another color into this one. Modifies original object.
	 * @param Color $withColor
	 * @param number $secondColorOpacity Default 0.5; 0 = no change to $this, 1 = change $this to fully match $withColor.
	 * @return \Imagoid\Color Fluent interface
	 * @throws \InvalidArgumentException
	 */
	function mix($withColor,$secondColorOpacity=0.5) {
		if (!($withColor instanceof Color)) {
			throw new \InvalidArgumentException("Method Color->mix() must receive another Color object as an argument!");
		}

		$this->r=$this->r+(($withColor->r-$this->r)*$secondColorOpacity*$withColor->a);
		$this->g=$this->g+(($withColor->g-$this->g)*$secondColorOpacity*$withColor->a);
		$this->b=$this->b+(($withColor->b-$this->b)*$secondColorOpacity*$withColor->a);

		return $this;
	}

	/**
	 * Increases gamma lightness. Modifies original object.
	 * @param number $intensity Any parsable number. 0 = no change, 1 = white color.
	 * @return \Imagoid\Color Fluent interface
	 */
	function lighten($intensity) {
		if ($intensity<0) return $this->darken($intensity*-1);
		$intensity=$this->normaliseNumber($intensity);
		$this->r+=(1-$this->r)*$intensity;
		$this->g+=(1-$this->g)*$intensity;
		$this->b+=(1-$this->b)*$intensity;
		return $this;
	}

	/**
	 * Decreases gamma lightness. Modifies original object.
	 * @param number $intensity Any parsable number. 0 = no change, 1 = black color.
	 * @return \Imagoid\Color Fluent interface
	 */
	function darken($intensity) {
		if ($intensity<0) return $this->lighten($intensity*-1);
		$intensity=$this->normaliseNumber($intensity);
		$this->r*=1-$intensity;
		$this->g*=1-$intensity;
		$this->b*=1-$intensity;
		return $this;
	}

	/**
	 * Increase opacity, by percentage of remaining to fully opaque (100% alpha). Modifies original object.
	 * @param number $intensity Any parsable number. 0 = no change. 1 = fully opaque.
	 * 0.5 = increase opacity by 50%, i.e. from 40% to 70% alpha.
	 * @return \Imagoid\Color Fluent interface.
	 */
	function increaseOpacity($intensity) {
		if ($intensity<0) return $this->decreaseOpacity($intensity*-1);
		$intensity=$this->normaliseNumber($intensity);
		$this->a+=(1-$this->a)*$intensity;
		return $this;
	}

	/**
	 * Decerase opacity, by percentage of remaining to fully transparent (0% alpha). Modifies original object.
	 * @param number $intensity Any parsable number. 0 = no change. 1 = fully transparent.
	 * 0.5 = increase opacity by 50%, i.e. from 40% to 20% alpha.
	 * @return \Imagoid\Color Fluent interface.
	 */
	function decreaseOpacity($intensity) {
		if ($intensity<0) return $this->increaseOpacity($intensity*-1);
		$intensity=$this->normaliseNumber($intensity);
		$this->a*=$intensity;
		return $this;
	}

	/**
	 * Calculates luminosity, subjective lightness for human eye.
	 * @return number
	 */
	function luminosity() {
		return ($this->r*0.6+$this->g+$this->b*0.3)/1.9;
	}

	/**
	 * Calculates mathematical lightness (average of RGB)
	 * @return number
	 */
	function lightness() {
		return ($this->r+$this->g+$this->b)/3;
	}

	/**
	 * Changes this Color object to it's grayscaled version with same luminosity.
	 * @return \Imagoid\Color Fluent interface.
	 */
	function desaturate() {
		$vysledek=$this->luminosity();
		$this->r=$vysledek;
		$this->g=$vysledek;
		$this->b=$vysledek;
		return $this;
	}

	/**
	 * Changes this Color object to it's inverted counterpart.
	 * @return \Imagoid\Color Fluent interface.
	 */
	function invert() {
		$this->r=1-$this->r;
		$this->g=1-$this->g;
		$this->b=1-$this->b;
		return $this;
	}

	/**
	 * Adds some values directly to color. Can receive negative values to subtract some color.
	 * <br />Can be run with one or three params.
	 * <br />Modifies original object.
	 * @param number|string $r Any parsable number string
	 * @param number|string $g Any parsable number string
	 * @param number|string $b Any parsable number string
	 */
	function addColor($r,$g=null,$b=null) {
		if (func_num_args()==1) {
			$g=$r;
			$b=$r;
		}
		$this->r=$this->r+$this->normaliseNumber($r,true);
		$this->g=$this->g+$this->normaliseNumber($g,true);
		$this->b=$this->b+$this->normaliseNumber($b,true);
		if ($this->r>1) $this->r=1;
		if ($this->r<0) $this->r=0;
		if ($this->g>1) $this->g=1;
		if ($this->g<0) $this->g=0;
		if ($this->b>1) $this->b=1;
		if ($this->b<0) $this->b=0;

		return $this;
	}

	/**
	 * Directly multiplies color values. Can receive negative values.
	 * <br />Multiplying by 50% means "bring the color to half of the way to be 100% white",
	 * by -50% means "bring the color to 50% of its original brightness".
	 * <br />Can be run with one or three params.
	 * <br />Modifies original object.
	 * @param number|string $r Any parsable number string
	 * @param number|string $g Any parsable number string
	 * @param number|string $b Any parsable number string
	 */
	function multiplyColor($r,$g=null,$b=null) {
		if (func_num_args()==1) {
			$g=$r;
			$b=$r;
		}
		$r=$this->normaliseNumber($r,true);
		$g=$this->normaliseNumber($g,true);
		$b=$this->normaliseNumber($b,true);

		if ($r>0) $this->r+=(1-$this->r)*$r;
		elseif ($r<0) $this->r*=1+$r;

		if ($g>0) $this->g+=(1-$this->g)*$g;
		elseif ($g<0) $this->g*=1+$g;

		if ($b>0) $this->b+=(1-$this->b)*$b;
		elseif ($b<0) $this->b*=1+$b;

		if ($this->r>1) $this->r=1;
		if ($this->r<0) $this->r=0;
		if ($this->g>1) $this->g=1;
		if ($this->g<0) $this->g=0;
		if ($this->b>1) $this->b=1;
		if ($this->b<0) $this->b=0;

	}

	/**
	 * Check if the color is same as $otherColor.
	 * @param string|array|Color $otherColor
	 * @param number $allowDiferrence Tolerated difference (0 = must be exact, 0.1 or 10 or 10% = allow 10% defference etc).
	 * @param bool $compareAlpha Should alpha channel be also compared?
	 * @param bool $transparentsAreSame Whether transparent color (with alpha 0) shuld be considered to be same independently on r, g, or b
	 * @return bool
	 */
	function isSameAs($otherColor,$allowDiferrence=0,$compareAlpha=true,$transparentsAreSame=true) {
		$otherColor=Color::build($otherColor);
		return self::compare($this,$otherColor,$allowDiferrence,$compareAlpha);
	}

	/**
	 * Static version of isSameAs. Arguments must be Color objects.
	 * @param \Imagoid\Color $color1
	 * @param \Imagoid\Color $color2
	 * @param type $allowDifference
	 * @param type $compareAlpha
	 * @param type $transparentsAreSame
	 * @return boolean
	 * @see isSameAs()
	 */
	public static function compare(Color $color1,Color $color2,$allowDifference=0,$compareAlpha=true,$transparentsAreSame=true) {
		$x=1/256;
		if ($allowDifference<$x) $allowDifference=$x; // 1/256 - lower difference is not significant
		if ($transparentsAreSame and $color1->a<$allowDifference and $color2->a<$allowDifference) return true;
		if ((abs($color1->r-$color2->r))>$allowDifference) return false;
		if ((abs($color1->g-$color2->g))>$allowDifference) return false;
		if ((abs($color1->b-$color2->b))>$allowDifference) return false;
		if ($compareAlpha and abs($color1->a-$color2->a)>$allowDifference) return false;
		return true;
	}

	/**
	 * @param Color|string $input Either Color object, or just a string or number to build Color with.
	 * @return \Imagoid\Color
	 */
	public static function build($input) {
		if ($input instanceof Color) return $input;
		return new Color($input);
	}

}
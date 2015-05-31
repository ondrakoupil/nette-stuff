<?php

namespace OndraKoupil\Images;

/**
 * @author Ondřej Koupil koupil@animato.cz
 * @deprecated Will use Palette somedays
 */
abstract class Transformation {

	/**
	 * Apply the transformation to na image. Modifies $image.
	 * When extending this method, the $image MUST be the object that is modified.
	 * @return ImageResource $image
	 */
	abstract public function apply(ImageResource $image);

	/**
	 * @return Transformation
	 */
	abstract public function setup();

	/**
	 * @return Transformation;
	 */
	abstract function reset();

	/**
	 * @return string
	 */
	abstract function getSignature();

	/**
	 * Apply the transformation to a copy of $image. Original $image remains untouched.
	 * @return ImageResource A copy of $image with transformation applied.
	 */
	public function applyCopy(ImageResource $image) {
		$newImage=clone $image;
		return $this->apply($newImage);
	}

}

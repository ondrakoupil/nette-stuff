<?php

namespace OndraKoupil\Images;

/**
* @deprecated Will use Palette somedays
*/

class ImageQueryParser extends \Nette\Object {

	/**
	 * @param string $imageQuery
	 * @return Transformation
	 * @throws \InvalidArgumentException
	 */
	function parse($imageQuery) {
		$imageQuery=trim($imageQuery);
		if (!$imageQuery) return null;
		if (is_numeric($imageQuery)) {
			return new ResizeTransformation($imageQuery, $imageQuery, ResizeTransformation::FIT);
		}
		if (preg_match('~^(\d+)[\sx]+(\d+)(\s+[a-z]+)?$~i',$imageQuery,$parts)) {
			if (isset($parts[3]) and $parts[3]) {
				return new ResizeTransformation($parts[1], $parts[2], $parts[3]);
			} else {
				return new ResizeTransformation($parts[1], $parts[2]);
			}
		}
		throw new \InvalidArgumentException("Could not parse ImageQuery: $imageQuery");
	}

}

<?php

namespace OndraKoupil\Images;


interface IFileFinder {
	function getPath($source,$signature,$freeFilename=true);
	function getHrefFromPath($path);
}

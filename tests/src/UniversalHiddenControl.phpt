<?php

namespace OndraKoupil\Nette\Forms;

include "../bootstrap.php";

use \Tester\TestCase;
use \Tester\Assert;

class UniversalHiddenControlTestCase extends TestCase {

	function testEncodings() {
		$field = new UniversalHiddenControl();

		// null
		Assert::same(
			null,
			$field->restoreValue( $field->convertValue(null) )
		);

		// scalars
		Assert::true(
			10 == $field->restoreValue( $field->convertValue(10) )
		);

		Assert::same(
			"",
			$field->restoreValue( $field->convertValue("") )
		);

		$value = "{tady něco je}";
		Assert::equal(
			$value,
			$field->restoreValue( $field->convertValue($value) )
		);

		$value = "object:invalid";
		Assert::equal(
			$value,
			$field->restoreValue( $field->convertValue($value) )
		);

		Assert::equal(
			"ahoj!",
			$field->restoreValue( $field->convertValue("ahoj!") )
		);

		// arrays
		$value = array("10", 5, "#{}\"'ů", false, "foo" => "bar", "sub" => array(1, "2", "49Sdžfř"));
		Assert::equal(
			$value,
			$field->restoreValue($field->convertValue($value))
		);

		$value = array(1, 2, 10, 258, "sdg as dgas g", array("sadg", null, true));
		Assert::equal(
			$value,
			$field->restoreValue($field->convertValue($value))
		);

		// objects
		$x = new \stdClass();
		$x->a = 10;
		$x->b = new \stdClass();
		$x->b->a = 100;
		$x->b->c = "foo? bar!";
		$x->b->d = array("10", "a" => "'\",!{");
		$x->c = array("10", "xxx", "p" => "x");

		Assert::equal(
			$value,
			$field->restoreValue($field->convertValue($value))
		);

	}

}

$a = new UniversalHiddenControlTestCase();
$a->run();
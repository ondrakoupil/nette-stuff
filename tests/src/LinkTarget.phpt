<?php

include "../bootstrap.php";

use \Tester\TestCase;
use \Tester\Assert;
use \OndraKoupil\Nette\LinkTarget;

class LinkTargetTestCase extends TestCase {

	function testConstruct() {

		$a = new LinkTarget();
		Assert::equal(LinkTarget::TYPE_NONE, $a->type);
		Assert::equal("", $a->target);
		Assert::equal(array(), $a->params);

		$b = new LinkTarget("Presenter:action");
		Assert::equal(LinkTarget::TYPE_NETTE, $b->type);
		Assert::equal("Presenter:action", $b->target);
		Assert::equal(array(), $b->params);

		$arrayC = array("id"=>4, "value"=>"a");
		$c = new LinkTarget("Presenter:action", $arrayC);
		Assert::equal(LinkTarget::TYPE_NETTE, $c->type);
		Assert::equal("Presenter:action", $c->target);
		Assert::equal($arrayC, $c->params);

		$d = new LinkTarget(LinkTarget::TYPE_HTTP, "http://www.google.cz");
		Assert::equal(LinkTarget::TYPE_HTTP, $d->type);
		Assert::equal("http://www.google.cz", $d->target);

		$e = LinkTarget::httpLink("http://www.seznam.cz");
		Assert::equal(LinkTarget::TYPE_HTTP, $e->type);
		Assert::equal("http://www.seznam.cz", $e->target);

		$f = new LinkTarget(LinkTarget::TYPE_NETTE, "Pre:act", array("a"=>"b"));
		Assert::equal(LinkTarget::TYPE_NETTE, $f->type);
		Assert::equal("Pre:act", $f->target);
		Assert::equal(array("a"=>"b"), $f->params);

	}

	function testSetters() {

		$a = new LinkTarget("Pre:act");
		$a->params = false;
		Assert::equal(array(), $a->params);
		$a->params = array("id" => 1);
		Assert::equal(array("id" => 1), $a->params);

		$a->target = "";
		Assert::equal(LinkTarget::TYPE_NONE, $a->type);
		Assert::equal("", $a->target);

		$a->type = LinkTarget::TYPE_HTTP;
		Assert::equal(LinkTarget::TYPE_HTTP, $a->type);
		$a->type = "balabambam";
		Assert::equal(LinkTarget::TYPE_HTTP, $a->type);
	}


	function testLink() {
		$a = new LinkTarget();
		Assert::equal("", $a->link());
		Assert::false($a->isLink());

		$b = new LinkTarget("Presenter:action");
		Assert::exception(function() use ($b) {
			$b->link();
		}, '\InvalidArgumentException');

		// How to test link() without actual presenter?

		$e = LinkTarget::httpLink("http://www.seznam.cz");
		Assert::equal("http://www.seznam.cz", $e->link());
		Assert::true($e->isLink());

	}

	function testSerialization() {
		$a = new LinkTarget("Pre:act", array("a"=>10, "b"=>"foo"));
		$string = $a."";
		Assert::equal($a->string(), $string);
		Assert::equal($a->__toString(), $string);

		$a2 = LinkTarget::from($string);
		Assert::equal($a->target, $a2->target);
		Assert::equal($a->params, $a2->params);
		Assert::equal($a->type, $a2->type);

		$ser = serialize($a);
		$a3 = unserialize($ser);
		Assert::equal($a->target, $a3->target);
		Assert::equal($a->params, $a3->params);
		Assert::equal($a->type, $a3->type);

		$b = LinkTarget::httpLink("http://www.seznam.cz");
		$ser2 = serialize($b);
		$b2 = unserialize($ser2);
		Assert::equal($b->target, $b2->target);
		Assert::equal($b->params, $b2->params);
		Assert::equal($b->type, $b2->type);
	}


}

$a=new LinkTargetTestCase();
$a->run();

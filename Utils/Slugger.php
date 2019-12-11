<?php

namespace Rapsys\UserBundle\Utils;

use Symfony\Component\DependencyInjection\ContainerInterface;

class Slugger {
	//The secret parameter
	private $secret;

	//The alpha array
	private $alpha;

	//The rev array
	private $rev;

	//The alpha array key number
	private $count;

	//The offset reduced from secret
	private $offset;

	//Retrieve secret and set offset from reduction
	public function __construct(ContainerInterface $container) {
		//Set secret
		$this->secret = $container->getParameter('kernel.secret');

		//Pseudo-random alphabet
		//XXX: use array flip and keys to workaround php "smart" that cast range('0', '9') as int instead of string
		//XXX: The key count mismatch, count(alpha)>count(rev), resulted in a data corruption due to duplicate numeric values
		//TODO: set this as a parameter generated once in a command ?
		$this->alpha = array_keys(array_flip(array_merge(
			range('^', '[', -1),
			range('V', 'Z'),
			range('9', '7', -1),
			range('L', 'O'),
			range('f', 'a', -1),
			range('_', '`'),
			range('3', '0', -1),
			range('E', 'H'),
			range('v', 'r', -1),
			range('+', '/'),
			range('K', 'I', -1),
			range('g', 'j'),
			range('=', ':', -1),
			range('>', '@'),
			range('m', 'k', -1),
			range('4', '6'),
			range('*', '%', -1),
			range('n', 'q'),
			range('U', 'P', -1),
			range(' ', '$'),
			range('D', 'A', -1),
			range('w', 'z'),
			range('~', '!', -1)
		)));

		//Init rev array
		$this->count = count($rev = $this->rev = array_flip($this->alpha));

		//Init split
		$split = str_split($this->secret);

		//Set offset
		$this->offset = array_reduce($split, function ($res, $a) use ($rev) { return $res += $rev[$a]; }, count($split)) % $this->count;
	}

	//Short the string
	public function short(string $string): string {
		//Return string
		$ret = '';

		//Iterate on each character
		foreach(str_split($string) as $k => $c) {
			if (isset($this->rev[$c]) && isset($this->alpha[($this->rev[$c]+$this->offset)%$this->count])) {
				//XXX: Remap char to an other one
				$ret .= chr(($this->rev[$c] - $this->offset + $this->count) % $this->count);
			}
		}

		//Send result
		return str_replace(['+','/'], ['-','_'], base64_encode($ret));
	}

	//Unshort the string
	public function unshort(string $string): string {
		//Return string
		$ret = '';

		//Iterate on each character
		foreach(str_split(base64_decode(str_replace(['-','_'], ['+','/'], $string))) as $c) {
			//XXX: Reverse map char to an other one
			$ret .= $this->alpha[(ord($c) + $this->offset) % $this->count];
		}

		//Send result
		return $ret;
	}

	//Crypt and base64uri encode string
	public function hash(string $string): string {
		return str_replace(['+','/'], ['-','_'], base64_encode(crypt($string, $this->secret)));
	}

	//Convert string to safe slug
	function slug(string $string): string {
		return preg_replace('/[\/_|+ -]+/', '-', strtolower(trim(preg_replace('/[^a-zA-Z0-9\/_|+ -]/', '', str_replace(['\'', '"'], ' ', iconv('UTF-8', 'ASCII//TRANSLIT', $string))), '-')));
	}
}

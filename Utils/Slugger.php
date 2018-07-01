<?php

namespace Rapsys\UserBundle\Utils;

class Slugger {
	//The secret parameter
	private $secret;

	//The offset reduced from secret
	private $offset;

	//Retrieve secret and set offset from reduction
	public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
		//Set secret
		$this->secret = $container->getParameter('secret');

		//Init rev array
		$rev = array_flip(array_merge(range('0', '9'), range('a', 'z'), range('A', 'Z'), range('!', '~')));

		//Set offset
		$this->offset = array_reduce(str_split($this->secret), function ($res, $a) use ($rev) { return $res += $rev[$a]; }, count($this->secret)) % count($rev);
	}

	//Short the string
	public function short($string) {
		//Return string
		$ret = '';

		//Alphabet
		$alpha = array_merge(range('0', '9'), range('a', 'z'), range('A', 'Z'), range('!', '~'));

		//Reverse alphabet
		$rev = array_flip($alpha);

		//Number characters
		$count = count($alpha);

		//Iterate on each character
		foreach(str_split($string) as $c) {
			if (isset($rev[$c]) && isset($alpha[($rev[$c]+$this->offset)%$count])) {
				$ret .= $alpha[($rev[$c]+$this->offset)%$count];
			}
		}

		//Send result
		return str_replace(array('+','/'), array('-','_'), base64_encode($ret));
	}

	//Unshort the string
	public function unshort($string) {
		//Return string
		$ret = '';

		//Alphabet
		$alpha = array_merge(range('0', '9'), range('a', 'z'), range('A', 'Z'), range('!', '~'));

		//Reverse alphabet
		$rev = array_flip($alpha);

		//Number characters
		$count = count($alpha);

		//Iterate on each character
		foreach(str_split(base64_decode(str_replace(array('-','_'), array('+','/'), $string))) as $c) {
			if (isset($rev[$c]) && isset($alpha[($rev[$c]-$this->offset+$count)%$count])) {
				$ret .= $alpha[($rev[$c]-$this->offset+$count)%$count];
			}
		}

		//Send result
		return $ret;
	}

	//Crypt and base64uri encode string
	public function hash($string) {
		return str_replace(array('+','/'), array('-','_'), base64_encode(crypt($string, $this->secret)));
	}

	//Convert string to safe slug
	function slug($string) {
		return preg_replace('/[\/_|+ -]+/', '-', strtolower(trim(preg_replace('/[^a-zA-Z0-9\/_|+ -]/', '', str_replace(array('\'', '"'), ' ', iconv('UTF-8', 'ASCII//TRANSLIT', $string))), '-')));
	}

}

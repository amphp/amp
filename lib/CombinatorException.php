<?php

namespace Amp;

class CombinatorException extends \RuntimeException {
	private $combinedExceptions;

	public function __construct($message, array $combinedExceptions = []) {
		parent::__construct($message, 0, null);
		$this->combinedExceptions = $combinedExceptions;
	}

	public function getCombinedExceptions() {
		return $this->combinedExceptions;
	}
}

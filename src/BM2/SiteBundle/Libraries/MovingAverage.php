<?php

namespace BM2\SiteBundle\Libraries;

class MovingAverage {

	private $rr_size;
	private $data;
	private $index;
	private $has_data = false;

	public function __construct($size=5) {
		$this->data = array();
		$this->rr_size = $size;
		$this->index = 0;
	}

	public function addData($value) {
		$this->index++;
		if ($this->index >= $this->rr_size) {
			$this->index = 0;
		}
		$this->data[$this->index] = $value;
		$this->has_data = true;
	}

	public function getAverage() {
		if (!$this->has_data) return -1;

		$total = 0;
		for ($i=0; $i<$this->rr_size; $i++) {
			if (isset($this->data[$i])) {
				$total += $this->data[$i];
			} else {
				$total += $this->data[$this->index];
			}
		}
		return $total / $this->rr_size;
	}

}


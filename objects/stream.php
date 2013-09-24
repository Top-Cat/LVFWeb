<?php

class stream {
	protected $buffer;
	private $func;

	function stream_open($path, $mode, $options, &$opened_path) {
		$this->func = parse_url($path)['host'];
		return true;
	}

	public function stream_write($data) {
		$lines = explode("\n", $data);

		$lines[0] = $this->buffer . $lines[0];

		$nb_lines = count($lines);
		$this->buffer = $lines[$nb_lines-1];
		unset($lines[$nb_lines-1]);

		foreach ($lines as $line) {
			call_user_func($this->func, $line);
		}

		return strlen($data);
	}
}

?>
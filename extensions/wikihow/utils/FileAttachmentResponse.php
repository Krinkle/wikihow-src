<?php

class FileAttachmentResponse {
	var $filename, $mimeType;

	const MIME_TSV = 'text/tsv';

	public function __construct($filename, $mimeType = self::MIME_TSV) {
		$this->filename = $filename;
		$this->mimeType = $mimeType;
	}

	public function start() {
		$this->outputHeader();
	}

	protected function outputHeader() {
		header("Content-Type: $this->mimeType");
		header('Content-Disposition: attachment; filename="' . addslashes($this->filename) . '"');
	}

	public function outputData($data) {
		echo $data;
	}

	public function end() {
		exit;
	}
}
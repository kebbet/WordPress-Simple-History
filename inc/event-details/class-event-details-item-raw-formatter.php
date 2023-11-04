<?php

namespace Simple_History\Event_Details;

/**
 * Formatter for a group of items.
 */
class Event_Details_Item_RAW_Formatter extends Event_Details_Item_Formatter {
	/** @var string */
	protected $html_output = '';

	/** @var array<mixed> */
	protected $json_output = [];

	public function to_html() {
		return $this->html_output;
	}

	public function to_json() {
		return $this->json_output;
	}

	/**
	 * @param string $html HTML output.
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_html_output( $html ) {
		$this->html_output = $html;

		return $this;
	}

	/**
	 * @param array<mixed> $json JSON output.
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_json_output( $json ) {
		$this->json_output = $json;

		return $this;
	}
}

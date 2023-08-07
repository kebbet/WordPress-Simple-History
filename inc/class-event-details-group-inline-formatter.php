<?php

namespace Simple_History;

/**
 * Format a group of items as an inline list.
 */
class Event_Details_Group_Inline_Formatter extends Event_Details_Group_Formatter {
	public function get_html_output( $group ) {
		$output = '<p>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Default_Formatter( $item ) );
			$output .= $item_formatter->get_output();
		}

		$output .= '</p>';

		return $output;
	}

	public function get_json_output( $group ) {
		$output = [];
		return $output;
	}
}

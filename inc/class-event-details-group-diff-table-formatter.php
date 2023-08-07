<?php

namespace Simple_History;

class Event_Details_Group_Diff_Table_Formatter extends Event_Details_Group_Formatter {
	public function get_html_output( $group ) {
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';
		$output .= '<tbody>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Diff_Table_Row_Formatter( $item ) );
			$output .= $item_formatter->get_output();
		}

		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}

	public function get_json_output( $group ) {
		$output = [];
		return $output;
	}
}

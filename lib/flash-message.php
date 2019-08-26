<?php

namespace ValuSearch;

function get_flash_message_key() {
	$id = get_current_user_id();

	if ( ! $id ) {
		return;
	}

	return "flash_message:$id";
}

function enqueue_flash_message( $message, $type ) {
	$key = get_flash_message_key();

	if ( ! $key ) {
		return;
	}

	$existing_messages = get_flash_messages();

	$existing_messages[] = [ 'type' => $type, 'message' => $message ];

	set_transient( $key, $existing_messages, 120 );
}

function get_flash_messages() {
	$key = get_flash_message_key();

	if ( ! $key ) {
		return [];
	}

	$data = get_transient( $key );

	if ( ! $data ) {
		return [];
	}

	return $data;
}

function clear_flash_messages() {
	delete_transient( get_flash_message_key() );
}
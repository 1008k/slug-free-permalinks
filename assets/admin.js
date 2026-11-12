( function () {
	const input = document.getElementById( 'ptid-post-structure' );
	const form = document.getElementById( 'ptid-native-permalink-form' );

	if ( ! input || ! form ) {
		return;
	}

	for ( const button of form.querySelectorAll( '[data-ptid-preset]' ) ) {
		button.addEventListener( 'click', function () {
			input.value = button.dataset.ptidPreset;
			input.focus();
			input.setSelectionRange( input.value.length, input.value.length );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	}

	for ( const button of form.querySelectorAll( '[data-ptid-tag]' ) ) {
		button.addEventListener( 'click', function () {
			const tag = button.dataset.ptidTag;
			const start = input.selectionStart ?? input.value.length;
			const end = input.selectionEnd ?? start;
			input.setRangeText( tag, start, end, 'end' );
			input.focus();
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	}
}() );

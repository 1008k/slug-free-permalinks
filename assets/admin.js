( function () {
	const button = document.getElementById( 'ptid-copy-wordpress-permalink-structure' );
	const input = document.getElementById( 'ptid-wordpress-permalink-structure' );

	if ( ! button || ! input ) {
		return;
	}

	button.addEventListener( 'click', async function () {
		const value = button.dataset.copyText || input.value;

		try {
			if ( navigator.clipboard && window.isSecureContext ) {
				await navigator.clipboard.writeText( value );
				return;
			}

			input.focus();
			input.select();
			document.execCommand( 'copy' );
		} catch ( error ) {
			input.focus();
			input.select();
		}
	} );
}() );

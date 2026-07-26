( () => {
	'use strict';

	const rows = document.querySelector( '#course-discovery-start-dates' );
	const template = document.querySelector( '#course-discovery-start-date-template' );
	const addButton = document.querySelector( '[data-course-discovery-add-start-date]' );

	if (
		! ( rows instanceof HTMLElement ) ||
		! ( template instanceof HTMLTemplateElement ) ||
		! ( addButton instanceof HTMLButtonElement )
	) {
		return;
	}

	const focusFirstInput = ( row ) => {
		const input = row.querySelector( 'input[type="month"]' );

		if ( input instanceof HTMLInputElement ) {
			input.focus();
		}
	};

	addButton.addEventListener( 'click', () => {
		const fragment = template.content.cloneNode( true );
		const row = fragment.querySelector(
			'[data-course-discovery-start-date-row]'
		);

		rows.append( fragment );

		if ( row instanceof HTMLElement ) {
			focusFirstInput( row );
		}
	} );

	rows.addEventListener( 'click', ( event ) => {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}

		const removeButton = event.target.closest(
			'[data-course-discovery-remove-start-date]'
		);

		if ( ! ( removeButton instanceof HTMLButtonElement ) ) {
			return;
		}

		const row = removeButton.closest(
			'[data-course-discovery-start-date-row]'
		);

		if ( ! ( row instanceof HTMLElement ) ) {
			return;
		}

		const allRows = rows.querySelectorAll(
			'[data-course-discovery-start-date-row]'
		);

		if ( 1 === allRows.length ) {
			const input = row.querySelector( 'input[type="month"]' );

			if ( input instanceof HTMLInputElement ) {
				input.value = '';
				input.focus();
			}

			return;
		}

		const nextFocus =
			row.nextElementSibling instanceof HTMLElement
				? row.nextElementSibling
				: row.previousElementSibling;

		row.remove();

		if ( nextFocus instanceof HTMLElement ) {
			focusFirstInput( nextFocus );
		} else {
			addButton.focus();
		}
	} );
} )();

/**
 * Term suggestions panel.
 *
 * Suggestions are rendered as checkboxes and only written when an editor
 * clicks Apply. Nothing here touches the post on its own.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || ! config ) {
		return;
	}

	var panel = document.querySelector( '[data-jevc-suggestions]' );

	if ( ! panel ) {
		return;
	}

	var button  = panel.querySelector( '[data-jevc-suggest]' );
	var results = panel.querySelector( '[data-jevc-results]' );
	var strings = config.strings || {};

	function message( text ) {
		results.textContent = '';
		var p = document.createElement( 'p' );
		p.className = 'description';
		p.textContent = text;
		results.appendChild( p );
	}

	function postId() {
		if ( wp.data && wp.data.select( 'core/editor' ) ) {
			var id = wp.data.select( 'core/editor' ).getCurrentPostId();

			if ( id ) {
				return id;
			}
		}

		return config.postId;
	}

	function render( suggestions ) {
		results.textContent = '';

		if ( ! suggestions.length ) {
			message( strings.none );
			return;
		}

		var list = document.createElement( 'ul' );
		list.style.margin = '0 0 8px';

		suggestions.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			var label = document.createElement( 'label' );
			var box = document.createElement( 'input' );

			box.type = 'checkbox';
			box.value = item.term_id;
			box.className = 'jevc-term';

			label.appendChild( box );
			label.appendChild(
				document.createTextNode( ' ' + item.name + ' (' + item.probability + ')' )
			);
			li.appendChild( label );
			list.appendChild( li );
		} );

		var apply = document.createElement( 'button' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.textContent = strings.apply;
		apply.addEventListener( 'click', applySelected );

		results.appendChild( list );
		results.appendChild( apply );
	}

	function applySelected() {
		var checked = Array.prototype.slice
			.call( results.querySelectorAll( '.jevc-term:checked' ) )
			.map( function ( box ) {
				return parseInt( box.value, 10 );
			} );

		if ( ! checked.length ) {
			return;
		}

		wp.apiFetch( {
			path: '/jev/v1/apply-terms',
			method: 'POST',
			data: { post_id: postId(), term_ids: checked }
		} )
			.then( function () {
				message( strings.applied );
			} )
			.catch( function ( error ) {
				message( ( error && error.message ) || strings.failed );
			} );
	}

	button.addEventListener( 'click', function () {
		button.disabled = true;
		message( strings.thinking );

		wp.apiFetch( {
			path: '/jev/v1/suggest-terms',
			method: 'POST',
			data: { post_id: postId() }
		} )
			.then( function ( response ) {
				render( ( response && response.suggestions ) || [] );
			} )
			.catch( function ( error ) {
				message( ( error && error.message ) || strings.failed );
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );
}( window.wp, window.jevcTagger ) );

/* Al-Ibiewi Studio Console: dashboard quick search */
( function () {
	'use strict';

	var data = window.alcDash;
	var input = document.getElementById( 'alc-search-input' );
	if ( ! data || ! input ) {
		return;
	}

	var wrap = input.closest( '.alc-search' );
	var panel = wrap.querySelector( '.alc-search__panel' );
	var list = wrap.querySelector( '.alc-search__results' );
	var items = [];
	var active = -1;
	var timer = null;
	var lastQuery = '';
	var request = 0;

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function open() {
		panel.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		wrap.classList.add( 'is-open' );
	}

	function close() {
		panel.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
		wrap.classList.remove( 'is-open' );
		active = -1;
	}

	function setActive( index ) {
		items.forEach( function ( li, i ) {
			li.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
		} );
		active = index;
		if ( index >= 0 && items[ index ] ) {
			input.setAttribute( 'aria-activedescendant', items[ index ].id );
			items[ index ].scrollIntoView( { block: 'nearest' } );
		} else {
			input.removeAttribute( 'aria-activedescendant' );
		}
	}

	function group( title, rows ) {
		if ( ! rows.length ) {
			return;
		}
		var head = el( 'li', 'alc-search__group', title );
		head.setAttribute( 'role', 'presentation' );
		list.appendChild( head );

		rows.forEach( function ( row ) {
			var li = el( 'li', 'alc-search__item' );
			li.id = 'alc-opt-' + items.length;
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'aria-selected', 'false' );

			var a = el( 'a' );
			a.href = row.url;
			a.tabIndex = -1;
			a.appendChild( el( 'span', 'alc-search__item-icon dashicons dashicons-' + ( row.icon || 'admin-generic' ) ) );
			var text = el( 'span', 'alc-search__item-text' );
			text.appendChild( el( 'span', 'alc-search__item-label', row.label ) );
			if ( row.meta ) {
				text.appendChild( el( 'span', 'alc-search__item-meta', row.meta ) );
			}
			a.appendChild( text );
			li.appendChild( a );

			li.addEventListener( 'mousemove', function () {
				setActive( items.indexOf( li ) );
			} );

			list.appendChild( li );
			items.push( li );
		} );
	}

	function render( actions, content, state ) {
		list.innerHTML = '';
		items = [];
		active = -1;

		group( data.i18n.actions, actions );
		group( data.i18n.content, content );

		if ( state === 'loading' && ! items.length ) {
			list.appendChild( el( 'li', 'alc-search__note', data.i18n.loading ) );
		} else if ( state === 'done' && ! items.length ) {
			list.appendChild( el( 'li', 'alc-search__note', data.i18n.none ) );
		}

		open();
	}

	function matchActions( q ) {
		q = q.toLowerCase();
		return data.actions.filter( function ( a ) {
			return ! q || ( a.label + ' ' + a.meta ).toLowerCase().indexOf( q ) !== -1;
		} ).slice( 0, q ? 4 : 6 );
	}

	function search( q ) {
		var actions = matchActions( q );
		if ( q.length < 2 ) {
			render( actions, [], 'idle' );
			return;
		}

		render( actions, [], 'loading' );
		var id = ++request;
		var url = data.ajax + '?action=alc_search&nonce=' + encodeURIComponent( data.nonce ) + '&q=' + encodeURIComponent( q );

		window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( id !== request ) {
					return;
				}
				render( actions, json && json.success ? json.data : [], 'done' );
			} )
			.catch( function () {
				if ( id === request ) {
					render( actions, [], 'done' );
				}
			} );
	}

	input.addEventListener( 'focus', function () {
		search( input.value.trim() );
	} );

	input.addEventListener( 'input', function () {
		var q = input.value.trim();
		if ( q === lastQuery ) {
			return;
		}
		lastQuery = q;
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			search( q );
		}, 180 );
	} );

	input.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			if ( panel.hidden ) {
				search( input.value.trim() );
			}
			setActive( items.length ? ( active + 1 ) % items.length : -1 );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			setActive( items.length ? ( active - 1 + items.length ) % items.length : -1 );
		} else if ( e.key === 'Enter' ) {
			var target = items[ active >= 0 ? active : 0 ];
			if ( target ) {
				e.preventDefault();
				window.location.href = target.querySelector( 'a' ).href;
			}
		} else if ( e.key === 'Escape' ) {
			close();
			input.blur();
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( ! wrap.contains( e.target ) ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		var t = e.target;
		var typing = t && ( t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test( t.tagName ) );
		if ( e.key === '/' && ! typing && ! e.metaKey && ! e.ctrlKey && ! e.altKey ) {
			e.preventDefault();
			input.focus();
		}
	} );
} )();

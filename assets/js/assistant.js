/* Al-Ibiewi Studio Console: website assistant widget.
   No dependencies. Model output is always rendered as text, never as HTML. */
( function () {
	'use strict';

	var cfg = window.alcAssistant;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n || {};
	var STORE = 'alc-ai-v1';
	var uid = 0;

	var ICON_CHAT = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 4.5h16v11.5H10l-4.5 3.5V16H4z"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 9h8M8 12.2h5"/></svg>';
	var ICON_CLOSE = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>';
	var ICON_PHONE = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M5 4h3.5l1.7 4.3-2.2 1.4a11 11 0 0 0 6.3 6.3l1.4-2.2L20 15.5V19a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1Z"/></svg>';
	var ICON_ARROW = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8"/></svg>';
	var ICON_BAG = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M5 8h14l-1 12H6L5 8Z"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>';
	var ICON_MAIL = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><rect x="3.5" y="5.5" width="17" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="m4 7 8 6 8-6"/></svg>';
	var ICON_SEND = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 12h13M13 6l6 6-6 6"/></svg>';

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined && text !== null ) {
			n.textContent = text;
		}
		return n;
	}

	/* Bold and [n] citations inside one line of text. */
	function inline( parent, line, sources ) {
		var parts = line.split( /(\*\*[^*]+\*\*|\[\d{1,2}\])/ );
		parts.forEach( function ( part ) {
			if ( ! part ) {
				return;
			}
			var bold = part.match( /^\*\*([^*]+)\*\*$/ );
			var cite = part.match( /^\[(\d{1,2})\]$/ );
			if ( bold ) {
				parent.appendChild( el( 'strong', null, bold[ 1 ] ) );
			} else if ( cite ) {
				var src = sources.filter( function ( s ) {
					return String( s.n ) === cite[ 1 ];
				} )[ 0 ];
				if ( src ) {
					var a = el( 'a', 'alc-ai__cite', cite[ 1 ] );
					a.href = src.url;
					a.title = src.title;
					a.setAttribute( 'aria-label', ( t.sources || 'Source' ) + ' ' + cite[ 1 ] + ': ' + src.title );
					parent.appendChild( a );
				}
			} else {
				parent.appendChild( document.createTextNode( part ) );
			}
		} );
	}

	/* Paragraphs and "- " lists. */
	function rich( text, sources ) {
		var frag = document.createDocumentFragment();
		var list = null;
		var para = null;
		String( text ).split( /\n/ ).forEach( function ( raw ) {
			var line = raw.replace( /\s+$/, '' );
			var item = line.match( /^\s*[-*•]\s+(.*)$/ );
			if ( item ) {
				para = null;
				if ( ! list ) {
					list = el( 'ul' );
					frag.appendChild( list );
				}
				var li = el( 'li' );
				inline( li, item[ 1 ], sources );
				list.appendChild( li );
			} else if ( line.trim() === '' ) {
				list = null;
				para = null;
			} else {
				list = null;
				if ( ! para ) {
					para = el( 'p' );
					frag.appendChild( para );
				} else {
					para.appendChild( document.createTextNode( ' ' ) );
				}
				inline( para, line, sources );
			}
		} );
		return frag;
	}

	function load() {
		try {
			var saved = JSON.parse( window.sessionStorage.getItem( STORE ) || 'null' );
			return saved && Array.isArray( saved.items ) ? saved.items.slice( -20 ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function save( items ) {
		try {
			window.sessionStorage.setItem( STORE, JSON.stringify( { items: items.slice( -20 ) } ) );
		} catch ( e ) {}
	}

	function boot( root ) {
		var mode = root.getAttribute( 'data-alc-assistant' );
		var bubble = mode === 'bubble';
		var id = 'alc-ai-' + ( ++uid );
		var items = bubble ? load() : [];
		var busy = false;

		var panel = el( 'div', 'alc-ai__panel' );
		panel.id = id;
		panel.setAttribute( 'role', bubble ? 'dialog' : 'region' );
		panel.setAttribute( 'aria-label', cfg.name );

		/* Header */
		var head = el( 'div', 'alc-ai__head' );
		var avatar = el( 'span', 'alc-ai__avatar' );
		if ( cfg.icon ) {
			var img = el( 'img' );
			img.src = cfg.icon;
			img.alt = '';
			avatar.appendChild( img );
		} else {
			avatar.innerHTML = ICON_CHAT;
		}
		var titles = el( 'span', 'alc-ai__titles' );
		titles.appendChild( el( 'span', 'alc-ai__name', cfg.name ) );
		titles.appendChild( el( 'span', 'alc-ai__tagline', t.tagline ) );
		head.appendChild( avatar );
		head.appendChild( titles );

		var closeBtn = null;
		if ( bubble ) {
			closeBtn = el( 'button', 'alc-ai__close' );
			closeBtn.type = 'button';
			closeBtn.setAttribute( 'aria-label', t.close );
			closeBtn.innerHTML = ICON_CLOSE;
			head.appendChild( closeBtn );
		}

		/* Conversation */
		var log = el( 'div', 'alc-ai__log' );
		log.setAttribute( 'role', 'log' );
		log.setAttribute( 'aria-live', 'polite' );

		var chips = el( 'div', 'alc-ai__suggest' );
		( cfg.suggestions || [] ).forEach( function ( q ) {
			var b = el( 'button', 'alc-ai__chip', q );
			b.type = 'button';
			b.addEventListener( 'click', function () {
				ask( q );
			} );
			chips.appendChild( b );
		} );

		/* Composer */
		var form = el( 'form', 'alc-ai__form' );
		var label = el( 'label', 'alc-ai__sr', t.placeholder );
		label.setAttribute( 'for', id + '-q' );
		var input = el( 'input', 'alc-ai__input' );
		input.id = id + '-q';
		input.type = 'text';
		input.maxLength = 400;
		input.autocomplete = 'off';
		input.placeholder = t.placeholder;
		var send = el( 'button', 'alc-ai__send' );
		send.type = 'submit';
		send.setAttribute( 'aria-label', t.send );
		send.innerHTML = ICON_SEND;
		form.appendChild( label );
		form.appendChild( input );
		form.appendChild( send );

		var foot = el( 'div', 'alc-ai__foot' );
		foot.appendChild( el( 'span', null, t.powered ) );
		if ( cfg.credit ) {
			var by = el( 'a', null, t.by );
			by.href = cfg.creditUrl;
			by.target = '_blank';
			by.rel = 'noopener';
			foot.appendChild( by );
		}

		panel.appendChild( head );
		panel.appendChild( log );
		panel.appendChild( chips );
		panel.appendChild( form );
		panel.appendChild( foot );

		/* Launcher */
		var launcher = null;
		if ( bubble ) {
			if ( cfg.position === 'left' ) {
				root.classList.add( 'alc-ai--left' );
			}
			root.style.setProperty( '--alc-ai-offset', ( parseInt( cfg.offset, 10 ) || 0 ) + 'px' );
			launcher = el( 'button', 'alc-ai__launcher' );
			launcher.type = 'button';
			launcher.setAttribute( 'aria-label', t.open );
			launcher.setAttribute( 'aria-expanded', 'false' );
			launcher.setAttribute( 'aria-controls', id );
			launcher.innerHTML = ICON_CHAT;
			panel.hidden = true;
			root.appendChild( panel );
			root.appendChild( launcher );
		} else {
			root.appendChild( panel );
		}

		function scroll() {
			log.scrollTop = log.scrollHeight;
		}

		function addBot( text, sources, extraCls, actions ) {
			var m = el( 'div', 'alc-ai__msg alc-ai__msg--bot' + ( extraCls ? ' ' + extraCls : '' ) );
			m.appendChild( rich( text, sources || [] ) );

			// Older saved replies only have sources: show them as buttons too.
			var list = actions && actions.length ? actions : ( sources || [] ).map( function ( s ) {
				return { kind: s.meta ? 'product' : 'page', label: s.title, meta: s.meta || '', url: s.url };
			} );
			if ( list.length ) {
				var box = el( 'div', 'alc-ai__actions' );
				list.forEach( function ( a ) {
					var b = el( 'a', 'alc-ai__action alc-ai__action--' + ( a.kind || 'page' ) );
					b.href = a.url;
					var icon = el( 'span', 'alc-ai__action-icon' );
					icon.innerHTML = a.kind === 'product' ? ICON_BAG : ( a.kind === 'contact' ? ICON_MAIL : ICON_ARROW );
					b.appendChild( icon );
					b.appendChild( el( 'span', 'alc-ai__action-label', a.label ) );
					if ( a.meta ) {
						b.appendChild( el( 'span', 'alc-ai__action-meta', a.meta ) );
					}
					box.appendChild( b );
				} );
				m.appendChild( box );
			}
			log.appendChild( m );
			scroll();
			return m;
		}

		function handoff( lead ) {
			if ( ! cfg.whatsapp ) {
				return null;
			}
			var wrap = el( 'div', 'alc-ai__handoff' );
			if ( lead ) {
				wrap.appendChild( el( 'span', 'alc-ai__handoff-lead', lead ) );
			}
			var a = el( 'a', 'alc-ai__wa' );
			a.href = cfg.whatsapp;
			a.target = '_blank';
			a.rel = 'noopener';
			a.innerHTML = ICON_PHONE;
			a.appendChild( el( 'span', null, t.whatsapp ) );
			wrap.appendChild( a );
			return wrap;
		}

		function addUser( text ) {
			log.appendChild( el( 'div', 'alc-ai__msg alc-ai__msg--user', text ) );
			scroll();
		}

		function render() {
			log.innerHTML = '';
			var hello = addBot( cfg.welcome || '' );
			var intro = handoff( t.person );
			if ( intro ) {
				hello.appendChild( intro );
			}
			items.forEach( function ( it ) {
				if ( it.role === 'user' ) {
					addUser( it.content );
				} else {
					var past = addBot( it.content, it.sources || [], it.error ? 'is-error' : ( it.gap ? 'is-gap' : '' ), it.actions || [] );
					if ( it.gap || it.error ) {
						var h1 = handoff();
						if ( h1 ) {
							past.appendChild( h1 );
						}
					}
				}
			} );
			chips.hidden = items.length > 0 || ! ( cfg.suggestions || [] ).length;
		}

		function history() {
			return items.filter( function ( it ) {
				return ! it.error;
			} ).slice( -6 ).map( function ( it ) {
				return { role: it.role, content: it.content };
			} );
		}

		function ask( q ) {
			q = String( q || '' ).trim();
			if ( ! q || busy ) {
				return;
			}
			busy = true;
			send.disabled = true;
			chips.hidden = true;
			var past = history();
			items.push( { role: 'user', content: q } );
			addUser( q );
			input.value = '';

			var wait = addBot( t.thinking, [], 'is-thinking' );

			var headers = { 'Content-Type': 'application/json' };
			if ( cfg.nonce ) {
				headers[ 'X-WP-Nonce' ] = cfg.nonce;
			}

			window.fetch( cfg.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: headers,
				body: JSON.stringify( { question: q, history: past } )
			} ).then( function ( r ) {
				return r.text().then( function ( body ) {
					var json = null;
					try {
						json = JSON.parse( body );
					} catch ( e ) {}
					return { ok: r.ok, status: r.status, json: json, body: body };
				} );
			} ).then( function ( res ) {
				wait.remove();
				if ( res.ok && res.json && typeof res.json.answer === 'string' ) {
					items.push( { role: 'assistant', content: res.json.answer, sources: res.json.sources || [], actions: res.json.actions || [], gap: ! res.json.found } );
					var reply = addBot( res.json.answer, res.json.sources || [], res.json.found ? '' : 'is-gap', res.json.actions || [] );
					if ( ! res.json.found ) {
						var h2 = handoff();
						if ( h2 ) {
							reply.appendChild( h2 );
						}
					}
				} else {
					var ours = res.json && res.json.code && String( res.json.code ).indexOf( 'alc_' ) === 0;
					var msg = ours ? res.json.message : t.error + ' (' + ( res.json && res.json.code ? res.json.code : 'HTTP ' + res.status ) + ')';
					if ( window.console ) {
						window.console.warn( '[Studio Console assistant]', res.status, res.json || String( res.body ).slice( 0, 300 ) );
					}
					items.push( { role: 'assistant', content: msg, error: true } );
					var bad = addBot( msg, [], 'is-error' );
					var h3 = handoff();
					if ( h3 ) {
						bad.appendChild( h3 );
					}
				}
			} ).catch( function ( err ) {
				wait.remove();
				if ( window.console ) {
					window.console.warn( '[Studio Console assistant]', err );
				}
				items.push( { role: 'assistant', content: t.offline, error: true } );
				var off = addBot( t.offline, [], 'is-error' );
				var h4 = handoff();
				if ( h4 ) {
					off.appendChild( h4 );
				}
			} ).then( function () {
				busy = false;
				send.disabled = false;
				if ( bubble ) {
					save( items );
				}
				input.focus();
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			ask( input.value );
		} );

		function open() {
			// If lifting left too little room for the panel, open from the corner.
			var lifted = parseInt( root.style.getPropertyValue( '--alc-ai-lift' ), 10 ) || 0;
			var room = window.innerHeight - ( 20 + ( parseInt( cfg.offset, 10 ) || 0 ) + lifted ) - 108;
			root.classList.toggle( 'is-tight', lifted > 0 && room < 460 );
			panel.hidden = false;
			root.classList.add( 'is-open' );
			launcher.setAttribute( 'aria-expanded', 'true' );
			launcher.innerHTML = ICON_CLOSE;
			launcher.setAttribute( 'aria-label', t.close );
			window.requestAnimationFrame( function () {
				panel.classList.add( 'is-in' );
				scroll();
				input.focus();
			} );
		}

		function close() {
			panel.classList.remove( 'is-in' );
			root.classList.remove( 'is-open' );
			root.classList.remove( 'is-tight' );
			launcher.setAttribute( 'aria-expanded', 'false' );
			launcher.innerHTML = ICON_CHAT;
			launcher.setAttribute( 'aria-label', t.open );
			panel.hidden = true;
			launcher.focus();
		}

		if ( bubble ) {
			launcher.addEventListener( 'click', function () {
				if ( panel.hidden ) {
					open();
				} else {
					close();
				}
			} );
			closeBtn.addEventListener( 'click', close );
			panel.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					close();
				}
			} );
		}

		/* Lift the bubble above other floating buttons in the same corner. */
		function floatingAncestor( node ) {
			for ( var depth = 0; node && node.nodeType === 1 && depth < 8; depth++ ) {
				if ( node === document.body || node === document.documentElement ) {
					return null;
				}
				var pos = window.getComputedStyle( node ).position;
				if ( pos === 'fixed' || pos === 'sticky' ) {
					return node;
				}
				node = node.parentElement;
			}
			return null;
		}

		function avoid() {
			if ( ! bubble || ! cfg.avoid || ! panel.hidden ) {
				return;
			}
			var lift = 0;
			var before = root.style.getPropertyValue( '--alc-ai-lift' ) || '0px';
			// Measure from the resting spot with the slide animation paused,
			// otherwise mid-animation positions get counted.
			root.style.transition = 'none';
			root.style.setProperty( '--alc-ai-lift', '0px' );
			void root.offsetHeight;
			for ( var pass = 0; pass < 4; pass++ ) {
				var r = launcher.getBoundingClientRect();
				var pad = 6;
				var points = [
					[ r.left - pad, r.top - pad ], [ r.right + pad, r.top - pad ],
					[ r.left - pad, r.bottom + pad ], [ r.right + pad, r.bottom + pad ],
					[ r.left + r.width / 2, r.top + r.height / 2 ], [ r.left - 40, r.top + r.height / 2 ]
				];
				var top = null;
				points.forEach( function ( p ) {
					var x = Math.max( 0, Math.min( window.innerWidth - 1, p[ 0 ] ) );
					var y = Math.max( 0, Math.min( window.innerHeight - 1, p[ 1 ] ) );
					( document.elementsFromPoint ? document.elementsFromPoint( x, y ) : [] ).forEach( function ( node ) {
						if ( root.contains( node ) ) {
							return;
						}
						var f = floatingAncestor( node );
						if ( ! f ) {
							return;
						}
						var fr = f.getBoundingClientRect();
						// Ignore full-screen overlays and headers at the top of the page.
						if ( fr.height > window.innerHeight * 0.5 || fr.bottom < window.innerHeight * 0.5 ) {
							return;
						}
						top = top === null ? fr.top : Math.min( top, fr.top );
					} );
				} );
				if ( top === null || top >= r.bottom ) {
					break;
				}
				lift += Math.ceil( r.bottom - top + 12 );
				lift = Math.min( lift, Math.round( window.innerHeight * 0.5 ) );
				root.style.setProperty( '--alc-ai-lift', lift + 'px' );
				void root.offsetHeight;
			}
			// Animate from where it was to where it should be.
			root.style.setProperty( '--alc-ai-lift', before );
			void root.offsetHeight;
			root.style.transition = '';
			root.style.setProperty( '--alc-ai-lift', lift + 'px' );
		}

		if ( bubble && cfg.avoid ) {
			[ 300, 1200, 3000, 6000 ].forEach( function ( ms ) {
				window.setTimeout( avoid, ms );
			} );
			window.addEventListener( 'resize', function () {
				window.clearTimeout( avoid.t );
				avoid.t = window.setTimeout( avoid, 250 );
			} );
		}

		render();
	}

	function start() {
		var nodes = document.querySelectorAll( '[data-alc-assistant]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( ! nodes[ i ].getAttribute( 'data-alc-ready' ) ) {
				nodes[ i ].setAttribute( 'data-alc-ready', '1' );
				boot( nodes[ i ] );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();

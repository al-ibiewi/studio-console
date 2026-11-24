/* Al-Ibiewi Studio Console: settings screen */
( function ( $ ) {
	'use strict';

	function rgb( hex ) {
		hex = String( hex || '' ).replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}
		if ( ! /^[0-9a-f]{6}$/i.test( hex ) ) {
			return null;
		}
		return [ 0, 2, 4 ].map( function ( i ) {
			return parseInt( hex.substr( i, 2 ), 16 );
		} );
	}

	function lum( c ) {
		var v = c.map( function ( x ) {
			x /= 255;
			return x <= 0.03928 ? x / 12.92 : Math.pow( ( x + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * v[ 0 ] + 0.7152 * v[ 1 ] + 0.0722 * v[ 2 ];
	}

	function contrast( a, b ) {
		var la = lum( a );
		var lb = lum( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	function ink( c ) {
		return contrast( c, [ 255, 255, 255 ] ) >= contrast( c, [ 11, 11, 11 ] ) ? [ 255, 255, 255 ] : [ 11, 11, 11 ];
	}

	function rgba( c, a ) {
		return 'rgba(' + c.join( ',' ) + ',' + a + ')';
	}

	function hex( c ) {
		return '#' + c.map( function ( x ) {
			return ( '0' + x.toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	// Live preview of the chosen theme on this screen and the sidebar.
	function preview() {
		var $checked = $( '.alc-themes input:checked' );
		if ( ! $checked.length ) {
			return;
		}
		var panel = rgb( $( '#alc_panel_bg' ).val() ) || rgb( $checked.data( 'panel' ) );
		var accent = rgb( $( '#alc_accent' ).val() ) || rgb( $checked.data( 'accent' ) );
		if ( ! panel || ! accent ) {
			return;
		}
		var pInk = ink( panel );
		var vars = {
			'--alc-panel': hex( panel ),
			'--alc-panel-ink': hex( pInk ),
			'--alc-panel-muted': rgba( pInk, 0.68 ),
			'--alc-panel-line': rgba( pInk, 0.14 ),
			'--alc-panel-hover': rgba( pInk, 0.08 ),
			'--alc-accent': hex( accent ),
			'--alc-accent-ink': hex( ink( accent ) ),
			'--alc-accent-soft': rgba( accent, 0.14 ),
			'--alc-accent-ring': rgba( accent, 0.32 ),
			'--alc-accent-on-panel': contrast( accent, panel ) >= 2 ? hex( accent ) : hex( pInk )
		};
		[ document.body, document.querySelector( '.alc-settings' ) ].forEach( function ( node ) {
			if ( node ) {
				Object.keys( vars ).forEach( function ( k ) {
					node.style.setProperty( k, vars[ k ] );
				} );
			}
		} );
	}

	$( function () {
		$( '.alc-color' ).wpColorPicker( {
			change: function ( event, ui ) {
				$( event.target ).val( ui.color.toString() );
				preview();
			},
			clear: function () {
				window.setTimeout( preview, 0 );
			}
		} );

		$( document ).on( 'change', '.alc-themes input', preview );

		$( '.alc-media' ).each( function () {
			var $wrap = $( this );
			var $input = $wrap.find( '.alc-media__input' );
			var $preview = $wrap.find( '.alc-media__preview' );
			var frame;

			$wrap.on( 'click', '.alc-media__choose', function ( event ) {
				event.preventDefault();

				if ( ! frame ) {
					frame = wp.media( {
						title: window.alcSettings.chooseTitle,
						button: { text: window.alcSettings.chooseButton },
						library: { type: 'image' },
						multiple: false
					} );

					frame.on( 'select', function () {
						var image = frame.state().get( 'selection' ).first().toJSON();
						var src = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
						$input.val( image.id ).trigger( 'change' );
						$preview.empty().append( $( '<img>', { src: src, alt: '' } ) );
						$wrap.addClass( 'has-image' );
					} );
				}

				frame.open();
			} );

			$wrap.on( 'click', '.alc-media__remove', function ( event ) {
				event.preventDefault();
				$input.val( '0' ).trigger( 'change' );
				$preview.empty();
				$wrap.removeClass( 'has-image' );
			} );
		} );
	} );
} )( jQuery );

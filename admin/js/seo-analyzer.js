/* PB MEDIA ALL SEO — live analyzer */
( function ( $ ) {
	'use strict';

	if ( typeof window.PBSeoAnalyzer === 'undefined' ) {
		return;
	}

	var cfg     = window.PBSeoAnalyzer;
	var $widget = $( '#pb-seo-analyzer' );
	if ( ! $widget.length ) {
		return;
	}

	var fields = {
		title:       '#pb_seo_pageTitleSEO',
		description: '#pb_seo_pageDescriptionSEO',
		keywords:    '#pb_seo_pageKeywordsSEO'
	};

	var debounceTimer = null;
	function debounce( fn, ms ) {
		return function () {
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( fn, ms );
		};
	}

	function getContent() {
		// Classic editor.
		if ( typeof window.tinymce !== 'undefined' ) {
			var ed = window.tinymce.get( 'content' );
			if ( ed && ! ed.isHidden() ) {
				return ed.getContent( { format: 'text' } );
			}
		}
		var $textarea = $( '#content' );
		if ( $textarea.length ) {
			return $textarea.val() || '';
		}
		// Gutenberg.
		if ( window.wp && window.wp.data && window.wp.data.select ) {
			try {
				var blocks = window.wp.data.select( 'core/editor' ).getBlocks();
				return blocks.map( function ( b ) {
					return ( b.attributes && ( b.attributes.content || '' ) ) || '';
				} ).join( ' ' );
			} catch ( e ) {}
		}
		return '';
	}

	function collect() {
		return {
			post_id:     cfg.postId,
			title:       $( fields.title ).val() || '',
			description: $( fields.description ).val() || '',
			keywords:    $( fields.keywords ).val() || '',
			content:     getContent()
		};
	}

	function render( data ) {
		var $score = $widget.find( '.pb-seo-score-num' );
		var $circle = $widget.find( '.pb-seo-score-circle' );
		var $label = $widget.find( '.pb-seo-score-label' );

		$score.text( data.score );
		$circle.attr( 'data-score', data.score );
		$label.attr( 'data-grade', data.grade ).text( data.grade_label );

		var $list = $widget.find( '.pb-seo-checks' ).empty();
		( data.checks || [] ).forEach( function ( c ) {
			$( '<li/>', {
				'class':       'pb-seo-check pb-seo-check--' + c.status,
				'data-key':    c.key
			} )
				.append( $( '<span/>', { 'class': 'pb-seo-check-icon' } ) )
				.append( $( '<span/>', { 'class': 'pb-seo-check-text', text: c.message } ) )
				.appendTo( $list );
		} );
	}

	function refresh() {
		$.ajax( {
			url:     cfg.restUrl,
			method:  'POST',
			data:    collect(),
			headers: { 'X-WP-Nonce': cfg.nonce }
		} ).done( render );
	}

	var triggerRefresh = debounce( refresh, 500 );

	$( document ).on( 'input change keyup',
		fields.title + ',' + fields.description + ',' + fields.keywords,
		triggerRefresh
	);

	// Also react to content changes (Classic).
	$( document ).on( 'input change', '#content', triggerRefresh );

	// Gutenberg subscription.
	if ( window.wp && window.wp.data && window.wp.data.subscribe ) {
		var lastBlocks = '';
		window.wp.data.subscribe( function () {
			try {
				var current = window.wp.data.select( 'core/editor' ).getEditedPostContent();
				if ( current !== lastBlocks ) {
					lastBlocks = current;
					triggerRefresh();
				}
			} catch ( e ) {}
		} );
	}

	// Initial refresh after a short delay to pick up content.
	setTimeout( refresh, 800 );
} )( jQuery );

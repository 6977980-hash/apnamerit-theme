<?php
/**
 * Admission documents checklist (novel idea #7, 2026-11-05): [am_doc_checklist] from data/documents.json.
 *
 * Server-side HTML lists every university's documents (readable without JavaScript and by crawlers).
 * am.js (AM docs) then shows one university, hides items for cases the student did not tick, keeps the
 * ticks in localStorage and prints. No new request, no new file.
 */

defined( 'ABSPATH' ) || exit;

function am_sc_doc_checklist( $atts ): string {
	$atts = shortcode_atts( array( 'uni' => '' ), (array) $atts, 'am_doc_checklist' );
	$d    = am_data( 'documents' );
	$unis = (array) ( $d['universities'] ?? array() );
	if ( ! $unis ) {
		return am_stub( 'am_doc_checklist', $atts );
	}
	$groups = (array) ( $d['groups'] ?? array() );
	$stages = (array) ( $d['stages'] ?? array() );
	$pick   = (string) $atts['uni'];

	$opts = '';
	$secs = '';
	foreach ( $unis as $u ) {
		$slug = am_slug( (string) $u['slug'] );
		$used = array();
		$body = '';
		foreach ( $stages as $st => $st_label ) {
			$lis = '';
			foreach ( (array) $u['items'] as $i => $it ) {
				if ( ( $it['st'] ?? '' ) !== $st ) {
					continue;
				}
				$for  = array_values( array_intersect( (array) ( $it['for'] ?? array() ), array_keys( $groups ) ) );
				$used = array_merge( $used, $for );
				$who  = $for ? ' <span class="am-chip">' . esc_html( implode( ' / ', array_map( fn( $g ) => (string) $groups[ $g ], $for ) ) ) . '</span>' : '';
				$note = ! empty( $it['n'] ) ? '<small class="am-muted">' . esc_html( (string) $it['n'] ) . '</small>' : '';
				$lis .= '<li' . ( $for ? ' data-for="' . esc_attr( implode( ' ', $for ) ) . '"' : '' ) . '><label class="am-check"><input type="checkbox" data-k="' . esc_attr( $slug . '-' . $i ) . '"><span>'
					. esc_html( (string) $it['t'] ) . $who . ' <span class="am-muted">(p. ' . esc_html( (string) $it['p'] ) . ')</span>' . $note . '</span></label></li>';
			}
			if ( '' !== $lis ) {
				$body .= '<h3>' . esc_html( (string) $st_label ) . '</h3><ul class="am-dl">' . $lis . '</ul>';
			}
		}
		$page  = ! empty( $u['page_link'] ) ? am_find_page( (string) $u['page_link'] ) : array();
		$used  = array_values( array_unique( $used ) );
		$opts .= '<option value="' . esc_attr( $slug ) . '"' . selected( $pick, $slug, false ) . '>' . esc_html( (string) $u['name'] ) . '</option>';
		$secs .= '<section class="am-docs-uni" id="docs-' . esc_attr( $slug ) . '" data-am-docs-uni="' . esc_attr( $slug ) . '" data-used="' . esc_attr( implode( ' ', $used ) ) . '">'
			. '<h2>' . esc_html( (string) $u['name'] . ' documents (' . (string) $u['session'] . ')' ) . '</h2>'
			. '<p>' . esc_html( (string) ( $u['intro'] ?? '' ) ) . ' <span class="am-muted" data-am-docs-count></span></p>'
			. $body
			. '<p class="am-muted">Source: <a href="' . esc_url( (string) $u['source_url'] ) . '" rel="noopener" target="_blank">' . esc_html( (string) $u['source_label'] ) . '</a> (page numbers as printed). '
			. esc_html( (string) ( $u['dates'] ?? '' ) )
			. ( $page ? ' <a href="' . esc_url( home_url( (string) $page['path'] ) ) . '">' . esc_html( am_page_label( $page ) ) . '</a>' : '' ) . '</p>'
			. '</section>';
	}

	$ticks = '';
	foreach ( $groups as $g => $label ) {
		$ticks .= '<label class="am-check" data-g="' . esc_attr( (string) $g ) . '"><input type="checkbox" value="' . esc_attr( (string) $g ) . '"> ' . esc_html( (string) $label ) . '</label>';
	}
	$when = ! empty( $d['checked'] ) ? 'Checked against the official documents on ' . gmdate( 'j M Y', (int) strtotime( (string) $d['checked'] ) ) . '.' : '';

	return '<div class="am-docs-wrap"><form class="am-calc am-js-only am-docs-pick" data-am-docs><fieldset class="am-formula"><legend>Your documents list</legend>'
		. '<div class="am-field"><label for="am-docs-uni">University</label><select id="am-docs-uni">' . $opts . '</select></div>'
		. '<fieldset class="am-docs-g"><legend>Tick what applies to you (leave empty for open merit)</legend>' . $ticks . '</fieldset></fieldset>'
		. '<div class="am-actions"><button type="button" class="am-btn am-btn--primary" data-am-docs-print>Print or save as PDF</button> <button type="button" class="am-btn" data-am-docs-clear>Clear ticks</button></div>'
		. '<p class="am-muted">Your ticks stay on this device only.</p></form>'
		. '<div class="am-docs">' . $secs . '</div>'
		. '<p class="am-muted">' . esc_html( $when ) . ' Each university can change its list in a new prospectus or notice: the official document always wins.</p></div>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'am_doc_checklist', 'am_sc_doc_checklist' );
	}
);

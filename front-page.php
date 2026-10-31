<?php
/**
 * Homepage. Phase 0 placeholder; Phase 1+ renders the all-universities aggregate calculator here.
 */

defined( 'ABSPATH' ) || exit;
get_header();
$season_year = (int) am_site( 'season_year' );
?>
<div class="am-wrap am-hero">
	<h1>Aggregate Calculator for Pakistani Universities (<?php echo esc_html( (string) $season_year ); ?>)</h1>
	<p class="am-lead">Calculate your aggregate, see where last year's closing merit says you can get in, and open the full merit list for MDCAT, UET, NUST, COMSATS, FAST, PU and more.</p>
	<p class="am-soon">Calculators are launching soon.</p>
</div>
<?php
get_footer();

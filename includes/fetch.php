<?php
	// Compsorer
	require_once WAF__PLUGIN_DIR . 'vendor/autoload.php';

	// General PHP Tools
	require_once( WAF__PLUGIN_DIR . 'includes/tools.php' );
	// AWS Tools
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/aws.php' );
	// Fetch Buoy Data
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/buoys.php' );
	// Fetch Wave Data
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/waves.php' );
	// Fetch Memplots
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/memplots.php' );
	// File Cache
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/cache.php' );
	// Spotter Wave Data
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/spotter.php' );
	// Local CSV Wave Data
	require_once( WAF__PLUGIN_DIR . 'includes/fetch/local.php' );
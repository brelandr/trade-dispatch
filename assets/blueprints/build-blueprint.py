#!/usr/bin/env python3
"""Build Playground blueprint.json (root + assets/blueprints)."""

from __future__ import annotations

import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
CSS = (HERE / "playground-pack.css").read_text(encoding="utf-8")

MU_PLUGIN = r"""<?php
/**
 * Playground-only pack chrome for Trade Dispatch Live Preview.
 */
add_filter(
	'body_class',
	static function ( $classes ) {
		$extra = array(
			'trdsp-playground-pack',
			'trdsp-has-pack',
			'trdsp-pack-fullscreen-cover',
			'trdsp-frame-fullscreen',
			'trdsp-surface-dark',
			'trdsp-font-condensed',
			'trdsp-header-transparent',
			'trdsp-radius-sharp',
			'trdsp-pattern-wash',
		);
		return array_merge( (array) $classes, $extra );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'trdsp-playground-pack',
			content_url( 'mu-plugins/trdsp-playground-pack.css' ),
			array(),
			'0.3.53'
		);
	}
);

add_filter(
	'template_include',
	static function ( $template ) {
		if ( is_front_page() ) {
			$home = WP_CONTENT_DIR . '/mu-plugins/trdsp-playground-home.php';
			if ( is_readable( $home ) ) {
				return $home;
			}
		}
		return $template;
	}
);
"""

HOME = r"""<?php
/**
 * Full-viewport pack homepage for Playground.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div class="trdsp-theme-shell">
	<header class="trdsp-theme-header"><div class="inner">
		<a class="trdsp-theme-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">Greenline Lawn Co.</a>
		<nav>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
			<a href="#services">Services</a>
			<a href="#book">Book</a>
			<a href="<?php echo esc_url( home_url( '/customer-portal/' ) ); ?>">Portal</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=trade-dispatch' ) ); ?>">Office</a>
		</nav>
	</div></header>
	<main class="trdsp-theme-main">
		<div class="trdsp-theme-hero">
			<div class="trdsp-theme-hero-copy">
				<h1>Greenline Lawn Co.</h1>
				<p>Scheduled lawn care for Austin yards. Book a visit or open the customer portal.</p>
				<div class="wp-block-buttons">
					<div class="wp-block-button"><a class="wp-block-button__link" href="#book">Book a visit</a></div>
					<div class="wp-block-button is-style-outline"><a class="wp-block-button__link" href="<?php echo esc_url( home_url( '/customer-portal/' ) ); ?>">Customer portal</a></div>
				</div>
			</div>
		</div>
		<div class="trdsp-theme-band" id="services">
			<div class="wp-block-columns trdsp-theme-cards">
				<div class="wp-block-column"><h3>Weekly mow</h3><p>Edge, blow, and a consistent route.</p></div>
				<div class="wp-block-column"><h3>Aeration</h3><p>Spring cores so water and seed reach the soil.</p></div>
				<div class="wp-block-column"><h3>Irrigation</h3><p>Heads, zones, and a leak check before summer.</p></div>
			</div>
		</div>
		<p class="trdsp-theme-trust">Licensed · Your WordPress site · Your data</p>
		<div class="trdsp-theme-reviews"><p>“They showed up when they said they would, left the gate as they found it, and the next visit was already on the calendar.” — Jordan Hale, East Austin</p></div>
		<div class="trdsp-theme-service-list">
			<h2>On the truck this week</h2>
			<ul class="trdsp-theme-services"><li>Weekly mow</li><li>Aeration</li><li>Irrigation</li></ul>
		</div>
		<section class="trdsp-theme-book" id="book">
			<h2>Ready for the next visit?</h2>
			<p>Request a time. The office confirms the appointment before a customer account is created.</p>
			<?php echo do_shortcode( '[trdsp_booking]' ); ?>
		</section>
	</main>
	<footer class="trdsp-theme-footer"><div class="inner">
		<span>Trade Dispatch preview</span>
		<nav>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=trade-dispatch' ) ); ?>">Jobs</a>
			<a href="<?php echo esc_url( home_url( '/customer-portal/' ) ); ?>">Portal</a>
		</nav>
	</div></footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
"""

SEED = r"""<?php
require_once '/wordpress/wp-load.php';

if ( ! class_exists( 'TRDSP_Customers' ) || ! class_exists( 'TRDSP_Jobs' ) ) {
	return;
}

$admin    = get_user_by( 'login', 'admin' );
$admin_id = $admin ? (int) $admin->ID : 1;

update_option( 'blogname', 'Greenline Lawn Co.' );
update_option( 'blogdescription', 'Austin, TX' );

$settings = get_option( 'trdsp_settings', array() );
if ( ! is_array( $settings ) ) {
	$settings = array();
}
$settings['business_name']      = 'Greenline Lawn Co.';
$settings['notify_email']       = 'admin@example.com';
$settings['booking_hours_hint'] = 'Weekdays 8:00 AM – 5:00 PM. Rain days move to the next open slot.';
$settings['booking_open']       = '08:00';
$settings['booking_close']      = '17:00';
$settings['booking_days']       = array( 1, 2, 3, 4, 5 );
update_option( 'trdsp_settings', $settings );

$now         = gmdate( 'Y-m-d H:i:s' );
$service_ids = array();
if ( class_exists( 'TRDSP_Services' ) ) {
	global $wpdb;
	$table   = TRDSP_Services::table();
	$catalog = array(
		array( 'Weekly mow', 'Edge, blow, and a consistent route.', 60, '55.00' ),
		array( 'Aeration', 'Spring cores so water and seed reach the soil.', 90, '175.00' ),
		array( 'Irrigation', 'Heads, zones, and a leak check before summer.', 75, '125.00' ),
	);
	foreach ( $catalog as $item ) {
		$wpdb->insert(
			$table,
			array(
				'name'            => $item[0],
				'description'     => $item[1],
				'default_minutes' => $item[2],
				'default_amount'  => $item[3],
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		$service_ids[] = (int) $wpdb->insert_id;
	}
}
$mow   = isset( $service_ids[0] ) ? $service_ids[0] : 0;
$aerate = isset( $service_ids[1] ) ? $service_ids[1] : 0;
$irrig = isset( $service_ids[2] ) ? $service_ids[2] : 0;

$rivera = TRDSP_Customers::save(
	array(
		'name'      => 'Maya Rivera',
		'email'     => 'maya.rivera@example.com',
		'phone'     => '(555) 201-4400',
		'address_1' => '1842 Oak Ridge Dr',
		'city'      => 'Austin',
		'state'     => 'TX',
		'postcode'  => '78704',
		'notes'     => 'Prefers morning windows. Dog in backyard — use side gate.',
	)
);
$chen = TRDSP_Customers::save(
	array(
		'name'      => 'James Chen',
		'email'     => 'james.chen@example.com',
		'phone'     => '(555) 201-4411',
		'address_1' => '90 Commerce Blvd',
		'city'      => 'Austin',
		'state'     => 'TX',
		'postcode'  => '78745',
		'notes'     => 'Retail suite. Ask for the store manager.',
	)
);
$brooks = TRDSP_Customers::save(
	array(
		'name'      => 'Alicia Brooks',
		'email'     => 'alicia.brooks@example.com',
		'phone'     => '(555) 201-4422',
		'address_1' => '512 Cedar Lane',
		'city'      => 'Austin',
		'state'     => 'TX',
		'postcode'  => '78702',
		'notes'     => 'New customer from the public booking form.',
	)
);

if ( is_wp_error( $rivera ) || is_wp_error( $chen ) || is_wp_error( $brooks ) ) {
	return;
}

$tomorrow  = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS + 9 * HOUR_IN_SECONDS );
$today     = gmdate( 'Y-m-d H:i:s', time() + 3 * HOUR_IN_SECONDS );
$nextweek  = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS + 10 * HOUR_IN_SECONDS );
$yesterday = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

$job_mow = TRDSP_Jobs::save(
	array(
		'customer_id'      => $rivera,
		'assigned_user_id' => $admin_id,
		'service_id'       => $mow,
		'title'            => 'Weekly mow — Oak Ridge',
		'status'           => 'scheduled',
		'scheduled_at'     => $tomorrow,
		'gate_notes'       => 'Side gate latch sticks. Code 4412 on the lockbox.',
		'hazard_notes'     => 'Dog in backyard until 9 AM.',
		'office_brief'     => 'Edge the walk. Blow the drive toward the street.',
		'recurrence'       => 'weekly',
	)
);
TRDSP_Jobs::save(
	array(
		'customer_id'      => $chen,
		'assigned_user_id' => $admin_id,
		'service_id'       => $irrig,
		'title'            => 'Irrigation leak check',
		'status'           => 'in_progress',
		'scheduled_at'     => $today,
		'office_brief'     => 'Manager on site. Start at zone 3.',
	)
);
TRDSP_Jobs::save(
	array(
		'customer_id'  => $brooks,
		'service_id'   => $aerate,
		'title'        => 'Aeration request',
		'status'       => 'requested',
		'scheduled_at' => '',
		'office_brief' => 'Came in from the public booking form. Confirm a weekday morning.',
	)
);
$done = TRDSP_Jobs::save(
	array(
		'customer_id'      => $rivera,
		'assigned_user_id' => $admin_id,
		'service_id'       => $mow,
		'title'            => 'Weekly mow — last visit',
		'status'           => 'completed',
		'scheduled_at'     => $yesterday,
	)
);
TRDSP_Jobs::save(
	array(
		'customer_id'      => $chen,
		'assigned_user_id' => $admin_id,
		'service_id'       => $mow,
		'title'            => 'Weekly mow — Commerce',
		'status'           => 'scheduled',
		'scheduled_at'     => $nextweek,
		'recurrence'       => 'weekly',
	)
);

if ( class_exists( 'TRDSP_Notes' ) && ! is_wp_error( $job_mow ) ) {
	TRDSP_Notes::add( (int) $job_mow, 'Customer asked for a text 30 minutes before arrival.', $admin_id );
}

if ( class_exists( 'TRDSP_Estimates' ) && ! is_wp_error( $done ) ) {
	TRDSP_Estimates::save(
		array(
			'customer_id' => $rivera,
			'job_id'      => $done,
			'title'       => 'Aeration + extra bags',
			'amount'      => 210.00,
			'status'      => 'sent',
		)
	);
}

$home_id = (int) wp_insert_post(
	array(
		'post_title'   => 'Home',
		'post_name'    => 'home',
		'post_content' => '<!-- wp:shortcode -->[trdsp_booking]<!-- /wp:shortcode -->',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	),
	true
);
$portal_id = (int) wp_insert_post(
	array(
		'post_title'   => 'Customer portal',
		'post_name'    => 'customer-portal',
		'post_content' => '<!-- wp:shortcode -->[trdsp_portal]<!-- /wp:shortcode -->',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	),
	true
);

$settings = get_option( 'trdsp_settings', array() );
if ( ! is_array( $settings ) ) {
	$settings = array();
}
if ( $portal_id > 0 ) {
	$settings['portal_page_id'] = $portal_id;
	update_option( 'trdsp_settings', $settings );
}
if ( $home_id > 0 ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
}
"""

blueprint = {
    "$schema": "https://playground.wordpress.net/blueprint-schema.json",
    "landingPage": "/",
    "preferredVersions": {"php": "8.2", "wp": "latest"},
    "features": {"networking": True},
    "steps": [
        {"step": "login", "username": "admin", "password": "password"},
        {
            "step": "installPlugin",
            "options": {"activate": True},
            "pluginData": {"resource": "wordpress.org/plugins", "slug": "trade-dispatch"},
        },
        {
            "step": "writeFile",
            "path": "/wordpress/wp-content/mu-plugins/trdsp-playground-pack.css",
            "data": CSS,
        },
        {
            "step": "writeFile",
            "path": "/wordpress/wp-content/mu-plugins/trdsp-playground-pack.php",
            "data": MU_PLUGIN,
        },
        {
            "step": "writeFile",
            "path": "/wordpress/wp-content/mu-plugins/trdsp-playground-home.php",
            "data": HOME,
        },
        {"step": "runPHP", "code": SEED},
    ],
}

text = json.dumps(blueprint, indent="\t", ensure_ascii=False) + "\n"
(HERE / "blueprint.json").write_text(text, encoding="utf-8")
(ROOT / "blueprint.json").write_text(text, encoding="utf-8")
print("wrote", HERE / "blueprint.json", "bytes", len(text.encode()))

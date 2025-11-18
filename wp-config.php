<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'test' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         ':Ysb,hMYzl;e:LQ+`N8[Y?a6UY|p>`|~WM9/qP[ |5Lhdb); ~j#LwTyF*o6MPy9' );
define( 'SECURE_AUTH_KEY',  'z3mqmrBksspXREn=D=bcJb>RMKI3;GQtN$4C*FLh_@U;-*?KW[WwZ@CN8>;{8v0~' );
define( 'LOGGED_IN_KEY',    '&]qO.e$N2Mv53pTD|>gqVtZYNAmy,p 5/z{OE1D(p^xz(VY/=w(R%$X$d%^)FQYs' );
define( 'NONCE_KEY',        'EUYo[(ppfH)7T5:ZLtRMxgTZkqqxJ{-qs(*`msd0;g6>}@p=43V)qg852y,}mw$.' );
define( 'AUTH_SALT',        '9{6zRC77Ahd>Y:w~d<;*m?F)Z3n.74^v.bTLu)Df.fN}Xf?P-p?>LC6H=.5cI~?G' );
define( 'SECURE_AUTH_SALT', '5@H?0IfAy;ihL_FO{:$XN#Xy5@.Ei4y=+efP~cF-L%~6J[~{NJ/LBXn]eT ) J7e' );
define( 'LOGGED_IN_SALT',   '1>%>Q|nkX`2ukdG0.t5*yk@qD2wWN(;Uw3k2yy,VqN5%@=NQo<u@KtHem2P=$Gq_' );
define( 'NONCE_SALT',       '/C:L@O4FNdC-G{@|Qw+JF]}?5dB!c,_sMNz<f>72zAC<RKT:&b8P7G-9{,QZYZkb' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

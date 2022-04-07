<?php
/**
 * WPCOMSH functions file.
 *
 * @package wpcomsh
 */

/**
 * Whether the theme is a wpcom theme.
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function wpcomsh_is_wpcom_theme( $theme_slug ) {
	return wpcomsh_is_wpcom_premium_theme( $theme_slug ) || wpcomsh_is_wpcom_pub_theme( $theme_slug );
}

/**
 * Whether the theme is a premium wpcom theme.
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function wpcomsh_is_wpcom_premium_theme( $theme_slug ) {
	if (
		! defined( 'WPCOMSH_PREMIUM_THEMES_PATH' ) ||
		! file_exists( WPCOMSH_PREMIUM_THEMES_PATH )
	) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log(
			"WPComSH: WPCom premium themes folder couldn't be located. " .
			'Check whether the ' . WPCOMSH_PREMIUM_THEMES_PATH . ' constant points to the correct directory.'
		);

		return false;
	}

	return file_exists(
		WPCOMSH_PREMIUM_THEMES_PATH . "/{$theme_slug}"
	);
}

/**
 * Whether the theme is a free wpcom theme.
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function wpcomsh_is_wpcom_pub_theme( $theme_slug ) {
	if (
		! defined( 'WPCOMSH_PUB_THEMES_PATH' ) ||
		! file_exists( WPCOMSH_PUB_THEMES_PATH )
	) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log(
			"WPComSH: WPCom pub themes folder couldn't be located. " .
			'Check whether the ' . WPCOMSH_PUB_THEMES_PATH . ' constant points to the correct directory.'
		);

		return false;
	}

	return file_exists(
		WPCOMSH_PUB_THEMES_PATH . "/{$theme_slug}"
	);
}

/**
 * Symlinks a wpcom theme.
 *
 * @param string $theme_slug Theme slug.
 * @param string $theme_type Type of theme.
 * @return bool|WP_Error
 */
function wpcomsh_symlink_theme( $theme_slug, $theme_type ) {
	$themes_source_path = '';

	if ( WPCOMSH_PUB_THEME_TYPE === $theme_type ) {
		$themes_source_path = WPCOMSH_PUB_THEMES_SYMLINK;
	} elseif ( WPCOMSH_PREMIUM_THEME_TYPE === $theme_type ) {
		$themes_source_path = WPCOMSH_PREMIUM_THEMES_SYMLINK;
	}

	$abs_theme_path         = $themes_source_path . "/{$theme_slug}";
	$abs_theme_symlink_path = get_theme_root() . '/' . $theme_slug;

	if ( ! file_exists( $abs_theme_path ) ) {
		$error_message = "Source theme directory doesn't exists at: ${abs_theme_path}";

		error_log( 'WPComSH: ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		return new WP_Error( 'error_symlinking_theme', $error_message );
	}

	if ( ! symlink( $abs_theme_path, $abs_theme_symlink_path ) ) {
		$theme_source_folder_path = WPCOMSH_PUB_THEME_TYPE === $theme_type
			? WPCOMSH_PUB_THEMES_PATH
			: WPCOMSH_PREMIUM_THEMES_PATH;

		$error_message = sprintf(
			'Can\'t symlink theme with slug: %1$s. Make sure it exists in the %2$s directory.',
			$theme_slug,
			$theme_source_folder_path
		);

		error_log( 'WPComSH: ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		return new WP_Error( 'error_symlinking_theme', $error_message );
	}

	return true;
}

/**
 * Deletes cache of the passed theme.
 *
 * @param string $theme_slug Optional. Slug of the theme to delete cache for.
 *                           Default: Current theme.
 */
function wpcomsh_delete_theme_cache( $theme_slug = null ) {
	$theme = wp_get_theme( $theme_slug );

	if ( $theme instanceof WP_Theme ) {
		$theme->cache_delete();
	}
}

/**
 * Checks whether a theme (by theme slug) is symlinked in the themes' directory.
 *
 * @param string $theme_slug The slug of a theme.
 * @return bool Whether a theme is symlinked in the themes' directory.
 */
function wpcomsh_is_theme_symlinked( $theme_slug ) {
	$theme_root  = get_theme_root();
	$theme_dir   = "$theme_root/$theme_slug";
	$site_themes = scandir( $theme_root );

	return in_array( $theme_slug, $site_themes, true ) && is_link( $theme_dir );
}

/**
 * Deletes a symlinked theme.
 *
 * @param string $theme_slug The slug of a theme.
 * @return bool|WP_Error True on success, WP_Error on error.
 */
function wpcomsh_delete_symlinked_theme( $theme_slug ) {
	$theme_dir = get_theme_root() . "/$theme_slug";

	if ( file_exists( $theme_dir ) && is_link( $theme_dir ) ) {
		unlink( $theme_dir );

		return true;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions
	error_log(
		"WPComSH: Can't delete the specified symlinked theme: the path or symlink doesn't exist."
	);

	return new WP_Error(
		'error_deleting_symlinked_theme',
		"Can't delete the specified symlinked theme: the path or symlink doesn't exist."
	);
}

/**
 * Returns a theme type.
 *
 * @param string $theme_slug The slug of a theme.
 * @return false|string Theme type or false if not a wpcom theme.
 */
function wpcomsh_get_wpcom_theme_type( $theme_slug ) {
	if ( wpcomsh_is_wpcom_premium_theme( $theme_slug ) ) {
		return WPCOMSH_PREMIUM_THEME_TYPE;
	} elseif ( wpcomsh_is_wpcom_pub_theme( $theme_slug ) ) {
		return WPCOMSH_PUB_THEME_TYPE;
	}

	return false;
}

/**
 * Returns whether the theme is a child theme.
 *
 * @param string $theme_slug Slug of the theme to check. Default: Active theme.
 * @return bool
 */
function wpcomsh_is_wpcom_child_theme( $theme_slug = null ) {
	$theme = wp_get_theme( $theme_slug );

	return $theme->get_stylesheet() !== $theme->get_template();
}

/**
 * Answers whether other themes have the same parent as the reference theme
 *
 * @param string $theme_slug Slug of the reference theme
 * @return bool
 */
function wpcomsh_do_other_themes_have_same_parent( $theme_slug ) {
	$reference_theme = wp_get_theme( $theme_slug );
	if ( ! $reference_theme->exists() ) {
		return false;
	}

	if ( ! wpcomsh_is_wpcom_child_theme( $theme_slug ) ) {
		return false;
	}

	foreach ( wp_get_themes() as $theme ) {
		if (
			$theme->get_stylesheet() !== $reference_theme->get_stylesheet() &&
			$theme->get_stylesheet() !== $reference_theme->get_template() &&
			$theme->get_template() === $reference_theme->get_template()
		) {
			return true;
		}
	}

	return false;
}


/**
 * Symlinks the theme's parent if it's a child theme.
 *
 * @param string $stylesheet Theme slug.
 * @return bool|WP_Error
 */
function wpcomsh_symlink_parent_theme( $stylesheet ) {
	$theme    = wp_get_theme( $stylesheet );
	$template = $theme->get_template();

	if ( $template === $stylesheet ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( "WPComSH: Can't symlink parent theme. Current theme is not a child theme." );

		return false;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions
	error_log( 'WPComSH: Symlinking parent theme.' );

	return wpcomsh_symlink_theme( $template, wpcomsh_get_wpcom_theme_type( $template ) );
}

/**
 * Deletes the symlink to the parent theme.
 *
 * @param string $stylesheet Theme slug.
 * @return bool|WP_Error
 */
function wpcomsh_delete_symlinked_parent_theme( $stylesheet ) {
	$theme    = wp_get_theme( $stylesheet );
	$template = $theme->get_template();

	if ( $template === $stylesheet ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log( "WPComSH: Can't unsymlink parent theme. $stylesheet is not a child theme." );

		return false;
	}

	if ( wpcomsh_do_other_themes_have_same_parent( $stylesheet ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		error_log(
			"WPComSH: Can't unsymlink parent theme $template. There are other installed child themes that depend on it."
		);
		return false;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions
	error_log( 'WPComSH: Unsymlinking parent theme.' );

	return wpcomsh_delete_symlinked_theme( $template );
}

/**
 * Returns the Atomic site ID.
 *
 * @return int
 */
function wpcomsh_get_atomic_site_id() {
	if ( defined( 'ATOMIC_SITE_ID' ) ) {
		return (int) ATOMIC_SITE_ID;
	}

	$atomic_site_id = apply_filters( 'wpcomsh_get_atomic_site_id', 0 );
	if ( ! empty( $atomic_site_id ) ) {
		return (int) $atomic_site_id;
	}

	return 0;
}

/**
 * Returns the Atomic client ID.
 *
 * @return int
 */
function wpcomsh_get_atomic_client_id() {
	if ( defined( 'ATOMIC_CLIENT_ID' ) ) {
		return (int) ATOMIC_CLIENT_ID;
	}

	$atomic_client_id = apply_filters( 'wpcomsh_get_atomic_client_id', 0 );
	if ( ! empty( $atomic_client_id ) ) {
		return (int) $atomic_client_id;
	}

	return 0;
}

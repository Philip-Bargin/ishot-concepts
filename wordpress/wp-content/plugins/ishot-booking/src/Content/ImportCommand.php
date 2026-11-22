<?php
namespace IShot\Booking\Content;

use WP_CLI;

/**
 * Imports the React prototype's content and photos (see Content\Importer).
 */
final class ImportCommand {

	/**
	 * Imports prototype services, projects, categories and photos. Safe to re-run.
	 *
	 * Items edited in WordPress since they were imported are kept unless --force is given.
	 * New posts are drafts marked "Placeholder"; prices, deposits and testimonials are never imported.
	 * Creates the published "Primary" navigation (the header menu) from the prototype's menu when there
	 * is none; an existing one is never changed, even with --force.
	 *
	 * ## OPTIONS
	 *
	 * --seed=<file>
	 * : Path to prototype-content.json (written by `npm run seed:export`).
	 *
	 * --images=<dir>
	 * : Folder holding the image files the seed names.
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--force]
	 * : Overwrite items that were edited in WordPress after import.
	 *
	 * [--publish]
	 * : Publish newly created posts (dev previews only). Existing posts keep their status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ishot import-prototype --seed=wp-content/ishot-seed/prototype-content.json --images=wp-content/ishot-prototype-images --dry-run
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc
	 */
	public function __invoke( array $args, array $assoc ): void {
		$seed_file = (string) $assoc['seed'];
		$images    = rtrim( (string) $assoc['images'], '/' );
		if ( ! is_readable( $seed_file ) ) {
			WP_CLI::error( "Seed file not found: {$seed_file}" );
		}
		if ( ! is_dir( $images ) ) {
			WP_CLI::error( "Image folder not found: {$images}" );
		}
		$seed = json_decode( (string) file_get_contents( $seed_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( ! is_array( $seed ) ) {
			WP_CLI::error( 'The seed file is not valid JSON.' );
		}

		$report = ( new Importer( $images ) )->run(
			$seed,
			array(
				'dry_run' => (bool) WP_CLI\Utils\get_flag_value( $assoc, 'dry-run', false ),
				'force'   => (bool) WP_CLI\Utils\get_flag_value( $assoc, 'force', false ),
				'publish' => (bool) WP_CLI\Utils\get_flag_value( $assoc, 'publish', false ),
			)
		);

		foreach ( $report['messages'] as $message ) {
			WP_CLI::log( $message );
		}
		$summary = sprintf(
			'%s%d created, %d updated, %d unchanged, %d kept because edited in WordPress, %d warnings, %d errors.',
			WP_CLI\Utils\get_flag_value( $assoc, 'dry-run', false ) ? 'Dry run: ' : '',
			$report['created'],
			$report['updated'],
			$report['unchanged'],
			$report['skipped_edited'],
			$report['warnings'],
			$report['errors']
		);
		$report['errors'] > 0 ? WP_CLI::error( $summary ) : WP_CLI::success( $summary );
	}
}

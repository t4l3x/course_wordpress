<?php
/**
 * Create the Course Discovery distribution ZIP from a prepared directory.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$source_argument      = $argv[1] ?? '';
$destination_argument = $argv[2] ?? '';
$source_directory     = realpath( $source_argument );

if ( false === $source_directory || ! is_dir( $source_directory ) ) {
	fwrite( STDERR, sprintf( "Distribution source directory is invalid: %s\n", $source_argument ) );
	exit( 1 );
}

if ( '' === $destination_argument || ! is_dir( dirname( $destination_argument ) ) ) {
	fwrite( STDERR, sprintf( "Distribution destination is invalid: %s\n", $destination_argument ) );
	exit( 1 );
}

$archive = new ZipArchive();
$opened  = $archive->open( $destination_argument, ZipArchive::CREATE | ZipArchive::OVERWRITE );

if ( true !== $opened ) {
	fwrite( STDERR, sprintf( "Unable to create distribution archive: %s\n", $destination_argument ) );
	exit( 1 );
}

$source_prefix = strlen( $source_directory ) + 1;
$files         = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $source_directory, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ( $files as $file ) {
	if ( $file->isLink() ) {
		$archive->close();
		fwrite( STDERR, sprintf( "Symbolic links are not supported in the distribution: %s\n", $file->getPathname() ) );
		exit( 1 );
	}
	if ( ! $file->isFile() ) {
		continue;
	}

	$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), $source_prefix ) );
	$archive_path  = 'course-discovery/' . $relative_path;
	$added         = $archive->addFile( $file->getPathname(), $archive_path );

	if ( ! $added ) {
		$archive->close();
		fwrite( STDERR, sprintf( "Unable to add path to distribution archive: %s\n", $file->getPathname() ) );
		exit( 1 );
	}
}

if ( ! $archive->close() ) {
	fwrite( STDERR, sprintf( "Unable to finalize distribution archive: %s\n", $destination_argument ) );
	exit( 1 );
}

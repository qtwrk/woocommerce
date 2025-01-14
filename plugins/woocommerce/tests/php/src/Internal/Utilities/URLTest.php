<?php

namespace Automattic\WooCommerce\Tests\Internal\Utilities;

use Automattic\WooCommerce\Internal\Utilities\URL;
use WC_Unit_Test_Case;

/**
 * A collection of tests for the filepath utility class.
 */
class URLTest extends WC_Unit_Test_Case {
	public function test_if_absolute_or_relative() {
		$this->assertTrue(
			( new URL( '/etc/foo/bar' ) )->is_absolute() ,
			'Correctly determines if a Unix-style path is absolute.'
		);

		$this->assertTrue(
			( new URL( 'c:\\Windows\Programs\Item' ) )->is_absolute(),
			'Correctly determines if a Windows-style path is absolute.'
		);

		$this->assertTrue(
			( new URL( 'wp-content/uploads/thing.pdf' ) )->is_relative(),
			'Correctly determines if a filepath is relative.'
		);
	}

	public function test_directory_traversal_resolution() {
		$this->assertEquals(
			'/var/foo/foobar',
			( new URL( '/var/foo/bar/baz/../../foobar' ) )->get_path(),
			'Correctly resolves a path containing a directory traversal.'
		);

		$this->assertEquals(
			'/bazbar',
			( new URL( '/var/foo/../../../../bazbar' ) )->get_path(),
			'Correctly resolves a path containing a directory traversal, even if the traversals attempt to backtrack beyond the root directory.'
		);

		$this->assertEquals(
			'../should/remain/relative',
			( new URL( 'relative/../../should/remain/relative' ) )->get_path(),
			'Simplifies a relative path containing directory traversals to the extent possible (without inspecting the filesystem).'
		);
	}

	public function test_can_get_normalized_string_representation() {
		$this->assertEquals(
			'foo/bar/baz',
			( new URL( 'foo/bar//baz' ) )->get_path(),
			'Empty segments are discarded, remains as a relative path.'
		);

		$this->assertEquals(
			'/foo/  /bar/   /baz/foobarbaz',
			( new URL( '///foo/  /bar/   /baz//foobarbaz' ) )->get_path(),
			'Empty segments are discarded, non-empty segments containing only whitespace are preserved, remains as an absolute path.'
		);

		$this->assertEquals(
			'c:/Windows/Server/HTTP/dump.xml',
			( new URL( 'c:\\Windows\Server\HTTP\dump.xml' ) )->get_path(),
			'String representations of Windows filepaths have forward slash separators and preserve the drive letter.'
		);
	}

	public function test_can_get_normalized_url_representation() {
		$this->assertEquals(
			'file://relative/path',
			( new URL( 'relative/path' ) )->get_url(),
			'Can obtain a URL representation of a relative filepath, even when the initial string was a plain filepath.'
		);

		$this->assertEquals(
			'file:///absolute/path',
			( new URL( '/absolute/path' ) )->get_url(),
			'Can obtain a URL representation of an absolute filepath, even when the initial string was a plain filepath.'
		);

		$this->assertEquals(
			'file:///etc/foo/bar',
			( new URL( 'file:///etc/foo/bar' ) )->get_url(),
			'Can obtain a URL representation of a filepath, when the source filepath was also expressed as a URL.'
		);
	}

	public function test_handling_of_percent_encoded_periods() {
		$this->assertEquals(
			'https://foo.bar/asset.txt',
			( new URL( 'https://foo.bar/parent/.%2e/asset.txt' ) )->get_url(),
			'Directory traversals expressed using percent-encoding are still resolved (lowercase, one encoded period).'
		);

		$this->assertEquals(
			'https://foo.bar/asset.txt',
			( new URL( 'https://foo.bar/parent/%2E./asset.txt' ) )->get_url(),
			'Directory traversals expressed using percent-encoding are still resolved (uppercase, one encoded period).'
		);

		$this->assertEquals(
			'https://foo.bar/asset.txt',
			( new URL( 'https://foo.bar/parent/%2E%2e/asset.txt' ) )->get_url(),
			'Directory traversals expressed using percent-encoding are still resolved (mixed case, both periods encoded).'
		);

		$this->assertEquals(
			'https://foo.bar/parent/%2E.%2fasset.txt',
			( new URL( 'https://foo.bar/parent/%2E.%2fasset.txt' ) )->get_url(),
			'If the forward slash after a double period is URL encoded, there is no directory traversal (since this means the slash is a part of the segment and is not a separator).'
		);

		$this->assertEquals(
			'file:///var/www/network/%2econfig',
			( new URL( '/var/www/network/%2econfig' ) )->get_url(),
			'Use of percent-encoding in URLs is accepted and unnecessary conversion does not take place.'
		);
	}

	public function test_can_obtain_parent_url() {
		$this->assertEquals(
			'file:///',
			( new URL( '/' ) )->get_parent_url(),
			'The parent of root directory "/" is "/".'
		);

		$this->assertEquals(
			'file:///var/',
			( new URL( '/var/dev/' ) )->get_parent_url(),
			'The parent URL will be trailingslashed.'
		);

		$this->assertEquals(
			'https://example.com/',
			( new URL( 'https://example.com' ) )->get_parent_url(),
			'The host name (for non-file URLs) is distinct from the path and will not be removed.'
		);
	}

	public function test_can_obtain_all_parent_urls() {
		$this->assertEquals(
			array(
				'https://local.web/wp-content/uploads/woocommerce_uploads/pdf_bucket/',
				'https://local.web/wp-content/uploads/woocommerce_uploads/',
				'https://local.web/wp-content/uploads/',
				'https://local.web/wp-content/',
				'https://local.web/',
			),
			( new URL( 'https://local.web/wp-content/uploads/woocommerce_uploads/pdf_bucket/secret-sauce.pdf' ) )->get_all_parent_urls(),
			'All parent URLs can be derived, but the host name is never stripped.'
		);

		$this->assertEquals(
			array(
				'file:///srv/websites/my.wp.site/public/',
				'file:///srv/websites/my.wp.site/',
				'file:///srv/websites/',
				'file:///srv/',
				'file:///',
			),
			( new URL( '/srv/websites/my.wp.site/public/test-file.doc' ) )->get_all_parent_urls(),
			'All parent URLs can be derived for a filepath, up to and including the root directory.'
		);

		$this->assertEquals(
			array(
				'file://C:/Documents/Web/TestSite/',
				'file://C:/Documents/Web/',
				'file://C:/Documents/',
				'file://C:/',
			),
			( new URL( 'C:\\Documents\\Web\\TestSite\\BackgroundTrack.mp3' ) )->get_all_parent_urls(),
			'All parent URLs can be derived for a filepath, up to and including the root directory plus drive letter (Windows).'
		);
	}
}

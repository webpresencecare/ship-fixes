<?php
/**
 * Fix: the Table of Contents block (`core/table-of-contents`) on sites without the Gutenberg plugin.
 *
 * WordPress ships the block's code but does not register it while it is experimental, so content written with the
 * Gutenberg plugin shows no table of contents once that plugin is gone. This registers the block under its own name,
 * so that content keeps working, and only when nothing else registered it: the Gutenberg plugin or, later, WordPress
 * itself win.
 *
 * The block (src/blocks/table-of-contents) and the rendering below follow Gutenberg 24.4.1
 * (packages/block-library/src/table-of-contents, GPL-2.0-or-later), as methods of this class instead of the
 * `block_core_table_of_contents_*` functions, which belong to WordPress.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

use WP_Block;
use WP_Block_Processor;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the Table of Contents block.
 *
 * @phpstan-type Heading array{content: string, level: int, link: string}
 * @phpstan-type Node array{heading: Heading, children: array<int, mixed>|null}
 * @phpstan-type Context array{current_page: int, is_paginated: bool, only_include_current_page: bool, permalink: string, target_page: int}
 */
final class Table_Of_Contents {

	/** The block's name: WordPress's own, so existing content keeps working. */
	public const BLOCK = 'core/table-of-contents';

	/** The Heading block's level when none is saved. */
	private const DEFAULT_LEVEL = 2;

	/**
	 * Hooks: after WordPress (init, 10) and the Gutenberg plugin (init, 20) registered their blocks, and before
	 * WordPress adds the block supports' attributes to every registered block (init, 22).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 21 );
	}

	/**
	 * Registers the block, unless it is there already.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			return;
		}
		$path = SHIP_FIXES_DIR . '/build/blocks';
		wp_register_block_metadata_collection( $path, $path . '/blocks-manifest.php' );
		$block = register_block_type_from_metadata( $path . '/table-of-contents', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		// Core gives the editor scripts the text domain; the translations path is the plugin's own folder.
		foreach ( false === $block ? array() : $block->editor_script_handles as $handle ) {
			wp_set_script_translations( $handle, 'ship-fixes', SHIP_FIXES_DIR . '/languages' );
		}
	}

	/**
	 * Renders the block from the headings of the post it is in.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved content (older versions of the block saved their list).
	 * @param WP_Block|null        $block      Block instance.
	 * @return string
	 */
	public static function render( array $attributes, string $content, ?WP_Block $block = null ): string {
		global $wp_current_filter;

		// Posts saved by older versions of the block keep their saved list until they are edited again.
		$legacy = $content;
		if ( '' === trim( $legacy ) && null !== $block && ! empty( $block->parsed_block['innerHTML'] ) ) {
			$legacy = (string) $block->parsed_block['innerHTML'];
		}
		if ( '' !== trim( $legacy ) ) {
			return self::add_aria_label( $attributes, $legacy );
		}

		if ( null === $block || empty( $block->context['postId'] ) ) {
			return '';
		}

		// Outside post content, for example in a template, `postId` falls back to the global post: the post being
		// viewed on singular views only (on archives it would be the first post of the list).
		$in_post_content = is_array( $wp_current_filter ) && in_array( 'the_content', $wp_current_filter, true );
		if ( ! $in_post_content && ! is_singular() ) {
			return '';
		}

		// On Preview, WordPress applies the unsaved changes to the global post only, so read it when it is the post
		// in context: loading the post by ID would read the saved version.
		$post = get_post();
		if ( ! $post || (int) $block->context['postId'] !== $post->ID ) {
			$post = get_post( (int) $block->context['postId'] );
		}
		if ( ! $post || post_password_required( $post ) ) {
			return '';
		}

		$max_level = isset( $attributes['maxLevel'] ) ? (int) $attributes['maxLevel'] : 0;
		$context   = self::context(
			$post->post_content,
			array(
				'only_include_current_page' => ! empty( $attributes['onlyIncludeCurrentPage'] ),
				'permalink'                 => (string) get_permalink( $post ),
				'target_page'               => self::current_page(),
			)
		);
		$headings  = self::headings( $post->post_content, $max_level, $context );
		if ( array() === $headings ) {
			return '';
		}

		$ordered  = ! array_key_exists( 'ordered', $attributes ) || (bool) $attributes['ordered'];
		$list_tag = $ordered ? 'ol' : 'ul';
		$html     = sprintf(
			'<nav %1$s><%2$s>%3$s</%2$s></nav>',
			get_block_wrapper_attributes(),
			$list_tag,
			self::list_items( self::nest( $headings ), $list_tag )
		);

		return self::add_aria_label( $attributes, $html );
	}

	/**
	 * Adds an aria-label to the block's `nav`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block HTML.
	 * @return string
	 */
	private static function add_aria_label( array $attributes, string $content ): string {
		$label     = empty( $attributes['ariaLabel'] ) || ! is_string( $attributes['ariaLabel'] ) ? __( 'Table of Contents', 'ship-fixes' ) : wp_strip_all_tags( $attributes['ariaLabel'] );
		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( array( 'tag_name' => 'nav' ) ) ) {
			$processor->set_attribute( 'aria-label', $label );
		}
		return $processor->get_updated_html();
	}

	/**
	 * The link to a heading: a fragment, or on paginated posts the address of the heading's page with the fragment.
	 * Headings without an ID cannot be linked.
	 *
	 * @param string $id      Heading ID.
	 * @param array  $context Context.
	 * @phpstan-param Context $context
	 * @return string
	 */
	private static function heading_link( string $id, array $context ): string {
		if ( '' === $id ) {
			return '';
		}
		if ( ! $context['is_paginated'] || '' === $context['permalink'] ) {
			return '#' . $id;
		}
		$permalink = remove_query_arg( 'page', $context['permalink'] );
		if ( 1 < $context['current_page'] ) {
			$permalink = add_query_arg( 'page', $context['current_page'], $permalink );
		}
		return $permalink . '#' . $id;
	}

	/**
	 * Wraps bare page breaks as `core/nextpage` blocks, so the block processor sees them: classic content stores
	 * `<!--nextpage-->` alone, and the Page Break block saves that marker as its content.
	 *
	 * @param string $content Serialized blocks.
	 * @return string
	 */
	private static function wrap_page_breaks( string $content ): string {
		// Page breaks that are blocks already become bare ones first, so none is wrapped twice.
		$content = (string) preg_replace(
			'/<!--\s+wp:(?:core\/)?nextpage\s+-->\s*<!--nextpage-->\s*<!--\s+\/wp:(?:core\/)?nextpage\s+-->/',
			'<!--nextpage-->',
			$content
		);
		return str_replace( '<!--nextpage-->', '<!-- wp:nextpage --><!--nextpage--><!-- /wp:nextpage -->', $content );
	}

	/**
	 * A heading block's text, level and link, or null when it is skipped (too deep, or empty).
	 *
	 * @param array<mixed> $block     Parsed heading block.
	 * @param int          $max_level Deepest level to include (0: all).
	 * @param array        $context   Context.
	 * @phpstan-param Context $context
	 * @phpstan-return Heading|null
	 * @return array<string, mixed>|null
	 */
	private static function heading( array $block, int $max_level, array $context ): ?array {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : self::DEFAULT_LEVEL;
		if ( $max_level && $level > $max_level ) {
			return null;
		}

		$rendered  = render_block( $block );
		$processor = new WP_HTML_Tag_Processor( $rendered );
		$id        = '';
		while ( $processor->next_tag() ) {
			if ( in_array( $processor->get_tag(), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) {
				$id = $processor->get_attribute( 'id' );
				break;
			}
		}

		// Line breaks become spaces; entities are decoded once here and escaped once on output.
		$text = html_entity_decode(
			trim( wp_strip_all_tags( (string) preg_replace( '/<br\s*\/?>/i', ' ', $rendered ) ) ),
			ENT_QUOTES,
			(string) get_option( 'blog_charset' )
		);
		if ( '' === $text ) {
			return null;
		}

		return array(
			'content' => $text,
			'level'   => $level,
			'link'    => self::heading_link( is_string( $id ) ? $id : '', $context ),
		);
	}

	/**
	 * The state kept while walking the post's content: whether it is paginated, the page being read, the page the
	 * block shows, and the address used for links.
	 *
	 * @param string               $content Serialized blocks.
	 * @param array<string, mixed> $context Known values.
	 * @phpstan-return Context
	 * @return array<string, mixed>
	 */
	private static function context( string $content, array $context ): array {
		return array(
			'current_page'              => 1,
			'is_paginated'              => str_contains( $content, '<!--nextpage-->' ),
			'only_include_current_page' => ! empty( $context['only_include_current_page'] ),
			'permalink'                 => isset( $context['permalink'] ) && is_string( $context['permalink'] ) ? $context['permalink'] : '',
			'target_page'               => max( 1, (int) ( $context['target_page'] ?? 1 ) ),
		);
	}

	/**
	 * The headings of a post's content, in order.
	 *
	 * @param string $content   Serialized blocks.
	 * @param int    $max_level Deepest level to include (0: all).
	 * @param array  $context   Context.
	 * @phpstan-param Context $context
	 * @phpstan-return array<int, Heading>
	 * @return array<int, array<string, mixed>>
	 */
	private static function headings( string $content, int $max_level, array $context ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}
		$headings  = array();
		$processor = new WP_Block_Processor( self::wrap_page_breaks( $content ) );
		while ( $processor->next_block() ) {
			$type = $processor->get_block_type();
			if ( 'core/nextpage' === $type ) {
				++$context['current_page'];
				continue;
			}
			$on_page = ! $context['only_include_current_page'] || $context['current_page'] === $context['target_page'];
			if ( 'core/heading' !== $type || ! $on_page ) {
				continue;
			}
			$block   = $processor->extract_full_block_and_advance();
			$heading = is_array( $block ) ? self::heading( $block, $max_level, $context ) : null;
			if ( null !== $heading ) {
				$headings[] = $heading;
			}
		}
		return $headings;
	}

	/**
	 * The page of a paginated post being shown.
	 *
	 * @return int
	 */
	private static function current_page(): int {
		global $page;
		$current = (int) get_query_var( 'page' );
		if ( ! $current && isset( $page ) ) {
			$current = (int) $page;
		}
		return max( 1, $current );
	}

	/**
	 * Nests a flat list of headings by level.
	 *
	 * @param array $headings Headings.
	 * @phpstan-param array<int, Heading> $headings
	 * @phpstan-return array<int, Node>
	 * @return array<int, array<string, mixed>>
	 */
	private static function nest( array $headings ): array {
		$nested = array();
		$count  = count( $headings );
		foreach ( $headings as $index => $heading ) {
			if ( $heading['level'] !== $headings[0]['level'] ) {
				continue;
			}
			// When a deeper heading follows, the headings up to the next one of this level are its children.
			$children = array();
			$deeper   = isset( $headings[ $index + 1 ] ) && $headings[ $index + 1 ]['level'] > $heading['level'];
			for ( $i = $index + 1; $deeper && $i < $count && $headings[ $i ]['level'] !== $heading['level']; $i++ ) {
				$children[] = $headings[ $i ];
			}
			$nested[] = array(
				'heading'  => $heading,
				'children' => $deeper ? self::nest( $children ) : null,
			);
		}
		return $nested;
	}

	/**
	 * The list items of nested headings.
	 *
	 * @param array  $nested   Nested headings.
	 * @param string $list_tag ol or ul.
	 * @phpstan-param array<int, mixed> $nested
	 * @return string
	 */
	private static function list_items( array $nested, string $list_tag ): string {
		$list = '';
		foreach ( $nested as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['heading'] ) || ! is_array( $node['heading'] ) ) {
				continue;
			}
			$text  = esc_html( (string) $node['heading']['content'] );
			$link  = (string) $node['heading']['link'];
			$entry = '' !== $link
				? sprintf( '<a class="wp-block-table-of-contents__entry" href="%1$s">%2$s</a>', esc_url( $link ), $text )
				: sprintf( '<span class="wp-block-table-of-contents__entry">%s</span>', $text );
			$inner = ! empty( $node['children'] ) && is_array( $node['children'] )
				? sprintf( '<%1$s>%2$s</%1$s>', $list_tag, self::list_items( $node['children'], $list_tag ) )
				: '';
			$list .= '<li>' . $entry . $inner . '</li>';
		}
		return $list;
	}
}

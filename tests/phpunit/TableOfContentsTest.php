<?php
/**
 * The Table of Contents fix: registered only when nothing else did, and rendered from the post's headings.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Table_Of_Contents;

/**
 * @covers \Ship\Fixes\Table_Of_Contents
 */
class TableOfContentsTest extends Ship_Fixes_TestCase {

	private const HEADINGS = '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading" id="one">One &amp; <em>first</em></h2><!-- /wp:heading -->'
		. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading" id="inner">Inner<br>part</h3><!-- /wp:heading -->'
		. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"></h3><!-- /wp:heading -->'
		. '<!-- wp:heading --><h2 class="wp-block-heading">No anchor</h2><!-- /wp:heading -->';

	/**
	 * Renders the block inside a post, as the front end does.
	 *
	 * @param string $content Post content before the block.
	 * @param string $block   The block's markup.
	 * @return string The block's HTML.
	 */
	private function render_in_post( string $content, string $block = '<!-- wp:table-of-contents /-->' ): string {
		$post_id = self::factory()->post->create( array( 'post_content' => $block . $content ) );
		$this->go_to( (string) get_permalink( $post_id ) );
		setup_postdata( get_post( $post_id ) );
		$blocks = parse_blocks( $block );
		return ( new WP_Block( $blocks[0], array( 'postId' => $post_id ) ) )->render();
	}

	public function test_registered_under_cores_name_with_its_render_callback(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( Table_Of_Contents::BLOCK );
		$this->assertInstanceOf( WP_Block_Type::class, $type );
		$this->assertSame( array( Table_Of_Contents::class, 'render' ), $type->render_callback );
		$this->assertNotEmpty( $type->editor_script_handles );
	}

	public function test_registers_after_the_gutenberg_plugin_and_before_core_adds_block_supports(): void {
		// Gutenberg registers this block on init at 20; core's WP_Block_Supports::init() runs at 22.
		$this->assertSame( 21, has_action( 'init', array( Table_Of_Contents::class, 'register' ) ) );
	}

	public function test_steps_aside_when_the_block_is_registered_already(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$registry->unregister( Table_Of_Contents::BLOCK );
		$registry->register( Table_Of_Contents::BLOCK, array( 'render_callback' => '__return_empty_string' ) );

		Table_Of_Contents::register();
		$type = $registry->get_registered( Table_Of_Contents::BLOCK );
		$this->assertInstanceOf( WP_Block_Type::class, $type );
		$this->assertSame( '__return_empty_string', $type->render_callback );

		$registry->unregister( Table_Of_Contents::BLOCK );
		Table_Of_Contents::register();
	}

	public function test_lists_the_headings_nested_escaped_and_linked(): void {
		$html = $this->render_in_post( self::HEADINGS );
		$this->assertStringContainsString( '<nav', $html );
		$this->assertStringContainsString( 'aria-label="Table of Contents"', $html );
		$this->assertStringContainsString( 'class="wp-block-table-of-contents"', $html );
		$this->assertStringContainsString(
			'<ol><li><a class="wp-block-table-of-contents__entry" href="#one">One &amp; first</a>'
			. '<ol><li><a class="wp-block-table-of-contents__entry" href="#inner">Inner part</a></li></ol></li>'
			. '<li><span class="wp-block-table-of-contents__entry">No anchor</span></li></ol>',
			$html,
			'Children nested, the empty heading left out, a heading without an anchor not linked.'
		);
	}

	public function test_nests_three_levels_and_a_deeper_first_heading(): void {
		$content = '';
		foreach ( array( 3, 4, 2, 3, 4, 3 ) as $index => $level ) {
			$content .= sprintf( '<!-- wp:heading {"level":%1$d} --><h%1$d class="wp-block-heading">H%2$d</h%1$d><!-- /wp:heading -->', $level, $index );
		}
		$entry = '<span class="wp-block-table-of-contents__entry">%s</span>';
		$this->assertStringContainsString(
			'<ol><li>' . sprintf( $entry, 'H0' ) . '<ol><li>' . sprintf( $entry, 'H1' ) . '</li></ol></li>'
			. '<li>' . sprintf( $entry, 'H3' ) . '<ol><li>' . sprintf( $entry, 'H4' ) . '</li></ol></li>'
			. '<li>' . sprintf( $entry, 'H5' ) . '</li></ol>',
			$this->render_in_post( $content ),
			'As in Gutenberg: the first heading sets the top level, and a heading above it (H2 here) is left out.'
		);
	}

	public function test_unordered_and_limited_to_a_level(): void {
		$html = $this->render_in_post( self::HEADINGS, '<!-- wp:table-of-contents {"ordered":false,"maxLevel":2,"ariaLabel":"On this page"} /-->' );
		$this->assertStringContainsString( 'aria-label="On this page"', $html );
		$this->assertStringContainsString( '<ul><li>', $html );
		$this->assertStringNotContainsString( 'Inner', $html );
	}

	public function test_paginated_posts_link_to_the_headings_page(): void {
		$content = self::HEADINGS . '<!-- wp:nextpage --><!--nextpage--><!-- /wp:nextpage -->'
			. '<!-- wp:heading --><h2 class="wp-block-heading" id="two">Two</h2><!-- /wp:heading -->';
		$html    = $this->render_in_post( $content );
		$this->assertMatchesRegularExpression( '/href="[^"]*page=2#two"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/href="[^"]*page=[^"]*#one"/', $html, 'Page 1 has the plain address.' );

		$first = $this->render_in_post( $content, '<!-- wp:table-of-contents {"onlyIncludeCurrentPage":true} /-->' );
		$this->assertStringContainsString( '#one', $first );
		$this->assertStringNotContainsString( '#two', $first );
	}

	public function test_nothing_without_headings_and_for_protected_posts(): void {
		$this->assertSame( '', $this->render_in_post( '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_content'  => self::HEADINGS,
				'post_password' => 'secret',
			)
		);
		$this->go_to( (string) get_permalink( $post_id ) );
		$blocks = parse_blocks( '<!-- wp:table-of-contents /-->' );
		$this->assertSame( '', ( new WP_Block( $blocks[0], array( 'postId' => $post_id ) ) )->render() );
	}

	public function test_keeps_the_list_saved_by_older_versions_of_the_block(): void {
		$saved = '<!-- wp:table-of-contents {"headings":[{"content":"Old","level":2,"link":"#old"}]} -->'
			. '<nav class="wp-block-table-of-contents"><ol><li><a class="wp-block-table-of-contents__entry" href="#old">Old</a></li></ol></nav>'
			. '<!-- /wp:table-of-contents -->';
		$html  = do_blocks( $saved );
		$this->assertStringContainsString( 'href="#old">Old</a>', $html );
		$this->assertStringContainsString( 'aria-label="Table of Contents"', $html );
	}
}

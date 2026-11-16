/**
 * The nested list of headings, as in Gutenberg 24.4.1 (table-of-contents/list.tsx).
 */
const ENTRY_CLASS_NAME = 'wp-block-table-of-contents__entry';

export default function TableOfContentsList( {
	nestedHeadingList,
	disableLinkActivation,
	onClick,
	ordered = true,
} ) {
	return (
		<>
			{ nestedHeadingList.map( ( node, index ) => {
				const { content, link } = node.heading;

				const entry = link ? (
					<a
						className={ ENTRY_CLASS_NAME }
						href={ link }
						aria-disabled={ disableLinkActivation || undefined }
						onClick={
							disableLinkActivation &&
							'function' === typeof onClick
								? onClick
								: undefined
						}
					>
						{ content }
					</a>
				) : (
					<span className={ ENTRY_CLASS_NAME }>{ content }</span>
				);

				const NestedListTag = ordered ? 'ol' : 'ul';

				return (
					<li key={ index }>
						{ entry }
						{ node.children ? (
							<NestedListTag>
								<TableOfContentsList
									nestedHeadingList={ node.children }
									disableLinkActivation={
										disableLinkActivation
									}
									onClick={
										disableLinkActivation &&
										'function' === typeof onClick
											? onClick
											: undefined
									}
									ordered={ ordered }
								/>
							</NestedListTag>
						) : null }
					</li>
				);
			} ) }
		</>
	);
}

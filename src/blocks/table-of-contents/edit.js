/**
 * The Table of Contents block in the editor, as in Gutenberg 24.4.1 (table-of-contents/edit.jsx), with the select
 * of `@wordpress/components` (WordPress 7.2 does not provide `@wordpress/ui` to plugins) and this plugin's text domain.
 */
import {
	BlockControls,
	BlockIcon,
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
// The block's own source uses these three components, which have no stable version in WordPress 7.2.
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	Placeholder,
	SelectControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	__experimentalConfirmDialog as ConfirmDialog,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, isRTL } from '@wordpress/i18n';
import { useInstanceId, useViewportMatch } from '@wordpress/compose';
import { store as noticeStore } from '@wordpress/notices';
import {
	tableOfContents as icon,
	formatListBullets,
	formatListBulletsRTL,
	formatListNumbered,
	formatListNumberedRTL,
} from '@wordpress/icons';
import TableOfContentsList from './list';
import { createListItemBlocks, linearToNestedHeadingList } from './utils';
import { useHeadings } from './hooks';

const HEADING_LEVEL_ITEMS = [
	{ value: 'all', label: __( 'All levels', 'ship-fixes' ) },
	{ value: '1', label: __( 'Heading 1', 'ship-fixes' ) },
	{ value: '2', label: __( 'Heading 2', 'ship-fixes' ) },
	{ value: '3', label: __( 'Heading 3', 'ship-fixes' ) },
	{ value: '4', label: __( 'Heading 4', 'ship-fixes' ) },
	{ value: '5', label: __( 'Heading 5', 'ship-fixes' ) },
	{ value: '6', label: __( 'Heading 6', 'ship-fixes' ) },
];

// The menu of the settings panel opens beside the sidebar, except on small screens (block-library/utils/hooks).
function useToolsPanelDropdownMenuProps() {
	const isMobile = useViewportMatch( 'medium', '<' );
	return ! isMobile
		? { popoverProps: { placement: 'left-start', offset: 259 } }
		: {};
}

function TableOfContentsToolbar( {
	canDetach,
	clientId,
	headingTree,
	ordered,
	setAttributes,
} ) {
	const canInsertList = useSelect(
		( select ) => {
			const { getBlockRootClientId, canInsertBlockType } =
				select( blockEditorStore );
			const rootClientId = getBlockRootClientId( clientId );

			return canInsertBlockType( 'core/list', rootClientId );
		},
		[ clientId ]
	);
	const { replaceBlocks } = useDispatch( blockEditorStore );
	const [ isConfirmingDetach, setIsConfirmingDetach ] = useState( false );

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={
							isRTL() ? formatListBulletsRTL : formatListBullets
						}
						title={ __( 'Unordered', 'ship-fixes' ) }
						description={ __(
							'Convert to unordered list',
							'ship-fixes'
						) }
						onClick={ () => setAttributes( { ordered: false } ) }
						isActive={ ordered === false }
					/>
					<ToolbarButton
						icon={
							isRTL() ? formatListNumberedRTL : formatListNumbered
						}
						title={ __( 'Ordered', 'ship-fixes' ) }
						description={ __(
							'Convert to ordered list',
							'ship-fixes'
						) }
						onClick={ () => setAttributes( { ordered: true } ) }
						isActive={ ordered === true }
					/>
				</ToolbarGroup>
				{ canDetach && canInsertList && (
					<ToolbarGroup>
						<ToolbarButton
							onClick={ () => setIsConfirmingDetach( true ) }
						>
							{ __( 'Detach', 'ship-fixes' ) }
						</ToolbarButton>
					</ToolbarGroup>
				) }
			</BlockControls>
			{ isConfirmingDetach && (
				<ConfirmDialog
					isOpen
					title={ __( 'Detach Table of Contents', 'ship-fixes' ) }
					__experimentalHideHeader={ false }
					confirmButtonText={ __( 'Detach', 'ship-fixes' ) }
					onConfirm={ () => {
						setIsConfirmingDetach( false );
						replaceBlocks(
							clientId,
							createBlock(
								'core/list',
								{ ordered },
								createListItemBlocks( headingTree, ordered )
							)
						);
					} }
					onCancel={ () => setIsConfirmingDetach( false ) }
					size="medium"
				>
					{ __(
						'The Table of Contents block lists the headings in the post. Detaching will enable you to edit, reorder, or remove entries. However, new headings will no longer be added automatically.',
						'ship-fixes'
					) }
				</ConfirmDialog>
			) }
		</>
	);
}

export default function TableOfContentsEdit( {
	attributes: { onlyIncludeCurrentPage, maxLevel, ordered = true },
	clientId,
	context: { postId, postType },
	setAttributes,
} ) {
	const postHeadings = useHeadings( {
		clientId,
		postId,
		postType,
		onlyIncludeCurrentPage,
		maxLevel,
	} );
	// Without a post in context, e.g. in a template or a block preview, show
	// sample entries standing in for the headings of the post being viewed.
	const headings = postId
		? postHeadings
		: [
				{
					content: __(
						'Headings from the post being viewed appear here',
						'ship-fixes'
					),
					level: 2,
				},
				{ content: __( 'Subheading', 'ship-fixes' ), level: 3 },
			];

	const blockProps = useBlockProps();
	const instanceId = useInstanceId(
		TableOfContentsEdit,
		'table-of-contents'
	);

	// If a user clicks to a link prevent redirection and show a warning.
	const { createWarningNotice } = useDispatch( noticeStore );
	const showRedirectionPreventedNotice = ( event ) => {
		event.preventDefault();
		createWarningNotice(
			__( 'Links are disabled in the editor.', 'ship-fixes' ),
			{
				id: `block-library/core/table-of-contents/redirection-prevented/${ instanceId }`,
				type: 'snackbar',
			}
		);
	};

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const headingTree = linearToNestedHeadingList( headings );

	// Without a post, the entries are samples, so there is nothing to detach.
	// The list style still applies, so its buttons stay available.
	const toolbarControls = (
		<TableOfContentsToolbar
			canDetach={ !! postId }
			clientId={ clientId }
			headingTree={ headingTree }
			ordered={ ordered }
			setAttributes={ setAttributes }
		/>
	);

	const inspectorControls = (
		<InspectorControls>
			<ToolsPanel
				label={ __( 'Settings', 'ship-fixes' ) }
				resetAll={ () => {
					setAttributes( {
						onlyIncludeCurrentPage: false,
						maxLevel: undefined,
						ordered: true,
					} );
				} }
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					hasValue={ () => !! onlyIncludeCurrentPage }
					label={ __( 'Only include current page', 'ship-fixes' ) }
					onDeselect={ () =>
						setAttributes( { onlyIncludeCurrentPage: false } )
					}
					isShownByDefault
				>
					<ToggleControl
						label={ __(
							'Only include current page',
							'ship-fixes'
						) }
						checked={ onlyIncludeCurrentPage }
						onChange={ ( value ) =>
							setAttributes( { onlyIncludeCurrentPage: value } )
						}
						help={
							onlyIncludeCurrentPage
								? __(
										'Only including headings from the current page (if the post is paginated).',
										'ship-fixes'
									)
								: __(
										'Include headings from all pages (if the post is paginated).',
										'ship-fixes'
									)
						}
					/>
				</ToolsPanelItem>
				<ToolsPanelItem
					hasValue={ () => !! maxLevel }
					label={ __( 'Limit heading levels', 'ship-fixes' ) }
					onDeselect={ () =>
						setAttributes( { maxLevel: undefined } )
					}
					isShownByDefault
				>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Include headings down to level',
							'ship-fixes'
						) }
						value={ maxLevel ? String( maxLevel ) : 'all' }
						options={ HEADING_LEVEL_ITEMS }
						onChange={ ( value ) =>
							setAttributes( {
								maxLevel:
									value !== 'all'
										? parseInt( value, 10 )
										: undefined,
							} )
						}
						help={
							! maxLevel
								? __(
										'Including all heading levels in the table of contents.',
										'ship-fixes'
									)
								: __(
										'Only include headings up to and including this level.',
										'ship-fixes'
									)
						}
					/>
				</ToolsPanelItem>
			</ToolsPanel>
		</InspectorControls>
	);

	// If there are no headings or the only heading is empty.
	// Note that the toolbar controls are intentionally omitted since the
	// "Detach" option is useless to the placeholder state.
	if ( headings.length === 0 ) {
		return (
			<>
				<div { ...blockProps }>
					<Placeholder
						icon={ <BlockIcon icon={ icon } /> }
						label={ __( 'Table of Contents', 'ship-fixes' ) }
						instructions={ __(
							'Start adding Heading blocks to create a table of contents automatically.',
							'ship-fixes'
						) }
					/>
				</div>
				{ inspectorControls }
			</>
		);
	}

	const ListTag = ordered ? 'ol' : 'ul';

	return (
		<>
			<nav { ...blockProps }>
				<ListTag>
					<TableOfContentsList
						nestedHeadingList={ headingTree }
						disableLinkActivation
						onClick={ showRedirectionPreventedNotice }
						ordered={ ordered }
					/>
				</ListTag>
			</nav>
			{ toolbarControls }
			{ inspectorControls }
		</>
	);
}

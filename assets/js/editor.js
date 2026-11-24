/* global wp, pdblocksSettings */
/**
 * The product description and short description as block editors, on the classic product screen.
 *
 * Each editor keeps its form field (`content`, `excerpt`) in sync as block markup. The product
 * form posts those fields with everything else, so nothing about saving a product changes.
 *
 * No @wordpress/editor and no edit-post: each editor is a BlockEditorProvider over a plain block
 * list, with a fixed toolbar and the inserter as a popover.
 */
( function ( wp, settings ) {
	const el = wp.element.createElement;

	// SVG paths from the Gutenberg @wordpress/icons package bundled in WordPress 7.1.
	const toolbarIcon = ( path, attributes = {} ) => el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': true, focusable: false },
		el( 'path', { d: path, ...attributes } )
	);
	const inserterIcon = toolbarIcon( 'M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z' );
	const leftSidebarIcon = toolbarIcon( 'M18 4H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM8.5 18.5H6c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h2.5v13zm10-.5c0 .3-.2.5-.5.5h-8v-13h8c.3 0 .5.2.5.5v12z', { fillRule: 'evenodd', clipRule: 'evenodd' } );
	const rightSidebarIcon = toolbarIcon( 'M18 4H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-4 14.5H6c-.3 0-.5-.2-.5-.5V6c0-.3.2-.5.5-.5h8v13zm4.5-.5c0 .3-.2.5-.5.5h-2.5v-13H18c.3 0 .5.2.5.5v12z', { fillRule: 'evenodd', clipRule: 'evenodd' } );
	const listViewIcon = toolbarIcon( 'M3 6h11v1.5H3V6Zm3.5 5.5h11V13h-11v-1.5ZM21 17H10v1.5h11V17Z' );
	const undoIcon = toolbarIcon( 'M18.3 11.7c-.6-.6-1.4-.9-2.3-.9H6.7l2.9-3.3-1.1-1-4.5 5L8.5 16l1-1-2.7-2.7H16c.5 0 .9.2 1.3.5 1 1 1 3.4 1 4.5v.3h1.5v-.2c0-1.5 0-4.3-1.5-5.7z' );
	const redoIcon = toolbarIcon( 'M15.6 6.5l-1.1 1 2.9 3.3H8c-.9 0-1.7.3-2.3.9-1.4 1.5-1.4 4.2-1.4 5.6v.2h1.5v-.3c0-1.1 0-3.5 1-4.5.3-.3.7-.5 1.3-.5h9.2L14.5 15l1.1 1.1 4.6-4.6-4.6-5z' );

	const { Component, createRoot, useEffect, useRef, useState } = wp.element;
	const { __ } = wp.i18n;
	const {
		BlockEditorKeyboardShortcuts,
		BlockEditorProvider,
		BlockInspector,
		BlockList,
		BlockToolbar,
		BlockTools,
		Inserter,
		ObserveTyping,
		WritingFlow,
	} = wp.blockEditor;
	const {
		getBlockType,
		isUnmodifiedDefaultBlock,
		parse,
		rawHandler,
		serialize,
		setFreeformContentHandlerName,
	} = wp.blocks;
	const { Button, SlotFillProvider } = wp.components;
	const { useStateWithHistory } = wp.compose;
	const { ShortcutProvider } = wp.keyboardShortcuts;
	const { isKeyboardEvent } = wp.keycodes;
	const { MediaUpload, uploadMedia } = wp.mediaUtils;

	const ListView = wp.blockEditor.ListView || wp.blockEditor.__experimentalListView;
	const fields = document.querySelectorAll( '.pdblocks-field' );

	if ( ! fields.length ) {
		return;
	}

	if ( ! getBlockType( 'core/paragraph' ) ) {
		wp.blockLibrary.registerCoreBlocks();
	}

	// The Classic block edits through TinyMCE, which is not loaded for these fields. Loose HTML
	// between blocks stays editable as Custom HTML instead.
	setFreeformContentHandlerName( 'core/html' );

	// The Media Library half of an image placeholder renders nothing until this filter supplies it.
	if ( wp.media ) {
		wp.hooks.addFilter(
			'editor.MediaUpload',
			'pdblocks/media-upload',
			() => MediaUpload
		);
	}

	const toBlocks = ( content ) => {
		if ( ! content.trim() ) {
			return [];
		}

		// Text written in the classic editor has no block markup. Convert it the way the Classic
		// block's "Convert to blocks" does.
		if ( ! content.includes( '<!-- wp:' ) ) {
			return rawHandler( { HTML: wp.autop.autop( content ) } );
		}

		return parse( content );
	};

	// A lone empty paragraph is empty content, as it is in the post editor.
	const toContent = ( blocks ) =>
		1 === blocks.length && isUnmodifiedDefaultBlock( blocks[ 0 ] )
			? ''
			: serialize( blocks );

	// Keep distinct block instances, including moves of blocks with identical markup.
	const sameBlockTree = ( previous, next ) =>
		previous.length === next.length && previous.every( ( block, index ) =>
			block === next[ index ] || (
				block.clientId === next[ index ].clientId &&
				sameBlockTree( block.innerBlocks, next[ index ].innerBlocks )
			)
		);

	const FieldEditor = ( { field, initialBlocks, editorSettings } ) => {
		const history = useStateWithHistory( initialBlocks );
		const [ panel, setPanel ] = useState( null );
		const [ stored ] = useState( () => ( {
			content: field.value,
			asBlocks: toContent( initialBlocks ),
		} ) );
		const blocks = history.value;
		const latest = useRef( blocks );
		latest.current = blocks;

		const recordBlocks = ( next, staged ) => {
			const previous = latest.current;
			// Initializing a newly inserted block can send a second, equivalent commit.
			if ( ! staged && sameBlockTree( previous, next ) && serialize( previous ) === serialize( next ) ) {
				return;
			}
			latest.current = next;
			history.setValue( next, staged );
		};

		useEffect( () => {
			const content = toContent( blocks );
			// Content nobody changed is posted back as it was stored, so opening and saving a
			// product never rewrites it into blocks.
			const value = content === stored.asBlocks ? stored.content : content;

			if ( value !== field.value ) {
				field.value = value;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		}, [ blocks ] );

		// "Restore the backup" hands over text WordPress kept in the browser. See below.
		useEffect( () => {
			const onRestore = ( event ) =>
				history.setValue( toBlocks( event.detail ), false );

			field.addEventListener( 'pdblocks-restore', onRestore );

			return () => field.removeEventListener( 'pdblocks-restore', onRestore );
		}, [] );

		const onKeyDown = ( event ) => {
			if ( isKeyboardEvent.primary( event, 'z' ) ) {
				event.preventDefault();
				history.undo();
			} else if (
				isKeyboardEvent.primaryShift( event, 'z' ) ||
				isKeyboardEvent.primary( event, 'y' )
			) {
				event.preventDefault();
				history.redo();
			}
		};

		return el(
			SlotFillProvider,
			null,
			el(
				ShortcutProvider,
				null,
				el(
					BlockEditorProvider,
					{
						value: blocks,
						onInput: ( nextBlocks ) =>
							recordBlocks( nextBlocks, true ),
						onChange: ( nextBlocks ) =>
							recordBlocks( nextBlocks, false ),
						settings: editorSettings,
					},
					el( BlockEditorKeyboardShortcuts.Register ),
					el(
						'div',
						{ className: 'pdblocks__frame', onKeyDown },
						el(
							'div',
							{
								className: 'pdblocks__toolbar',
								role: 'toolbar',
								'aria-label': __(
									'Editor tools',
									'alkamy-product-descriptions-for-woocommerce'
								),
							},
							el( Inserter, {
								position: 'bottom right',
								renderToggle: ( { onToggle, isOpen, disabled } ) =>
									el( Button, {
										className: 'pdblocks__inserter-toggle',
										variant: 'primary',
										size: 'compact',
										icon: inserterIcon,
										label: __(
											'Add block',
											'alkamy-product-descriptions-for-woocommerce'
										),
										'aria-expanded': isOpen,
										onClick: onToggle,
										disabled,
									} ),
							} ),
							el( Button, {
								size: 'compact',
								icon: wp.i18n.isRTL() ? redoIcon : undoIcon,
								label: __( 'Undo', 'alkamy-product-descriptions-for-woocommerce' ),
								onClick: history.undo,
								disabled: ! history.hasUndo,
								accessibleWhenDisabled: true,
							} ),
							el( Button, {
								size: 'compact',
								icon: wp.i18n.isRTL() ? undoIcon : redoIcon,
								label: __( 'Redo', 'alkamy-product-descriptions-for-woocommerce' ),
								onClick: history.redo,
								disabled: ! history.hasRedo,
								accessibleWhenDisabled: true,
							} ),
							ListView && el( Button, {
								icon: listViewIcon,
								label: __( 'List View', 'alkamy-product-descriptions-for-woocommerce' ),
								isPressed: panel === 'list',
								'aria-expanded': panel === 'list',
								onClick: () => setPanel( panel === 'list' ? null : 'list' ),
							} ),
							el(
								'div',
								{ className: 'pdblocks__block-toolbar' },
								el( BlockToolbar, { hideDragHandle: true } )
							),
							el( Button, {
								className: 'pdblocks__settings-toggle',
								size: 'compact',
								icon: wp.i18n.isRTL() ? leftSidebarIcon : rightSidebarIcon,
								label: __(
									'Block settings',
									'alkamy-product-descriptions-for-woocommerce'
								),
								isPressed: panel === 'settings',
								'aria-expanded': panel === 'settings',
								onClick: () => setPanel( panel === 'settings' ? null : 'settings' ),
							} )
						),
						el(
							'div',
							{ className: 'pdblocks__body' },
						panel === 'list' && el(
							'div',
							{ className: 'pdblocks__outline', role: 'region', 'aria-label': __( 'List View', 'alkamy-product-descriptions-for-woocommerce' ) },
							el( ListView, { isExpanded: true } )
						),
							el(
								'div',
								{ className: 'pdblocks__canvas' },
								el(
									BlockTools,
									null,
									el(
										'div',
										{
											className:
												'editor-styles-wrapper pdblocks__content',
										},
										el(
											WritingFlow,
											null,
											el(
												ObserveTyping,
												null,
												el( BlockList )
											)
										)
									)
								)
							),
							panel === 'settings' &&
								el(
									'div',
									{
										className: 'pdblocks__settings',
										role: 'region',
										'aria-label': __(
											'Block settings',
											'alkamy-product-descriptions-for-woocommerce'
										),
									},
									el( BlockInspector )
								)
						)
					)
				)
			)
		);
	};

	// If an editor fails, hand its content back as an editable field, so it can still be saved.
	class Boundary extends Component {
		constructor( props ) {
			super( props );
			this.state = { failed: false };
		}

		static getDerivedStateFromError() {
			return { failed: true };
		}

		componentDidMount() {
			if ( ! this.state.failed ) {
				this.props.wrapper.classList.add( 'is-ready' );
			}
		}

		componentDidCatch() {
			this.props.wrapper.classList.remove( 'is-ready' );
		}

		render() {
			return this.state.failed ? null : this.props.children;
		}
	}

	// WordPress keeps a browser backup of unsaved text and offers to restore it. Its own restore
	// writes to the classic editor, so the backup is passed to the block editors here. Undo in an
	// editor brings back what was there before, as the notice says.
	document.addEventListener( 'click', ( event ) => {
		if ( ! event.target.closest( '#local-storage-notice .restore-backup' ) ) {
			return;
		}

		const backup = wp.autosave?.local?.getSavedPostData?.();

		if ( ! backup ) {
			return;
		}

		fields.forEach( ( wrapper ) => {
			const field = wrapper.querySelector( '.pdblocks-field__content' );

			if ( field && 'string' === typeof backup[ field.name ] ) {
				field.dispatchEvent(
					new CustomEvent( 'pdblocks-restore', {
						detail: backup[ field.name ],
					} )
				);
			}
		} );

		// WordPress's restore selects the text of the editor it expects, which here is the page.
		window.getSelection()?.removeAllRanges();
	} );

	fields.forEach( ( wrapper ) => {
		const mountNode = wrapper.querySelector( '.pdblocks-field__editor' );
		const field = wrapper.querySelector( '.pdblocks-field__content' );
		const fieldSettings = settings.fields[ wrapper.dataset.pdblocksField ];

		if ( ! mountNode || ! field || ! fieldSettings ) {
			return;
		}

		const editorSettings = {
			...settings.editor,
			hasFixedToolbar: true,
			focusMode: false,
			bodyPlaceholder: fieldSettings.placeholder,
			allowedBlockTypes: fieldSettings.allowedBlockTypes,
		};

		if ( settings.canUpload ) {
			editorSettings.mediaUpload = ( args ) =>
				uploadMedia( {
					...args,
					wpAllowedMimeTypes: settings.editor.allowedMimeTypes,
					maxUploadFileSize: settings.editor.maxUploadFileSize,
				} );
		}

		// The editor sits inside the product form. Its own inputs and forms must not submit the product.
		wrapper.addEventListener( 'keydown', ( event ) => {
			if (
				'Enter' === event.key &&
				'INPUT' === event.target.tagName &&
				event.target.form === field.form
			) {
				event.preventDefault();
			}
		} );
		wrapper.addEventListener( 'submit', ( event ) => {
			event.stopPropagation();
		} );

		// Parsing can fail before React mounts its error boundary. Keep the raw textarea
		// visible and let the other field start independently in that case.
		try {
			const initialBlocks = toBlocks( field.value );
			createRoot( mountNode ).render(
				el(
					Boundary,
					{ wrapper },
					el( FieldEditor, { field, initialBlocks, editorSettings } )
				)
			);
		} catch {
			return;
		}
	} );
} )( window.wp, pdblocksSettings );

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
	const { Component, createRoot, useEffect, useState } = wp.element;
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

	const FieldEditor = ( { field, initialBlocks, editorSettings } ) => {
		const history = useStateWithHistory( initialBlocks );
		const [ showSettings, setShowSettings ] = useState( false );
		const [ stored ] = useState( () => ( {
			content: field.value,
			asBlocks: toContent( initialBlocks ),
		} ) );
		const blocks = history.value;

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
							history.setValue( nextBlocks, true ),
						onChange: ( nextBlocks ) =>
							history.setValue( nextBlocks, false ),
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
									'block-editor-product-descriptions-for-woocommerce'
								),
							},
							el( Inserter, {
								position: 'bottom right',
								renderToggle: ( { onToggle, isOpen, disabled } ) =>
									el( Button, {
										className: 'pdblocks__inserter-toggle',
										variant: 'primary',
										size: 'compact',
										icon: 'plus-alt2',
										label: __(
											'Add block',
											'block-editor-product-descriptions-for-woocommerce'
										),
										'aria-expanded': isOpen,
										onClick: onToggle,
										disabled,
									} ),
							} ),
							el( Button, {
								size: 'compact',
								icon: 'undo',
								label: __( 'Undo', 'block-editor-product-descriptions-for-woocommerce' ),
								onClick: history.undo,
								disabled: ! history.hasUndo,
								accessibleWhenDisabled: true,
							} ),
							el( Button, {
								size: 'compact',
								icon: 'redo',
								label: __( 'Redo', 'block-editor-product-descriptions-for-woocommerce' ),
								onClick: history.redo,
								disabled: ! history.hasRedo,
								accessibleWhenDisabled: true,
							} ),
							el(
								'div',
								{ className: 'pdblocks__block-toolbar' },
								el( BlockToolbar, { hideDragHandle: true } )
							),
							el( Button, {
								className: 'pdblocks__settings-toggle',
								size: 'compact',
								icon: 'admin-generic',
								label: __(
									'Block settings',
									'block-editor-product-descriptions-for-woocommerce'
								),
								isPressed: showSettings,
								onClick: () => setShowSettings( ! showSettings ),
							} )
						),
						el(
							'div',
							{ className: 'pdblocks__body' },
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
							showSettings &&
								el(
									'div',
									{
										className: 'pdblocks__settings',
										role: 'region',
										'aria-label': __(
											'Block settings',
											'block-editor-product-descriptions-for-woocommerce'
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

		wrapper.classList.add( 'is-ready' );
		createRoot( mountNode ).render(
			el(
				Boundary,
				{ wrapper },
				el( FieldEditor, {
					field,
					initialBlocks: toBlocks( field.value ),
					editorSettings,
				} )
			)
		);
	} );
} )( window.wp, pdblocksSettings );

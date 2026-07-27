( function ( wp, config ) {
	if ( ! wp || ! config || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	var registerPlugin             = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var TextControl                = wp.components.TextControl;
	var SelectControl              = wp.components.SelectControl;
	var el                         = wp.element.createElement;
	var useSelect                  = wp.data.useSelect;
	var useDispatch                = wp.data.useDispatch;

	function actionOptions() {
		var actions = config.actions || {};
		return Object.keys( actions ).map( function ( value ) {
			return { label: actions[ value ], value: value };
		} );
	}

	function ExpiryPanel() {
		var meta = useSelect(
			function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			},
			[]
		);

		var categories = useSelect(
			function ( select ) {
				return select( 'core' ).getEntityRecords( 'taxonomy', 'category', { per_page: -1, _fields: 'id,name' } ) || [];
			},
			[]
		);

		var editPost = useDispatch( 'core/editor' ).editPost;

		function setMeta( key, value ) {
			var newMeta = {};
			newMeta[ key ] = value;
			editPost( { meta: newMeta } );
		}

		var current    = meta[ config.metaKey ] ? new Date( meta[ config.metaKey ] * 1000 ) : null;
		var fieldValue = current ? current.toISOString().slice( 0, 16 ) : '';
		var action     = meta[ config.actionMetaKey ] || '';

		var children = [
			el( 'p', { key: 'help' }, config.help ),
			el(
				TextControl,
				{
					key: 'datetime',
					label: config.label,
					type: 'datetime-local',
					value: fieldValue,
					onChange: function ( nextValue ) {
						setMeta( config.metaKey, nextValue ? Math.floor( new Date( nextValue ).getTime() / 1000 ) : 0 );
					},
				}
			),
			el(
				SelectControl,
				{
					key: 'action',
					label: config.actionLabel,
					value: action,
					options: actionOptions(),
					onChange: function ( nextValue ) {
						setMeta( config.actionMetaKey, nextValue );
					},
				}
			),
		];

		if ( 'redirect_only' === action ) {
			children.push(
				el(
					TextControl,
					{
						key: 'redirect',
						label: config.redirectLabel,
						type: 'url',
						value: meta[ config.redirectMetaKey ] || '',
						onChange: function ( nextValue ) {
							setMeta( config.redirectMetaKey, nextValue );
						},
					}
				)
			);
		}

		if ( 'category' === action ) {
			var catOptions = [ { label: config.categoryDefault, value: 0 } ].concat(
				categories.map( function ( cat ) {
					return { label: cat.name, value: cat.id };
				} )
			);
			children.push(
				el(
					SelectControl,
					{
						key: 'category',
						label: config.categoryLabel,
						value: meta[ config.categoryMetaKey ] || 0,
						options: catOptions,
						onChange: function ( nextValue ) {
							setMeta( config.categoryMetaKey, parseInt( nextValue, 10 ) || 0 );
						},
					}
				)
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{ name: 'mscpe-expiry-panel', title: config.title },
			children
		);
	}

	registerPlugin( 'mscpe-expiry-sidebar', { render: ExpiryPanel } );
} )( window.wp, window.mscpeExpiryConfig );

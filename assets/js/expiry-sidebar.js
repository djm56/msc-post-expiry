( function ( wp, config ) {
	if ( ! wp || ! config || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	var registerPlugin             = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var TextControl                = wp.components.TextControl;
	var el                         = wp.element.createElement;
	var useSelect                  = wp.data.useSelect;
	var useDispatch                = wp.data.useDispatch;

	function ExpiryPanel() {
		var meta = useSelect(
			function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			},
			[]
		);

		var editPost   = useDispatch( 'core/editor' ).editPost;
		var current    = meta[ config.metaKey ] ? new Date( meta[ config.metaKey ] * 1000 ) : null;
		var fieldValue = current ? current.toISOString().slice( 0, 16 ) : '';

		return el(
			PluginDocumentSettingPanel,
			{ name: 'mscpe-expiry-panel', title: config.title },
			el( 'p', null, config.help ),
			el(
				TextControl,
				{
					label: config.label,
					type: 'datetime-local',
					value: fieldValue,
					onChange: function ( nextValue ) {
						var newMeta = {};
						if ( ! nextValue ) {
							newMeta[ config.metaKey ] = 0;
						} else {
							newMeta[ config.metaKey ] = Math.floor( new Date( nextValue ).getTime() / 1000 );
						}
						editPost( { meta: newMeta } );
					},
				}
			)
		);
	}

	registerPlugin( 'mscpe-expiry-sidebar', { render: ExpiryPanel } );
} )( window.wp, window.mscpeExpiryConfig );

( function ( wp, config ) {
	if ( ! wp || ! config || ! wp.plugins || ! wp.editPost || ! wp.date ) {
		return;
	}

	var registerPlugin             = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var DateTimePicker             = wp.components.DateTimePicker;
	var SelectControl              = wp.components.SelectControl;
	var TextControl                = wp.components.TextControl;
	var el                         = wp.element.createElement;
	var useSelect                  = wp.data.useSelect;
	var useDispatch                = wp.data.useDispatch;
	var dateI18n                   = wp.date.dateI18n;
	var getDate                    = wp.date.getDate;

	/**
	 * Convert a UTC epoch timestamp to a site-local string for DateTimePicker.
	 *
	 * DateTimePicker's currentDate/onChange contract is a timezone-less wall-clock
	 * string (YYYY-MM-DDTHH:mm:ss). The only timezone interpretation we perform
	 * is inside wp.date, which uses the site's configured timezone.
	 *
	 * @param {number} timestamp UTC epoch in seconds.
	 * @return {string|null} Wall-clock string in site-local time, or null if no timestamp.
	 */
	function timestampToSiteDate( timestamp ) {
		if ( ! timestamp ) {
			return null;
		}

		return dateI18n( 'Y-m-d\\TH:i:s', timestamp * 1000 );
	}

	/**
	 * Convert a DateTimePicker value back to a UTC epoch timestamp.
	 *
	 * The picker supplies a timezone-less wall-clock string (YYYY-MM-DDTHH:mm:ss).
	 * We parse that value through wp.date's getDate() so it is interpreted in the
	 * site's configured timezone, not the browser's timezone.
	 *
	 * @param {string|number} value Value from the picker.
	 * @return {number} UTC epoch in seconds.
	 */
	function siteDateToTimestamp( value ) {
		if ( ! value ) {
			return 0;
		}

		if ( 'string' === typeof value ) {
			return Math.floor( getDate( value.replace( 'T', ' ' ) ).getTime() / 1000 );
		}

		if ( 'number' === typeof value ) {
			return Math.floor( value / 1000 );
		}

		return 0;
	}

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

		var timestamp  = meta[ config.metaKey ] ? parseInt( meta[ config.metaKey ], 10 ) : 0;
		var currentDate = timestamp > 0 ? timestampToSiteDate( timestamp ) : null;
		var action     = meta[ config.actionMetaKey ] || '';

		var children = [
			el( 'p', { key: 'help' }, config.help ),
			el(
				'label',
				{
					key: 'datetime-label',
					style: { display: 'block', fontWeight: 'bold', marginBottom: '8px' },
				},
				config.label
			),
			el(
				DateTimePicker,
				{
					key: 'datetime',
					currentDate: currentDate,
					onChange: function ( nextDate ) {
						setMeta( config.metaKey, nextDate ? siteDateToTimestamp( nextDate ) : 0 );
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

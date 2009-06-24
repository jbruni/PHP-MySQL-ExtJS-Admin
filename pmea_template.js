

Ext.BLANK_IMAGE_URL = 's.gif';

Ext.namespace( 'Ext.pmea' );

Ext.pmea.REMOTING_API = { 
  'url': 'index.php',
  'type': 'remoting',
  'actions': { 'pmeaActions': [%pmea_actions%] }
};

Ext.Direct.addProvider( Ext.pmea.REMOTING_API );

Ext.pmea.Feedback = function ( title, message, type, delay ) {
  if ( !message ) return;
  if ( !type ) type = 'info';
  switch( type.toLowerCase() ) {
    case 'error':
      type = Ext.Msg.ERROR;
      break;
    case 'warning':
      type = Ext.Msg.WARNING;
      break;
    default:
      type = Ext.Msg.INFO;
      break;
  }
  var msg = Ext.Msg.show({
    title   : title,
    msg     : message,
    width   : 270,
    buttons : Ext.Msg.OK,
    icon    : type
  });
  if ( delay )
    msg.hide.defer( delay, msg );
}

Ext.pmea.EditorGridPanel = Ext.extend( Ext.grid.EditorGridPanel, {
  title: '[%pmea_title%]',
  frame: true,
  loadMask: true,
  stripeRows: true,
  // enableHdMenu: false,
  store: { fields: [], autoDestroy: true },
  columns: [],
  selModel: new Ext.grid.RowSelectionModel({}),
  
  initComponent: function() {
    var tablesCombo = new Ext.form.ComboBox({
      store: [%tables%],
      forceSelection: true,
      editable: false,
      triggerAction: 'all',
      width: 126
    });
    tablesCombo.on( 'select', this.selectTable, this );
    var bbar = new Ext.PagingToolbar({
      displayInfo: true,
      pageSize: [%pageSize%],
      hidden: true
    });
    var config = { 
      tbar: ['[%txt_tables%] ', tablesCombo,
        { xtype: 'tbspacer', width: 9 },
        { xtype: 'tbbutton', text: '[%txt_newrecord%]', handler: this.notyet, hidden: true }, ' ',
        { xtype: 'tbbutton', text: '[%txt_delrecord%]', handler: this.notyet, hidden: true }
      ],
      bbar: bbar
    };
    Ext.apply( this, Ext.apply( this.initialConfig, config ) );
    Ext.pmea.EditorGridPanel.superclass.initComponent.apply( this, arguments );
  },
  
  notyet: function() { Ext.pmea.Feedback( 'Not implemented yet',
    'You are invited to <b>donate</b> or<br />to <b>join</b> the developers team!<br /><br />' + 
    '<a href="http://www.jbruni.com.br/pmea/">http://www.jbruni.com.br/pmea/', 'info'
  )},
  
  selectTable: function( tablesCombo ) {
    var table = tablesCombo.getValue();
    if ( !Ext.StoreMgr.containsKey( table ) )
      return pmeaActions.getFields( table, this.getId(), this.getStore );
    var store = Ext.StoreMgr.get( table );
    this.reconfigure( store, store.columns );
    var bbar = this.getBottomToolbar();
    bbar.bindStore( store );
    store.fireEvent( 'load', store, store.getRange(), store.lastOptions );
  },
  
  getStore: function( result, e ) {
    if ( !e.status  || ( result == 'pmeaFailure' ) )
      return Ext.pmea.Feedback( '[%txt_failure%]' , '[%txt_failuremsg%]', 'error' );
    var writer = new Ext.data.JsonWriter({ returnJson: false });
    var store = new Ext.data.DirectStore({
      storeId: result.table,
      // PROXY: Ext.data.DirectProxy (paramOrder,paramsAsHash,directFn,api)
      paramOrder: [ 'table', 'start', 'limit', 'sort', 'dir' ],
      paramsAsHash: false,
      api: {
        read    : pmeaActions.getData,
        create  : pmeaActions.newData,
        update  : pmeaActions.setData,
        destroy : pmeaActions.delData
      },
      totalProperty: 'total',
      // READER: Ext.data.JsonReader (totalProperty,root,idProperty) + FIELDS
      root: 'rows',
      idProperty: result.idProperty,
      fields: result.fields,
      // successProperty: 'success',
      writer: writer,
      // STORE: Ext.data.Store specific parameters
      autoSave: false,
      baseParams: { table: result.table },
      remoteSort: true,
      sortInfo: {
        field: '',
        direction: ''
      }
    });
    var columns = new Ext.grid.ColumnModel({
      columns: result.columns,
      defaults: {
        // menuDisabled: true,
        sortable: true
      }
    });
    var grid = Ext.getCmp( result.grid );
    store.columns = columns;
    grid.on( 'afteredit', grid.onAfteredit, store );
    grid.reconfigure( store, columns );
    var bbar = grid.getBottomToolbar();
    bbar.bindStore( store );
    if ( bbar.hidden ) {
      bbar.show();
      var tbar = grid.getTopToolbar();
      tbar.items.each( function(item) { if (item.hidden) item.show(); } );
    }
    store.load({ params: { start: 0, limit: [%pageSize%] } });
  },
  
  onAfteredit: function( e ){
    var table = this.baseParams.table;
    e.record.set( 'pmea_table', table + '|' );
    e.record.set( 'pmea_table', table );
    this.save();
  }
});

Ext.reg( 'pmeaGrid', Ext.pmea.EditorGridPanel );

Ext.Direct.on( 'exception', function(e) { Ext.pmea.Feedback( '[%txt_failure%]', e.message, 'error' ) } );

function pmeaLayout() {
  new Ext.Viewport({
    layout: 'fit',
    items: { xtype: 'pmeaGrid' }
  });
}

var pmea = function() { return { init: pmeaLayout } }();

Ext.onReady( pmea.init, pmea, true );

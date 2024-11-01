<?php

defined('ABSPATH') or die();
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class CaptureFormTable extends WP_List_Table {

    private $wa_api;

    public function __construct(WA_API $wa_api) {
        parent::__construct( array(
            'singular' => 'Capture Form',
            'plural'   => 'Capture Forms',
            'ajax'     => false
        ) );
        $this->wa_api = $wa_api;
    }
    
    
    public function get_columns(){
        $columns = array(
          'userFormName' => 'Form Name',
          'Shortcode' => 'Shortcode',
          'Source' => 'Source'
        );
        return $columns;
    }

    public function get_hidden_columns( ) {
        return array(
            'userFormID',
        );
    } 
      
    public function prepare_items() {
        $my_forms_resp = $this->wa_api->get_wa_capture_forms();
        $my_forms = array();
        foreach($my_forms_resp as $f) {
            $my_forms[$f->userFormID] = (array)$f;
            $my_forms[$f->userFormID]['Shortcode'] = '<div style="display:flex;">[wiseagent form_id="' . esc_html($f->userFormID) . '"]' . '<button type="button" style="display:flex; align-items:center; margin-left:10px;" title="copy to clipboard" class="button button-secondary copyClip" data-clipboard-text="[wiseagent form_id=\'' . esc_html($f->userFormID) . '\']"><i class="dashicons dashicons-clipboard"></i></button></div>';
        }
        usort( $my_forms, array( &$this, 'usort_reorder' ) );
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $my_forms;
    }

    public function no_items() {
        _e( 'No forms avaliable.');
    }
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'userFormName':
            case 'Shortcode':
            case 'Source':
                return $item[ $column_name ];
            default:
                return '';//print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }
    public function get_sortable_columns() {
        $sortable_columns = array(
            'userFormName' => array( 'userFormName', true),
            'Source' => array( 'Source', false )
        );
        return $sortable_columns;
    }

    public function usort_reorder($a,$b){
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'userFormName'; 
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; 
        $result = strcmp($a[$orderby], $b[$orderby]); 
        return ($order==='asc') ? $result : -$result; 
    }
}
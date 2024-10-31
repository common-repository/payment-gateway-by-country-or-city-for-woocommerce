<?php
/**
 * Plugin Name: Payment gateway by country or city for Woocommerce
 * Plugin URI: http://wptrees.com/downloads/payment-gateway-by-cc-plugin/
 * Description: Enable/disable wooommerce payment gateway depending on selected country or by typed city 
 * Version: 1.0.1
 * Author: wptrees
 * Author URI: https://www.wptrees.com
 * Text Domain: ptwpgbcc
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'PTWPGBCC_PLUGIN_SLUG', 'pt-gateway-by-cc' );
class PTWCPGCC{
    static $instance = false;
    private function __construct() {
        // back end
        add_action      ( 'plugins_loaded',                     array( $this, 'textdomain'              )           );
        add_filter      ( 'plugin_action_links_' . plugin_basename( __FILE__ ), array($this, 'ptwpgbcc_action_links'));
        if(!is_admin()){
            add_filter      ( 'woocommerce_available_payment_gateways', array( $this,'ptwpgbcc_payment_gateway_disable_city' ));
            add_action      ( 'woocommerce_after_checkout_form', array( $this,'ptwpgbcc_show_notice_shipping' ));
        }
        add_action      ( 'admin_menu', array($this, 'register_ptwpgbcc_submenu_page') );
        register_activation_hook( __FILE__, array($this,'ptwpgbcc_activate' ));
    }
    public static function getInstance() {
        if ( !self::$instance )
            self::$instance = new self;
        return self::$instance;
    }
    public function ptwpgbcc_action_links( $links )
    {
        $links[] = '<a href="'. menu_page_url( PTWPGBCC_PLUGIN_SLUG, false ) .'">'.__('Settings','ptwpgbcc').'</a>';
        return $links;
    }

    public function textdomain(){
        load_plugin_textdomain( 'ptwpgbcc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function register_ptwpgbcc_submenu_page() {
        add_submenu_page( 'woocommerce', 'pt_gateway_by_cc', __('Gateway by Country/City','ptwpgbcc'), 'manage_woocommerce', 'pt-gateway-by-cc', array($this, 'ptwpgbcc_options_page') , 5 );
    }
    public function ptwpgbcc_activate() {
        global $wpdb;
        $pre = $wpdb->prefix;
        $table_name = $pre .'ptwpgbcc';
        $query = "CREATE TABLE IF NOT EXISTS $table_name (
        ruleid INT AUTO_INCREMENT,
        cat VARCHAR(255) NOT NULL,
        C_type VARCHAR(255) NOT NULL,
        gateway VARCHAR(255) NOT NULL,
        action VARCHAR(255) NOT NULL,
        PRIMARY KEY (ruleid)
        )  ENGINE=INNODB;";
        $wpdb->get_results($query);
    }
    public function ptwpgbcc_options_page()
{

    echo '
    <style>
        .wpg_main_div {width: 1150px;
                        text-align: left;
                        margin: auto;
                        margin-top:50px;
                        padding: 1em;
                        color:white;
                        border: 1px solid #ddd;
                        background: #00a0d2;}

        .wpg_fz_20 {font-size:20px;}

        .wpg_inner_div{
                        width: 1150px;
                        text-align: left;
                        margin: auto;
                        padding: 1em;
                        border: 1px solid #ddd;
                        background: white;
                        padding-bottom:60px;
                        }
        .wpg_inner_div_no_bottom{
                        width: 1150px;
                        text-align: left;
                        margin: auto;
                        padding: 1em;
                        border: 1px solid #ddd;
                        background: white;
                        color:red;
                        }
        .wgp_notice_color {color:#c00000;}
        .wgp_h3 {text-align: center; margin-top:90px; margin-bottom:30px;}
        .wgp_inner_notice {color:red; font-size: 11px;}
        .wgp_new_r_div {float:right; margin-top:10px;}
        .wgp_plus_icon {color:white; margin-top: 5px;}
        .wgp_remove_icon {color:red; margin-top: 5px;}
        .wgp_h4 {margin-top:30px; margin-bottom:30px;}
        .wgp_credit {
                        width: 1150px;
                        text-align: left;
                        margin: auto;
                        padding: 1em;
                        border: 1px solid #ddd;}
        .wgp_a {font-size: 1.1em;    text-decoration: none;}
        .wgp_rate {color:#c00000; margin-left:50px;  float:right;}
        .r-d-c {display: contents !important;}
    </style>
    ';

/*
saving countries rules
*/

    global $wpdb;
    $pre = $wpdb->prefix;
    $table_name = $pre .'ptwpgbcc';

    $gateways = WC()->payment_gateways->payment_gateways();

    global $woocommerce;
    $countries_obj   = new WC_Countries();
    $countries   = $countries_obj->__get('countries');

    if(isset($_POST['save_button_co']))
    {

        if(($_POST['country_select'])&&(!empty($_POST['country_select']))&&($_POST['payment_option'])&&(!empty($_POST['payment_option']))&&($_POST['payment_action'])&&(!empty($_POST['payment_action']))){

            if(wp_verify_nonce($_POST['country_nonces'], 'wpgbcc_country_nonces')){

                $country = sanitize_text_field($_POST['country_select']);
                $po = sanitize_text_field($_POST['payment_option']);
                $pa = sanitize_text_field($_POST['payment_action']);

                $allowed_pa_keys = ['1','2'];

                $allowed_gateways = array();

                foreach( $gateways as $gateway ) {
                    if( $gateway->enabled == 'yes' ) {
                        $allowed_gateways[] = $gateway->id ;
                    }
                }

                $allowed_country = array();
                foreach( $countries as $key=>$value ) {
                    $allowed_country[] = $key ;
                }

                if (in_array($country, $allowed_country, true)) {
                if (in_array($po, $allowed_gateways, true)) {
                if (in_array($pa, $allowed_pa_keys, true)) {

                    if(($_POST['rule_id'])&&(!empty($_POST['rule_id']))){

                        $rule_id = sanitize_text_field($_POST['rule_id']);

                        $query = 'select COUNT(*) from '.$table_name.' where cat="country" and ruleid != '.$rule_id.' and C_type = "'.$country.'" and gateway = "'.$po.'"';

                        $rows = $wpdb->get_var($query);
                        if($rows>0){
                            $query = "update $table_name set action = %s where cat='country' and C_type = %s and gateway = %s";
                            $wpdb->get_results($wpdb->prepare($query, array($pa, $country, $po)));
                            $query = "delete from $table_name where ruleid = %d ";
                            $wpdb->get_results($wpdb->prepare($query, $rule_id));
                        }else{
                            $query = "update $table_name set action = %s , cat='country' , C_type = %s , gateway = %s where ruleid = %d";
                            $wpdb->get_results($wpdb->prepare($query,array($pa, $country, $po, $rule_id)));
                        }


                    }else{

                        $query = 'select COUNT(*) from '.$table_name.' where cat="country" and C_type = "'.$country.'" and gateway = "'.$po.'"';

                        $rows = $wpdb->get_var($query);
                        if($rows>0){
                            $query = "update $table_name set action = %s where cat='country' and C_type = %s and gateway = %s";
                            $wpdb->get_results($wpdb->prepare($query, array($pa, $country, $po)));
                        }else{
                            $query = "insert into $table_name (cat,C_type,gateway,action) values ('country',%s,%s,%s)";
                            $wpdb->get_results($wpdb->prepare($query, array($country, $po, $pa)));
                        }


                    }

                }}}
            }
        }
    }

/*
removing countries rules
*/

    if(isset($_POST['remove_button_co'])){
        if(($_POST['rule_id'])&&(!empty($_POST['rule_id']))){
            if(wp_verify_nonce($_POST['country_nonces'], 'wpgbcc_country_nonces')){
                $rule_id = sanitize_text_field($_POST['rule_id']);
                $query = "delete from $table_name where ruleid = %d ";
                $wpdb->get_results($wpdb->prepare($query, $rule_id));
            }
        }
    }


    ?>
    <div class="wpg_main_div">
    <p class="wpg_fz_20">
    <?php _e('Welcome to "Payment gateway by country or city for Woocommerce" plugin settings','ptwpgbcc'); ?>
    </p>
    </div>

    <div class="wpg_inner_div">
    <?php


    if(!$gateways)
        echo '<h2 class="wgp_notice_color">*'.esc_html__( 'no gateways activated in woocommerce, please activate at least one pament gateway to add rules', 'ptwpgbcc' ).'</h2>';
    else{


        echo '<h3 class="wgp_h3">'.esc_html__( 'Woocommerce payment gateway rules by country', 'ptwpgbcc' ).'</h3>';
        echo '<table class="widefat fixed" cellspacing="0" id="c-rule-table">
                <thead>
                <tr>
                    <th style="width:30px;">'.esc_html__( 'Rule', 'ptwpgbcc' ).'<br> &nbsp;</th>
                    <th style="width:315px;">'.esc_html__( 'Country', 'ptwpgbcc' ).'<br><font class="wgp_inner_notice" >*'.esc_html__( 'country to apply rule on', 'ptwpgbcc' ).'</font></th>
                    <th style="width:200px;">'.esc_html__( 'Gateway', 'ptwpgbcc' ).'<br><font class="wgp_inner_notice">*'.esc_html__( 'Gateway to apply rule on', 'ptwpgbcc' ).'</font></th>
                    <th style="width:390px;">'.esc_html__( 'Action', 'ptwpgbcc' ).'<br><font class="wgp_inner_notice">*'.esc_html__( 'enable/disable this gateway', 'ptwpgbcc' ).'</font></th>
                    <th style="width:90px;">'.esc_html__( 'Active/disable', 'ptwpgbcc' ).'<br> &nbsp;</th>
                </tr>
                </thead>
                <tbody>';


        global $wpdb;
        $pre = $wpdb->prefix;
        $table_name = $pre .'ptwpgbcc';
        $query = 'select * from '.$table_name.' where cat="country" ';
        $results = $wpdb->get_results($query);

        if(!$results){
            $j=2;
            $r_c = 0;
        }else{
            $j=2;
            foreach($results as $r){
                $j++;
            }
        }

        for($i=1;$i<26;$i++){
            if($i>=$j)
                $hide = 'display: none;';
            else
                $hide = '';


            if($i<=($j-2)){
                $c_id =  $results[$i-1]->ruleid;
                $c_co =  $results[$i-1]->C_type;
                $c_pg =  $results[$i-1]->gateway;
                $c_ac =  $results[$i-1]->action;
            }else{
                $c_id =  "";
                $c_co =  "";
                $c_pg =  "";
                $c_ac =  "";
            }

            echo '<form action="#" name="country_form_rule" method="post">';
            echo '<tr id="row-c-'.$i.'" style="'.$hide.'"><td style="width:30px;">';
            echo '#'.$i.'<input type="hidden" name="rule_id" value="'.esc_attr($c_id).'"></td>';

            echo '<input type="hidden" name="country_nonces" value="'.wp_create_nonce('wpgbcc_country_nonces').'"></td>';

            echo '<td style="width:315px;">
            <select id="country_select" name="country_select" required>';
            echo '<option value="">No country selected</option>';
            foreach( $countries as $key=>$value ) {
                        if ($key == $c_co ) $s = 'selected' ; else $s = '';
                        echo '<option value="'.esc_attr($key).'" '.$s.'>'.esc_html__($value).'</option>';
            }
            echo '</select></td>';


            echo '<td style="width:200px;">
            <select id="payment_option" name="payment_option" required>';
            echo '<option value="">'.esc_html__( 'No Gateway selected', 'ptwpgbcc' ).'</option>';
            foreach( $gateways as $gateway ) {

                    if( $gateway->enabled == 'yes' ) {
                        if ($gateway->id == $c_pg ) $s = 'selected' ; else $s = '';
                        echo '<option value="'.esc_attr($gateway->id).'" '.$s.'>'.esc_html__($gateway->title).'</option>';
                    }
            }
            echo '</select></td>';

            if ($c_ac == 1);
            echo '<td style="width:390px;">
            <select id="payment_action" name="payment_action" required>
                <option value="">'.esc_html__( 'No Option seleted', 'ptwpgbcc' ).'</option>';
                if ($c_ac == 1)
                    echo '<option value="1" selected>'.esc_html__( 'only include this payment gateway in this country', 'ptwpgbcc' ).'</option>';
                else
                    echo '<option value="1">'.esc_html__( 'only include this payment gateway in this country', 'ptwpgbcc' ).'</option>';
                if ($c_ac == 2)
                    echo '<option value="2" selected>'.esc_html__( 'Does not include this payment gateway in this country', 'ptwpgbcc' ).'</option>';
                else
                    echo '<option value="2">'.esc_html__( 'Does not include this payment gateway in this country', 'ptwpgbcc' ).'</option>';
            echo '</select></td>';


            echo '<td style="width:90px;">  <input type="submit" name="save_button_co" value="save" onclick="return confirm(\''.esc_html__( 'Are you sure you want to Add this rule? \n*notice: if this country has same payment gateway rule, this will override old rule', 'ptwpgbcc' ).'\')" class="button-secondary">';echo ' <button   type="submit" name="remove_button_co" value="remove" class="button-secondary r-d-c"  onclick="return confirm(\''.esc_html__( 'Are you sure you want to Delete this rule?', 'ptwpgbcc' ).'\')"><span class="dashicons dashicons-minus wgp_remove_icon"></span> </button> </td>';

            echo '</tr></form>';
        }


        echo'</tbody> </table>';




        echo '<div id="add_new_r_button_div" class="wgp_new_r_div"><button id="add_new_r_button" onclick="show_new_r_c()" class="button-primary add-c-rule" >'.esc_html__( 'Add new rule', 'ptwpgbcc' ).' <span class="dashicons dashicons-plus wgp_plus_icon"></span></button></div>';

        echo '
        <script>
            var r_c_count = '.$j.';
            function show_new_r_c(){
                jQuery(document).ready(function($) {
                  $("#row-c-"+r_c_count).fadeIn("slow");
                  r_c_count= r_c_count +1;
                  if(r_c_count==26){
                    $("#add_new_r_button").fadeOut("slow");
                    $("#add_new_r_button_div").text("Max rules number reached");
                  }
                });
            }
        </script>
        ';

/*
*
*
*  city rules
*
*
*/

/*
saving city rules
*/

    if(isset($_POST['save_button_ci']))
    {

        if(($_POST['city_select'])&&($_POST['payment_option_ci'])&&($_POST['payment_action_ci'])&&(!empty($_POST['city_select']))&&(!empty($_POST['payment_option_ci']))&&(!empty($_POST['payment_action_ci']))){

            if(wp_verify_nonce($_POST['city_nonces'], 'wpgbcc_city_nonces')){

                $city = sanitize_text_field($_POST['city_select']);
                $po = sanitize_text_field($_POST['payment_option_ci']);
                $pa = sanitize_text_field($_POST['payment_action_ci']);

                $allowed_pa_keys = ['1','2'];

                $allowed_gateways = array();

                foreach( $gateways as $gateway ) {
                    if( $gateway->enabled == 'yes' ) {
                        $allowed_gateways[] = $gateway->id ;
                    }
                }


                if (in_array($po, $allowed_gateways, true)) {
                if (in_array($pa, $allowed_pa_keys, true)) {

                    if(($_POST['rule_id_ci'])&&(!empty($_POST['rule_id_ci']))){

                        $rule_id = sanitize_text_field($_POST['rule_id_ci']);



                        $query = 'select COUNT(*) from '.$table_name.' where cat="city" and ruleid != '.$rule_id.' and C_type = "'.$city.'" and gateway = "'.$po.'"';

                        $rows = $wpdb->get_var($query);
                        if($rows>0){
                            $query = "update $table_name set action= %s where cat='city' and C_type = %s and gateway = %s";
                            $wpdb->get_results($wpdb->prepare($query, array($pa, $city, $po)));
                            $query = "delete from $table_name where ruleid = %d ";
                            $wpdb->get_results($wpdb->prepare($query, $rule_id));
                        }else{
                            $query = "update $table_name set action= %s , cat='city', C_type = %s , gateway = %s where ruleid = %d";
                            $wpdb->get_results($wpdb->prepare($query, array($pa, $city, $po, $rule_id)));
                        }


                    }else{

                        $query = "select COUNT(*) from $table_name where cat='city' and C_type = %s and gateway =  %s ";
                        $rows = $wpdb->get_var($wpdb->prepare($query, array($city, $po)));
                        if($rows>0){
                            $query = "update $table_name set action = %s where cat='city' and C_type = %s and gateway = %s ";
                            $wpdb->get_results($wpdb->prepare($query, array( $pa, $city, $po)));
                        }else{
                            $query = "insert into $table_name (cat,C_type,gateway,action) values ('city',%s,%s,%s)";
                            $wpdb->get_results($wpdb->prepare($query, array( $city, $po, $pa)));
                        }
                    }
                }}
            }
        }
    }

/*
removing city rules
*/

    if(isset($_POST['remove_button_ci'])){
        if(($_POST['rule_id_ci'])&&(!empty($_POST['rule_id_ci']))){
            if(wp_verify_nonce($_POST['city_nonces'], 'wpgbcc_city_nonces')){
                $rule_id = sanitize_text_field($_POST['rule_id_ci']);
                $query = "delete from $table_name where ruleid = %d ";
                $wpdb->get_results($wpdb->prepare($query, $rule_id));
            }
        }
    }


        echo '<h3 class="wgp_h3">'.esc_html__( 'Woocommerce payment gateway rules by city', 'ptwpgbcc' ).'</h3>';
        echo '<h4 class="wgp_h4"><font color="#00a0d2" >'.esc_html__( 'type the city name , and whenever this name is located in paymenet address , the rule will be applied', 'ptwpgbcc' ).'</font><br> *'.esc_html__( 'ex: type the word: york here and whenever it shows in address field it will activate the rule, this includes : newyork - new york - NEW YORK - ***york*** - ...', 'ptwpgbcc' ).'</h4>';
        echo '<table class="widefat fixed" cellspacing="0">
                <thead>
                <tr>
                    <th style="width:30px;">'.esc_html__( 'Rule', 'ptwpgbcc' ).'<br> &nbsp;</th>
                    <th style="width:315px;">'.esc_html__( 'City', 'ptwpgbcc' ).'<br><font class="wgp_inner_notice">*'.esc_html__( 'city to apply rule on', 'ptwpgbcc' ).'</font></th>
                    <th style="width:200px;">'.esc_html__( 'Gateway', 'ptwpgbcc' ).'<br><font class="wgp_inner_notice">*'.esc_html__( 'Gateway to apply rule on', 'ptwpgbcc' ).'</font></th>
                    <th style="width:390px;">'.esc_html__( 'a', 'ptwpgbcc' ).'Action<br><font class="wgp_inner_notice">*'.esc_html__( 'enable/disable this gateway', 'ptwpgbcc' ).'</font></th>
                    <th style="width:90px;">'.esc_html__( 'Active/disable', 'ptwpgbcc' ).'<br> &nbsp;</th>
                </tr>
                </thead>
                <tbody>';



        global $wpdb;
        $pre = $wpdb->prefix;
        $table_name = $pre .'ptwpgbcc';
        $query = "select * from $table_name where cat='city'";
        $results = $wpdb->get_results($query);

        if(!$results){
            $j_cc=2;
            $r_c = 0;
        }else{
            $j_cc=2;
            foreach($results as $r){
                $j_cc++;
            }
        }

        for($i=1;$i<51;$i++){
            if($i>=$j_cc)
                $hide = 'display: none;';
            else
                $hide = '';


            if($i<=($j_cc-2)){
                $c_id =  $results[$i-1]->ruleid;
                $c_co =  $results[$i-1]->C_type;
                $c_pg =  $results[$i-1]->gateway;
                $c_ac =  $results[$i-1]->action;
            }else{
                $c_id =  "";
                $c_co =  "";
                $c_pg =  "";
                $c_ac =  "";
            }

            echo '<form action="#" name="city_form_rule" method="post">';
            echo '<tr id="row-cc-'.$i.'" style="'.$hide.'"><td style="width:30px;">';
            echo '#'.$i.'<input type="hidden" name="rule_id_ci" value="'.esc_attr($c_id).'"></td>';

            echo '<input type="hidden" name="city_nonces" value="'.wp_create_nonce('wpgbcc_city_nonces').'"></td>';

            echo '<td style="width:315px;">';
            echo '*<input type="text" name="city_select" value="'.esc_attr($c_co).'" required>*';
            echo '</td>';


            echo '<td style="width:200px;">
            <select id="payment_option_ci" name="payment_option_ci" required>';
            echo '<option value="">'.esc_html__( 'No Gateway selected', 'ptwpgbcc' ).'</option>';
            foreach( $gateways as $gateway ) {

                    if( $gateway->enabled == 'yes' ) {
                        if ($gateway->id == $c_pg ) $s = 'selected' ; else $s = '';
                        echo '<option value="'.esc_attr($gateway->id).'" '.$s.' >'.esc_html__($gateway->title).'</option>';
                    }
            }
            echo '</select></td>';

            echo '<td style="width:390px;">
            <select id="payment_action_ci" name="payment_action_ci" required="required">
                <option value="">'.esc_html__( 'No Option seleted', 'ptwpgbcc' ).'</option>';
                if ($c_ac == 1)
                    echo '<option value="1" selected>'.esc_html__( 'only include this payment gateway in this city', 'ptwpgbcc' ).'</option>';
                else
                    echo '<option value="1">'.esc_html__( 'only include this payment gateway in this city', 'ptwpgbcc' ).'</option>';
                if ($c_ac == 2)
                    echo '<option value="2" selected>'.esc_html__( 'Does not include this payment gateway in this city', 'ptwpgbcc' ).'</option>';
                else
                    echo '<option value="2">'.esc_html__( 'Does not include this payment gateway in this city', 'ptwpgbcc' ).'</option>';
                echo '</select></td>';


            echo '<td style="width:90px;">  <input type="submit" name="save_button_ci" value="save" onclick="return confirm(\''.esc_html__( 'Are you sure you want to Add this rule? \n\n*notice: if this city has same payment gateway rule, this will override old rule', 'ptwpgbcc' ).'\')" class="button-secondary">';echo ' <button   type="submit" name="remove_button_ci" value="remove" class="button-secondary r-d-c"  onclick="return confirm(\''.esc_html__( 'Are you sure you want to Delete this rule?', 'ptwpgbcc' ).'\')"><span class="dashicons dashicons-minus wgp_remove_icon"></span> </button> </td>';


            echo '</tr></form>';
        }


        echo'</tbody> </table>';


        echo '<div id="add_new_r_button_div_cc" class="wgp_new_r_div"><button id="add_new_r_button_cc" onclick="show_new_r_c_cc()" class="button-primary add-c-rule_cc" >'.esc_html__( 'Add new rule', 'ptwpgbcc' ).' <span class="dashicons dashicons-plus wgp_plus_icon"></span></button></div>';


        echo '
        <script>
            var r_c_count_cc = '.$j_cc.';
            function show_new_r_c_cc(){
                jQuery(document).ready(function($) {
                  $("#row-cc-"+r_c_count_cc).fadeIn("slow");
                  r_c_count_cc = r_c_count_cc +1;
                  if(r_c_count_cc==51){
                    $("#add_new_r_button_cc").fadeOut("slow");
                    $("#add_new_r_button_div_cc").text("Max rules number reached");
                  }
                }); 
            }
        </script>
        ';



    }


    echo '</div>';
    echo '
    <div class="wgp_credit">


    <a class="wgp_a" href="https://primatree.com" target="blank" class="linkout">This plugin made with  <span class="dashicons dashicons-heart " style="color:#c00000;"></span> by PrimaTree.</a>


    <a class="wgp_a wgp_rate" href="http://wordpress.org/support/view/plugin-reviews/payment-gateway-by-country-or-city-for-woocommerce?free-counter?rate=5#postform" target="blank" class="linkout">Show Us Some Love:  Leave a review <span class="dashicons dashicons-star-filled " style="color:#e6b800"></span><span class="dashicons dashicons-star-filled " style="color:#e6b800"></span><span class="dashicons dashicons-star-filled " style="color:#e6b800"></span><span class="dashicons dashicons-star-filled " style="color:#e6b800"></span><span class="dashicons dashicons-star-filled " style="color:#e6b800"></span>
    </a>

    </div>
    ';

?>

<?php
}
    public function ptwpgbcc_payment_gateway_disable_city( $available_gateways ) {
        $gateways = WC()->payment_gateways->payment_gateways();
        if(!WC()->customer)
            return $available_gateways;
        
        foreach( $gateways as $gateway ) {
            $c_string = '';
            $co_string = '';
            if( $gateway->enabled == 'yes' ) {

                if(WC()->customer->get_shipping_city())
                $c_string = WC()->customer->get_shipping_city();

                if(WC()->customer->get_shipping_country())
                $co_string = WC()->customer->get_shipping_country();

                $pn = $gateway->id;
                global $wpdb;
                $pre = $wpdb->prefix;
                $table_name = $pre .'ptwpgbcc';

                $query = 'select C_type from '.$table_name.' where cat="country" and gateway = %s and action = 1 ';

                $results = $wpdb->get_results($wpdb->prepare($query, $pn));

                $query2 = 'select C_type from '.$table_name.' where cat="city" and gateway = %s and action = 1 ';

                $results2 = $wpdb->get_results($wpdb->prepare($query2, $pn));

                if(($results)||($results2)){
                    $not_in = 1;

                    if($results){
                        foreach($results as $r){
                            $country = $r->C_type;

                            if(mb_strpos(strtolower($co_string), strtolower($country))  === false ){

                            }else{
                                $not_in = 2;
                            }
                        }
                    }

                    if($not_in==1){
                        if($results2){
                            foreach($results2 as $r){
                                $city = $r->C_type;
                                if(mb_strpos(strtolower($c_string), strtolower($city))  === false ){

                                }else{
                                    $not_in = 2;
                                }
                            }
                        }
                    }

                    if($not_in==1)
                        unset( $available_gateways[$pn] );
                }

                $query = 'select C_type from '.$table_name.' where cat="country" and gateway = %s and action = 2 ';

                $results = $wpdb->get_results($wpdb->prepare($query,$pn));

                if($results){
                    $not_in = 1;
                    foreach($results as $r){
                        $country = $r->C_type;


                        if(mb_strpos(strtolower($co_string), strtolower($country))  === false ){

                        }else{
                            $not_in = 2;
                        }
                    }

                    if($not_in==2){
                        if($results2){
                            foreach($results2 as $r){
                                $city = $r->C_type;
                                if(mb_strpos(strtolower($c_string), strtolower($city))  === false ){

                                }else{
                                    $not_in = 1;
                                }
                            }
                        }
                    }

                    if($not_in==2)
                        unset( $available_gateways[$pn] );
                }



                $query = 'select C_type from '.$table_name.' where cat="city" and gateway = %s and action = 2 ';

                $results = $wpdb->get_results($wpdb->prepare($query, $pn));

                if($results){
                    $not_in = 1;
                    foreach($results as $r){
                        $city = $r->C_type;
                        if(mb_strpos(strtolower($c_string), strtolower($city))  === false ){

                        }else{
                            $not_in = 2;
                        }
                    }
                    if($not_in==2)
                        unset( $available_gateways[$pn] );
                }



            }
        }
        return $available_gateways;

    }
    public function ptwpgbcc_show_notice_shipping(){
        ?>
        <script>
            jQuery(document).ready(function($){
                $('#billing_city').change(function(){

                    jQuery( 'body' ).trigger( 'update_checkout' );
                });
                $('#billing_city').blur(function(){
                    jQuery( 'body' ).trigger( 'update_checkout' );
                });

                $('#shipping_city').change(function(){
                    jQuery( 'body' ).trigger( 'update_checkout' );
                });
                $('#shipping_city').blur(function(){
                    jQuery( 'body' ).trigger( 'update_checkout' );
                });

            });
        </script>

        <?php

    }
}
// Instantiate our class
$PTWCPGCC = PTWCPGCC::getInstance();
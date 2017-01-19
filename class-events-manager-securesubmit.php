<?php
/**
 * Events Manager SecureSubmit Gateway
 *
 * @package   EM_Gateway_SecureSubmit
 * @author    Mark Hagan <mark.hagan@e-hps.com>
 * @license   Custom (https://github.com/SecureSubmit/WordPress/blob/master/LICENSE.md)
 * @link      https://developer.heartlandpaymentsystems.com/SecureSubmit/
 */

/**
 * SecureSubmit Gateway class
 *
 * @package EM_Gateway_SecureSubmit
 * @author  Mark Hagan <mark.hagan@e-hps.com>
 */

class EM_Gateway_SecureSubmit extends EM_Gateway {
    /**
     * Gateway reference, which is used in various places for referencing gateway info. Use lowercase characters/numbers and underscores.
     *
     * @since    1.0.0
     *
     * @var string
     */
    var $gateway = 'securesubmit';

    /**
     * This will be what admins see as the gateway name (e.g. Offline, PayPal, Authorize.net ...)
     *
     * @since    1.0.0
     *
     * @var string
     */
    var $title = 'SecureSubmit';

    /**
     * The default status value your gateway assigns this booking. Default is 4, i.e. awaiting online payment
     *
     * @since    1.0.0
     *
     * @var int
     */
    var $status = 4;

    /**
     * Set this to any true value and this will trigger the em_my_bookings_booked_message function to override the status name of this booking when in progress.
     *
     * @since    1.0.0
     *
     * @var string
     */
    var $status_txt = 'Processing (SecureSubmit)';

    /**
     * If your gateway supports the ability to pay without requiring further fields (e.g. credit card info), then you can set this to true.
     *
     * You will automatically have the ability to show buttons within your gateway. It's up to you to change what happens after by
     * overriding functions from EM_Gateway such as modifying booking_add or booking_form_feedback.
     *
     * @since    1.0.0
     *
     *
     * @var boolean
     */
    var $button_enabled = false;

    /**
     * If your gateway is compatible with our Multiple Bookings Mode, then you can set this to true, otherwise your gateway won't be available for booking in this mode.
     *
     * @since    1.0.0
     *
     *
     * @var boolean
     */
    var $supports_multiple_bookings = true;

    /**
     * Some external gateways (e.g. PayPal IPNs) return information back to your site about payments, which allow you to automatically track refunds made outside Events Manager.
     * If you enable this to true, be sure to add an overriding handle_payment_return function to deal with the information sent by your gateway.
     *
     * @since    1.0.0
     *
     * @var unknown_type
     */
    var $payment_return = true;

    /**
     * Timeout limit for automatic user registration
     *
     * @since   1.0.0
     *
     * @var     string
     */
    var $registered_timer = 0;

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @var     string
     */
    protected $version = '1.0.0';

    /**
     * Unique identifier for your plugin.
     *
     * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
     * match the Text Domain file header in the main plugin file.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'events-manager-securesubmit';

    /**
     * Slug of the plugin screen.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_screen_hook_suffix = null;

    /**
     * Initialize the plugin by setting localization, filters, and administration functions.
     *
     * @since     1.0.0
     */
    public function __construct() {
        parent::__construct();

        if ($this->is_active()) {
            // Load plugin text domain
            add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

            add_filter( 'em_wp_localize_script', array( $this, 'em_wp_localize_script' ), 10, 1 );
            add_filter( 'em_booking_form_action_url', array( $this, 'force_ssl' ), 10, 1 );
            wp_enqueue_script('securesubmit', plugins_url('jquery.securesubmit.js',__FILE__), array('jquery'));
        }
    }

   /**
     * This function intercepts the previous booking form url from the javascript localized array of EM variables and
     * forces it to be an HTTPS url.
     *
     * @since    1.0.0
     *
     * @param array $localized_array
     * @return array
     */
    public function em_wp_localize_script($localized_array) {
        $localized_array['bookingajaxurl'] = $this->force_ssl($localized_array['bookingajaxurl']);

        if ( ! is_admin() )   {
            $localized_array['ajaxurl'] = $this->force_ssl($localized_array['ajaxurl']);
        }

        return $localized_array;
    }

    /**
     * Turns any url into an HTTPS url.
     * @param string $url
     * @return string
     */
    function force_ssl($url){
        return $url;
        //return str_replace('http://','https://', $url);
    }

    /**
     * Triggered by the em_booking_add_securesubmit action, modifies the booking status if the event isn't free and also adds a filter to modify user feedback returned.
     *
     * @since    1.0.0
     *
     * @param EM_Event $EM_Event
     * @param EM_Booking $EM_Booking
     * @param boolean $post_validation
     */
    public function booking_add($EM_Event,$EM_Booking, $post_validation = false){
        global $wpdb, $wp_rewrite, $EM_Notices;
        $this->registered_timer = current_time('timestamp', 1);
        parent::booking_add($EM_Event, $EM_Booking, $post_validation);

        if( $post_validation && empty($EM_Booking->booking_id) ){
            if( get_option('dbem_multiple_bookings') && get_class($EM_Booking) == 'EM_Multiple_Booking' ){
                add_filter('em_multiple_booking_save', array(&$this, 'em_booking_save'),2,2);
            }else{
                add_filter('em_booking_save', array(&$this, 'em_booking_save'),2,2);
            }
        }
    }

    /**
     * Added to filters once a booking is added. Once booking is saved, we capture payment, and approve the booking (saving a second time). If payment isn't approved, just delete the booking and return false for save.
     * @param bool $result
     * @param EM_Booking $EM_Booking
     */
    function em_booking_save( $result, $EM_Booking ){
        global $wpdb, $wp_rewrite, $EM_Notices;
        //make sure booking save was successful before we try anything
        if( $result ){
            if( $EM_Booking->get_price() > 0 ){
                //handle results
                $capture = $this->authorize_and_capture($EM_Booking);
                if($capture){
                    //Set booking status, but no emails sent
                    if( !get_option('em_'.$this->gateway.'_manual_approval', false) || !get_option('dbem_bookings_approval') ){
                        $EM_Booking->set_status(1, false); //Approve
                    }else{
                        $EM_Booking->set_status(0, false); //Set back to normal "pending"
                    }
                }else{
                    //not good.... error inserted into booking in capture function. Delete this booking from db
                    if( !is_user_logged_in() && get_option('dbem_bookings_anonymous') && !get_option('dbem_bookings_registration_disable') && !empty($EM_Booking->person_id) ){
                        //delete the user we just created, only if created after em_booking_add filter is called (which is when a new user for this booking would be created)
                        $EM_Person = $EM_Booking->get_person();
                        if( strtotime($EM_Person->data->user_registered) >= $this->registered_timer ){
                            if( is_multisite() ){
                                include_once(ABSPATH.'/wp-admin/includes/ms.php');
                                wpmu_delete_user($EM_Person->ID);
                            }else{
                                include_once(ABSPATH.'/wp-admin/includes/user.php');
                                wp_delete_user($EM_Person->ID);
                            }
                            //remove email confirmation
                            global $EM_Notices;
                            $EM_Notices->notices['confirms'] = array();
                        }
                    }
                    $EM_Booking->manage_override = true;
                    $EM_Booking->delete();
                    $EM_Booking->manage_override = false;
                    return false;
                }
            }
        }
        return $result;
    }

    /**
     * Intercepts return JSON and adjust feedback messages when booking with this gateway. This filter is added only when the em_booking_add function is triggered by the em_booking_add filter.
     *
     * @since    1.0.0
     *
     * @param array $return
     * @param EM_Booking $EM_Booking
     * @return array
     */
    public function booking_form_feedback( $return, $EM_Booking ){
        // Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
        if (!empty($return['result'])) {
            if (!empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_meta['gateway'] == $this->gateway && $EM_Booking->get_price() > 0) {
                $return['message'] = get_option('em_securesubmit_booking_feedback');
            } else {
                //returning a free message
                $return['message'] = get_option('em_securesubmit_booking_feedback_free');
            }
        } elseif (!empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_meta['gateway'] == $this->gateway && $EM_Booking->get_price() > 0) {
            //void this last authroization
            $this->__void($EM_Booking);
        }
        return $return;
    }

    /*
     * --------------------------------------------------
     * Booking UI - modifications to booking pages and tables containing securesubmit bookings
     * --------------------------------------------------
     */

    /**
     * Outputs custom content and credit card information.
     */
    function booking_form(){
        echo get_option('em_'.$this->gateway.'_form');
        ?>
        <input type="hidden" name="securesubmit_token" id="securesubmit_token" />
        <p class="em-bookings-form-gateway-cardno">
          <label><?php  _e('Credit Card Number','em-pro'); ?></label>
          <input type="text" size="15" value="" id="card_num" class="input" />
        </p>
        <p class="em-bookings-form-gateway-expiry">
          <label><?php  _e('Expiry Date','em-pro'); ?></label>
          <select id="exp_month" >
            <?php
                for($i = 1; $i <= 12; $i++){
                    $m = $i > 9 ? $i:"0$i";
                    echo "<option>$m</option>";
                }
            ?>
          </select> /
          <select id="exp_year" >
            <?php
                $year = date('Y',current_time('timestamp'));
                for($i = $year; $i <= $year+10; $i++){
                    echo "<option>$i</option>";
                }
            ?>
          </select>
        </p>
        <p class="em-bookings-form-ccv">
          <label><?php  _e('CVV','em-pro'); ?></label>
          <input type="text" size="4" id="card_cvv" value="" class="input" />
        </p>

        <script type="text/javascript">
            function secureSubmitResponseHandler(response) {
                if ( response.message ) {
                    alert(response.message);
                } else {
                    jQuery('#securesubmit_token').val(response.token_value);
                    jQuery('#em-booking-submit').off('click');
                    jQuery('#em-booking-submit').click();
                }
            }

            jQuery(document).ready(function() {
                setTimeout(function () {
                    jQuery('#em-booking-submit').on('click', function() {
                        hps.tokenize({
                            data: {
                                public_key: '<?php echo get_option('em_'.$this->gateway.'_public_key'); ?>',
                                number: jQuery('#card_num').val(),
                                cvc: jQuery('#card_cvv').val(),
                                exp_month: jQuery('#exp_month').val(),
                                exp_year: jQuery('#exp_year').val()
                            },
                            success: function(response) {
                                secureSubmitResponseHandler(response);
                            },
                            error: function(response) {
                                secureSubmitResponseHandler(response);
                            }
                        });

                        return false;
                    });
                }, 300);
            });
        </script>

        <?php
    }

    /*
     * --------------------------------------------------
     * Gateway Settings Functions
     * --------------------------------------------------
     */

    /**
     * Outputs custom SecureSubmit setting fields in the settings page
     */
    function mysettings() {
        global $EM_options;
        ?>
        <table class="form-table">
        <tbody>
          <tr valign="top">
              <th scope="row"><?php _e('Success Message', 'em-pro') ?></th>
              <td>
                <input type="text" name="booking_feedback" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback" )); ?>" style='width: 40em;' /><br />
                <em><?php _e('The message that is shown to a user when a booking is successful and payment has been taken.','em-pro'); ?></em>
              </td>
          </tr>
          <tr valign="top">
              <th scope="row"><?php _e('Success Free Message', 'em-pro') ?></th>
              <td>
                <input type="text" name="booking_feedback_free" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_free" )); ?>" style='width: 40em;' /><br />
                <em><?php _e('If some cases if you allow a free ticket (e.g. pay at gate) as well as paid tickets, this message will be shown and the user will not be charged.','em-pro'); ?></em>
              </td>
          </tr>
        </tbody>
        </table>
        <h3><?php echo sprintf(__('%s Options','dbem'),'SecureSubmit')?></h3>
        <p style="font-style:italic;"><?php echo sprintf(__('Please visit the <a href="%s">SecureSubmit</a> portal for information on taking payments with Heartland.','em-pro'), 'https://developer.heartlandpaymentsystems.com/SecureSubmit/'); ?></p>
        <table class="form-table">
        <tbody>
            <tr valign="top">
                  <th scope="row"><?php _e('Public Key', 'emp-pro') ?></th>
                  <td><input type="text" name="public_key" value="<?php esc_attr_e(get_option( 'em_'. $this->gateway . "_public_key", "" )); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Secret key', 'emp-pro') ?></th>
                <td><input type="text" name="secret_key" value="<?php esc_attr_e(get_option( 'em_'. $this->gateway . "_secret_key", "" )); ?>" /></td>
            </tr>
            <tr><td colspan="2"><strong><?php echo sprintf(__( '%s Options', 'dbem' ),__('Advanced','em-pro')); ?></strong></td></tr>
            <tr>
                <th scope="row"><?php _e('Email Customer (on success)', 'emp-pro') ?></th>
                <td>
                    <select name="email_customer">
                        <?php $selected = get_option('em_'.$this->gateway.'_email_customer'); ?>
                        <option value="1" <?php echo ($selected) ? 'selected="selected"':''; ?>><?php _e('Yes','emp-pro'); ?></option>
                        <option value="0" <?php echo (!$selected) ? 'selected="selected"':''; ?>><?php _e('No','emp-pro'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Customer Receipt Email Header', 'emp-pro') ?></th>
                <td><input type="text" name="header_email_receipt" value="<?php esc_attr_e(get_option( 'em_'. $this->gateway . "_header_email_receipt", __("Thanks for your payment!", "emp-pro"))); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Customer Receipt Email Footer', 'emp-pro') ?></th>
                <td><input type="text" name="footer_email_receipt" value="<?php esc_attr_e(get_option( 'em_'. $this->gateway . "_footer_email_receipt", "" )); ?>" /></td>
            </tr>
          <tr valign="top">
              <th scope="row"><?php _e('Manually approve completed transactions?', 'em-pro') ?></th>
              <td>
                <input type="checkbox" name="manual_approval" value="1" <?php echo (get_option('em_'. $this->gateway . "_manual_approval" )) ? 'checked="checked"':''; ?> /><br />
                <em><?php _e('By default, when someone pays for a booking, it gets automatically approved once the payment is confirmed. If you would like to manually verify and approve bookings, tick this box.','em-pro'); ?></em><br />
                <em><?php echo sprintf(__('Approvals must also be required for all bookings in your <a href="%s">settings</a> for this to work properly.','em-pro'),EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></em>
              </td>
          </tr>
        </tbody>
        </table>
        <?php
    }

    /*
     * Run when saving settings, saves the settings available in EM_Gateway_SecureSubmit::mysettings()
     */
    function update() {
        parent::update();
        $gateway_options = array(
            $this->gateway . "_public_key" => $_REQUEST[ 'public_key' ],
            $this->gateway . "_secret_key" => $_REQUEST[ 'secret_key' ],
            $this->gateway . "_email_customer" => ($_REQUEST[ 'email_customer' ]),
            $this->gateway . "_header_email_receipt" => $_REQUEST[ 'header_email_receipt' ],
            $this->gateway . "_footer_email_receipt" => $_REQUEST[ 'footer_email_receipt' ],
            $this->gateway . "_manual_approval" => $_REQUEST[ 'manual_approval' ],
            $this->gateway . "_booking_feedback" => wp_kses_data($_REQUEST[ 'booking_feedback' ]),
            $this->gateway . "_booking_feedback_free" => wp_kses_data($_REQUEST[ 'booking_feedback_free' ])
        );
        foreach($gateway_options as $key=>$option){
            update_option('em_'.$key, stripslashes($option));
        }
        //default action is to return true
        return true;

    }
    /**
     * Add extra placeholders to the booking email. Called by em_booking_output_placeholder filter, added in this object __construct() function.
     *
     * @param string $result
     * @param EM_Booking $EM_Booking
     * @param string $placeholder
     * @param string $target
     * @return string
     */
    public function em_booking_output_placeholder( $result, $EM_Booking, $placeholder, $target='html' ) {
        /* hook to add extra placeholders if needed */
        do_action('em_securesubmit_booking_output_placeholder', $result, $EM_Booking, $placeholder, $target);

        return parent::em_booking_output_placeholder($result, $EM_Booking, $placeholder, $target);
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        $domain = $this->plugin_slug;
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    }

    /*
     * --------------------------------------------------
     * SecureSubmit Functions - functions specific to securesubmit payments
     * --------------------------------------------------
     */

    /**
     * Retreive the securesubmit vars needed to send to the gateway to proceed with payment
     * @param EM_Booking $EM_Booking
     */
    function authorize_and_capture($EM_Booking){
        global $EM_Notices;

        if( !class_exists('HpsServicesConfig') ){
            require_once('hps/Hps.php');
        }

        $amount = $EM_Booking->get_price(false, false, true);

        $config = new HpsServicesConfig();

        $config->secretApiKey = get_option('em_'.$this->gateway.'_secret_key');
        $config->versionNumber = '1740';
        $config->developerId = '002914';

        $creditService = new HpsCreditService($config);

        $hpsaddress = new HpsAddress();
        if( EM_Gateways::get_customer_field('address', $EM_Booking) != '' ) $hpsaddress->address = EM_Gateways::get_customer_field('address', $EM_Booking);
        if( EM_Gateways::get_customer_field('city', $EM_Booking) != '' ) $hpsaddress->city = EM_Gateways::get_customer_field('city', $EM_Booking);
        if( EM_Gateways::get_customer_field('state', $EM_Booking) != '' ) $hpsaddress->state = EM_Gateways::get_customer_field('state', $EM_Booking);
        if( EM_Gateways::get_customer_field('zip', $EM_Booking) != '' ) $hpsaddress->zip = EM_Gateways::get_customer_field('zip', $EM_Booking);
        if( EM_Gateways::get_customer_field('country', $EM_Booking) != '' ){
            $countries = em_get_countries();
            $hpsaddress->country = $countries[EM_Gateways::get_customer_field('country', $EM_Booking)];
        }

        $names = explode(' ', $EM_Booking->get_person()->get_name());

        $cardHolder = new HpsCardHolder();
        if( !empty($names[0]) ) $cardHolder->firstName = array_shift($names);
        if( implode(' ',$names) != '' ) $cardHolder->lastName = implode(' ',$names);
        if( EM_Gateways::get_customer_field('phone', $EM_Booking) != '' ) $cardHolder->phone = EM_Gateways::get_customer_field('phone', $EM_Booking);
        $cardHolder->email = $EM_Booking->get_person()->user_email;
        $cardHolder->address = $hpsaddress;

        $hpstoken = new HpsTokenData();
        $hpstoken->tokenValue = $_REQUEST['securesubmit_token'];

        $details = new HpsTransactionDetails();
        $details->invoiceNumber = $EM_Booking->booking_id;
        $details->memo = preg_replace('/[^a-zA-Z0-9\s]/i', "", $EM_Booking->get_event()->event_name);

        try {
            $response = $creditService->charge($amount, 'usd', $hpstoken, $cardHolder, false, null);

            $EM_Booking->booking_meta[$this->gateway] = array('txn_id'=>$response->transactionId, 'amount' => $amount);
            $this->record_transaction($EM_Booking, $amount, 'USD', date('Y-m-d H:i:s', current_time('timestamp')), $response->transactionId, 'Completed', '');
            $result = true;
        } catch (HpsException $e) {
            $EM_Booking->add_error($e->getMessage());
            $result = false;
        }

        //Return transaction_id or false
        return apply_filters('em_gateway_securesubmit_authorize', $result, $EM_Booking, $this);
    }

    /**
     * Void a booking
     *
     * @since    1.0.0
     *
     * @param EM_Booking $EM_Booking
     */
    private function __void($EM_Booking) {
        global $wpdb;

        if ($EM_Booking->booking_meta['gateway'] == $this->gateway) {
            $sql = $wpdb->prepare("DELETE
                                     FROM " . EM_BOOKINGS_TABLE . "
                                    WHERE booking_id=%d", $EM_Booking->booking_id);

            $result = $wpdb->query($sql);
            if ($result !== false) {
                //delete the tickets
                $EM_Booking->get_tickets_bookings()->delete();
                $EM_Booking->previous_status = $EM_Booking->booking_status;
                $EM_Booking->booking_status = false;
                $EM_Booking->feedback_message = sprintf(__('%s deleted', 'dbem'), __('Booking', 'dbem'));
            } else
                $EM_Booking->add_error(sprintf(__('%s could not be deleted', 'dbem'), __('Booking', 'dbem')));
        }
    }
}

/* eof */

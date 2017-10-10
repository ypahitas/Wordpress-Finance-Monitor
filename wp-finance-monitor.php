<?php
/*
Plugin Name: Wordpress Finance monitor 
Plugin URI:  
Description: Monitors a portfolio of stocks and sends reports and alerts
Version:     20160911
Author:      Yiannis Pahitas
Author URI:  http://YiannisPahitas.com
License:    
License URI: 
Text Domain: 
Domain Path: 
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define( 'FINANCEMONITOR__PLUGIN_DIR',plugin_dir_path( __FILE__ ));
require_once( FINANCEMONITOR__PLUGIN_DIR . 'class.financeMonitor.php');
require_once( FINANCEMONITOR__PLUGIN_DIR . 'class.financeMonitorPageReport.php');

register_activation_hook( __FILE__, array( 'FinanceMonitor', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FinanceMonitor', 'deactivate' ) );

//Runs every day according to wp Cron
add_action( 'my_daily_event', array( 'FinanceMonitor', 'HandleDebugLog') );
add_action( 'my_daily_event', array( 'FinanceMonitor', 'MonitorPortfolio') );

//run plugin everytime page loads
//add_action( 'wp', array( 'FinanceMonitor', 'MonitorPortfolio'));

//change email from address
add_filter( 'wp_mail_from', 'plugin_mail_from' );
add_filter( 'wp_mail_from_name', 'plugin_mail_name' );
function plugin_mail_name( $email ){
    return 'Finance Monitor'; // new email name from sender.
  }
  function plugin_mail_from ($email ){
    return 'yiannis@yiannispahitas.com'; // new email address from sender.
  }

  //Register Shortcode so that Report and alert can be shown on page.
  add_shortcode( 'DisplayFinanceMonitor',  array( 'financeMonitorPageReport', 'DisplayFinanceMonitor') );

?>
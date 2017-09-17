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

register_activation_hook( __FILE__, array( 'FinanceMonitor', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FinanceMonitor', 'deactivate' ) );

//Runs every day according to wp Cron
add_action( 'my_daily_event', array( 'FinanceMonitor', 'monitorPortfolio') );

?>
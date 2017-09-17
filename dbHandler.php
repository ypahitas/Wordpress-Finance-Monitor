<?php 
/*
Class file that handles all exchanges with the wordpress database
*/
class DBHandler{

    private $logger;

    //Checks to see if database already includes tables
    //If not, creates them. Tables are hardocded
    public function CreateDB() { 
        global $wpdb,$logger;
        $this->InitialiseVars();
        $logger->write_log ("Creating Database");

        $tablefinanceMonitorName = $wpdb->prefix . "financeMonitor";
        $tablefinanceMonitorStockDataName = $wpdb->prefix . "financeMonitorStockData";        

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );        
        
        $charset_collate = $wpdb->get_charset_collate();

        $tablefinanceMonitorStatement = "CREATE TABLE IF NOT EXISTS $tablefinanceMonitorName (
        ID int NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        keyEntry varchar(255),
        value varchar(255),
        PRIMARY KEY  (id)
        ) $charset_collate;";

        $tablefinanceMonitorStockDataStatement = "CREATE TABLE IF NOT EXISTS $tablefinanceMonitorStockDataName (
        ID int NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        stockSymbol varchar(255),
        price decimal,
        numberOfStocks int,
        PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta( $tablefinanceMonitorStatement );
        dbDelta( $tablefinanceMonitorStockDataStatement );
    }
    //Call this method to insert into the database the last executed date
    public function getLastExecuted(){
        global $wpdb,$logger;
        $this->InitialiseVars();
        $logger->write_log ("Getting last Executed");

        $tablefinanceMonitorName = $wpdb->prefix . "financeMonitor";
        $tablefinanceMonitorStockDataName = $wpdb->prefix . "financeMonitorStockData"; 
        
        $query ="SELECT value FROM $tablefinanceMonitorName WHERE keyEntry = 'lastExecuted' ORDER BY id DESC LIMIT 1";
        $retVal = $wpdb->get_var($query);
        $logger->write_log ("Last Executed - ".$retVal);
        return $retVal;
    }
    //Call this method to insert into the database the last executed date
    public function setLastExecuted($format){
        global $wpdb,$logger;
        $this->InitialiseVars();
        $logger->write_log ("Setting last Executed");

        $tablefinanceMonitorName = $wpdb->prefix . "financeMonitor";
        $tablefinanceMonitorStockDataName = $wpdb->prefix . "financeMonitorStockData"; 
        //First delete previous lastExecuted
        $wpdb->delete( 
            $tablefinanceMonitorName, 
            array( 
                "keyEntry"=>"lastExecuted"
            )
        );
        $wpdb->insert( 
            $tablefinanceMonitorName, 
            array( 
                'keyEntry' => "lastExecuted", 
                'value' => current_time( $format )                
            )
        ); 
    }

    private function InitialiseVars()
    {
        global $logger;
       require_once( FINANCEMONITOR__PLUGIN_DIR . 'Logger.php');
       $logger = new Logger();
    }
}


?>
<?php

class financeMonitorPageReport{
		
	protected $logger;
	protected $dbHandler;	

	public static function DisplayFinanceMonitor() { 
		$financeMonitorPageReport = new financeMonitorPageReport();
		return $financeMonitorPageReport->Display();
	}
	
	//Triggers the finance monitors and displays results
	public function Display(){
		require_once( FINANCEMONITOR__PLUGIN_DIR . 'class.financeMonitor.php');
		require_once( FINANCEMONITOR__PLUGIN_DIR . 'Logger.php');
		require_once( FINANCEMONITOR__PLUGIN_DIR . 'dbHandler.php');

		$logger = new Logger("/logs");
		$dbHandler = new DBHandler($logger);

		$logger->write_log("Displaying Report");
		
		//Run monitor
		$financeMonitor = new financeMonitor();
		$financeMonitor->PerformMonitoring();

		//Read the results
		$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";
		$reportFile = FINANCEMONITOR__PLUGIN_DIR .  "report/report.json";

		//get last executed 
		$lastExecuted = $dbHandler->getLastExecutedMonitoring();
		
		//read files and body - reports and alerts
		$reports = file_get_contents($reportFile);				
		$alerts = file_get_contents($alertFile);

		//read html template and evaluate with variables
		$templatefile =  FINANCEMONITOR__PLUGIN_DIR . "ReportTemplate.html";			
		$template = file_get_contents($templatefile);
		eval("\$message = \"$template\";");			
		return $message;
	}
}

?>
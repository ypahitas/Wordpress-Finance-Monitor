<?php

class financeMonitor{
		
	protected $logger;
	protected $dbHandler;	

	public static function activate() {
		require_once( FINANCEMONITOR__PLUGIN_DIR . 'Logger.php');
		require_once( FINANCEMONITOR__PLUGIN_DIR . 'dbHandler.php');

		$logger = new Logger();
		$logger->write_log("Activating Plugin");

		$dbHandler = new DBHandler($logger);

		//Assign to the scheduler to execute periodically
		wp_schedule_event( time(), 'daily', 'my_daily_event' );
		wp_schedule_event( time(), 'hourly', 'my_hourly_event' );
		//Create Database tables

		$dbHandler->CreateDB();
	} 
	
	public static function deactivate() {
		wp_clear_scheduled_hook('my_daily_event');		
		//remove_action( 'wp', array( 'FinanceMonitor', 'MonitorPortfolio'));
	} 

	//If the Debug log becomes too large, rename it to avoid issues
	public static function HandleDebugLog(){
		$debugLogPath=WP_CONTENT_DIR . '/debug.log';
		$backUpFolderPath=WP_CONTENT_DIR .'/DebugBackUp';
		//Set a Max size of arbitary 20MB
		$fileSize =filesize ($debugLogPath);
		if($fileSize!=false && $fileSize>20000000 ){
			if (!file_exists($backUpFolderPath)) {
				mkdir($backUpFolderPath);
			}
			rename($debugLogPath, $backUpFolderPath.date("Y-m-d")."log");
		}
	}
	public static function MonitorPortfolio() { 
		$financeMonitor = new financeMonitor();
		$financeMonitor->PerformMonitoring();
	}
	public static function MonitorThresholds() { 
		$financeMonitor = new financeMonitor();
		$financeMonitor->PerformThresholdMonitoring();
	}
	//Run procedure that:
	//Only executes once a month
	//Gets current prices
	//updates database
	//compares with last month/year etc
	//creates alerts if certain thresholds are exceeded
	public function PerformMonitoring(){
		global $logger;
		try{

			require_once( FINANCEMONITOR__PLUGIN_DIR . 'Logger.php');
			require_once( FINANCEMONITOR__PLUGIN_DIR . 'dbHandler.php');

			$logger = new Logger("/logs");
			$dbHandler = new DBHandler($logger);
			
			$logger->write_log("Monitor Portfolio");

			$portfolioDBName="portfolio";
			//Alerts settings
			//Threshold which will trigger alert
			$mtdThreshold =-0.1;
			$ytdThreshold =-0.1;
			$lastFiveAverageThreshold =-0.1;

			//Clear alerts and reports
			$this->resetAlerts();
			$this->resetReport();


			$stocksConfigFile = FINANCEMONITOR__PLUGIN_DIR ."StocksConfiguration.json";				
			$stockArray = json_decode(file_get_contents($stocksConfigFile));
			$logger->write_log($stockArray[0]->symbol);
			//The next 2 variables will be used to calculate total portfolio ROI
			$totalInitialCost=0;
			$totalCurrentValue	=0;

			foreach ($stockArray as $stock) {
				//get stock and compare return
				$currentPrice = $this->getStockPrice($stock->symbol);
				$currentValue = $currentPrice * $stock->NumberOfStocks;
				$ROI = ($currentValue - $stock->TotalCost)/$stock->TotalCost;
				$formattedPercentageROI = number_format ($ROI*100,2);
				//Adding to the overall portfolio value
				$totalInitialCost+=$stock->TotalCost;
				$totalCurrentValue+=$currentValue;

				//Add stock ROI to report
				$this->addReport($stock->symbol." Return since Buy: ". $formattedPercentageROI ."%");
				//Compare month to date
				$mtd = $dbHandler->getLastMonthPrice($stock->symbol);
				if($mtd !== -1 && $this->ChangeAlert($stock->TotalCost,$mtd,$mtdThreshold)){
					$this->addAlert("Month to Date Alert for ".$stock->symbol);
				}
				//Compare year to date
				$ytd = $dbHandler->getLastYearPrice($stock->symbol);
				if($ytd !== -1 && $this->ChangeAlert($stock->TotalCost,$ytd,$ytdThreshold)){
					$this->addAlert("Year to Date Alert for ".$stock->symbol);
				}
				//Compare moving average
				$lastFivePrices=$dbHandler->getLastNPrices($stock->symbol,50);
				$movingAverage = array_sum($lastFivePrices) /count($lastFivePrices);
				if($this->ChangeAlert($stock->TotalCost,$movingAverage,$lastFiveAverageThreshold)){
					$this->addAlert("Moving average Alert for ".$stock->symbol);
				}
				//Add new price to DB
				$dbHandler->setStockPrice($stock->symbol,$currentPrice,$stock->NumberOfStocks,"Y-m-d");
			}
			//Portfolio monitoring
			$ROI = ($totalCurrentValue - $totalInitialCost)/$totalInitialCost;
			$formattedPercentageROI = number_format ($ROI*100,2);
			//Add portfolio ROI to report
			$this->addReport("Portfolio return: ". $formattedPercentageROI ."%");
			//Compare month to date - Porfolio threshold lower
			$mtd = $dbHandler->getLastMonthPrice($portfolioDBName);
			if($mtd !== -1 && $this->ChangeAlert($totalInitialCost,$mtd,$mtdThreshold/2)){
				$this->addAlert("Month to Date Alert for ".$portfolioDBName);
			}
			//Compare year to date  - Porfolio threshold lower
			$ytd = $dbHandler->getLastYearPrice($portfolioDBName);
			if($ytd !== -1 && $this->ChangeAlert($totalInitialCost,$ytd,$ytdThreshold/2)){
				$this->addAlert("Year to Date Alert for ".$portfolioDBName);
			}
			//Compare moving average - Porfolio threshold lower
			$lastFivePrices=$dbHandler->getLastNPrices($portfolioDBName,50);
			$movingAverage = array_sum($lastFivePrices) /count($lastFivePrices);
			if($this->ChangeAlert($totalInitialCost,$movingAverage,$lastFiveAverageThreshold/2)){
				$this->addAlert("Moving average Alert for ".$portfolioDBName);
			}
			//Add to DB
			$dbHandler->setStockPrice($portfolioDBName,$totalCurrentValue,1,"Y-m-d");

			//set last executed monitoring
			$dbHandler->setLastExecutedMonitoring("Y-m-d");
		}
		catch(Exception $e){
			//log exception and add alert
			$logger->write_log ($e->getMessage());
			$this->addAlert($e->getMessage());
			//log stack trace
			$logger->write_log(json_encode($e->getTrace()));
		}
		finally{
			//If executed less than a month ago skip sending email UNLESS there are alerts
			$lastExecuted = new DateTime($dbHandler->getLastExecutedEmail());
			$interval = date_diff($lastExecuted, new DateTime("now"));
			//if there are alerts, send email anw
			$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";			
			$alerts = file_get_contents($alertFile);
			
			$intervalFormat = $interval->format("%m");
			$logger->write_log ("InterVal since last executed: ".$intervalFormat);
			
			if($intervalFormat > 1 || $alerts !='[]'){
				$this->sendMail('AddYourEmailAddress');
				//set last executed
				$dbHandler->setLastExecutedEmail("Y-m-d");
			}			
			else{
				$logger->write_log ("Sent email within the last month, will not send now");
			}
		}
	}

	//Run procedure that:
	//sends alerts if certain thresholds are exceeded/missed
	public function PerformThresholdMonitoring(){
		global $logger;
		try{

			require_once( FINANCEMONITOR__PLUGIN_DIR . 'Logger.php');
			require_once( FINANCEMONITOR__PLUGIN_DIR . 'dbHandler.php');

			$logger = new Logger("/logs");
			$dbHandler = new DBHandler($logger);
			
			$logger->write_log("Monitor Thresholds");

			//Clear alerts and reports
			$this->resetAlerts();
			$this->resetReport();


			$alertsConfigFile = FINANCEMONITOR__PLUGIN_DIR ."AlertsConfiguration.json";				
			$stockArray = json_decode(file_get_contents($alertsConfigFile));
			$logger->write_log($stockArray[0]->symbol);

			foreach ($stockArray as $stock) {
				//get stock and compare return
				$currentPrice = $this->getStockPrice($stock->symbol);
				
				//Compare to threshold. 
				if($stock->UpOrDown=== 'up' && $currentPrice > $stock->threshold){
					$this->addAlert("Price exceeded threshold Alert for ".$stock->symbol);
				}
				else{
					$this->addAlert("Price dropped bellow threshold Alert for ".$stock->symbol);
				}
			}
		}
		catch(Exception $e){
			//log exception and add alert
			$logger->write_log ($e->getMessage());
			$this->addAlert($e->getMessage());
			//log stack trace
			$logger->write_log(json_encode($e->getTrace()));
		}
		finally{
			//if there are alerts, send email anw
			$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";			
			$alerts = file_get_contents($alertFile);
			
			$intervalFormat = $interval->format("%m");
			$logger->write_log ("InterVal since last executed: ".$intervalFormat);
			
			if($alerts !='[]'){
				$this->sendMail('AddYourEmailAddress');
			}			
			else{
				$logger->write_log ("Alerts empty not sending anything");
			}
		}
	}
	//Remove all alerts from the alert file before monitoring begins
	function resetAlerts(){
		global $logger;
		$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";
		$logger->write_log ("Resetting alerts");
		//read file
		$jsonArray = json_decode(file_get_contents($alertFile ),TRUE);		
		//new empty array
		$jsonArray = array();
		//save data
		file_put_contents($alertFile , json_encode($jsonArray));
	}
	
	//Add a new alert to the alert file
	function addAlert($alert){
		global $logger;
		$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";
		$logger->write_log ("Adding Alert");
		//read file
		$jsonArray = json_decode(file_get_contents($alertFile ),TRUE);		
		
		//add new element
		array_push ( $jsonArray, $alert );

		//save data
		file_put_contents($alertFile , json_encode($jsonArray));
	}

	//Remove all reports from the report file before monitoring begins
	function resetReport(){
		global $logger;
		$reportFile =  FINANCEMONITOR__PLUGIN_DIR . "report/report.json";
		$logger->write_log ("Reset Report");
		//read file
		$jsonArray = json_decode(file_get_contents($reportFile ),TRUE);		
		//new empty array
		$jsonArray = array();
		//save data
		file_put_contents($reportFile , json_encode($jsonArray));
	}
	
	//Add a new report to the report file
	function addReport($report){
		global $logger;
		$reportFile =  FINANCEMONITOR__PLUGIN_DIR . "report/report.json";
		$logger->write_log ("Adding Report");
		//read file
		$jsonArray = json_decode(file_get_contents($reportFile ),TRUE);		
		
		//add new element
		array_push ( $jsonArray, $report );

		//save data
		file_put_contents($reportFile , json_encode($jsonArray));
	}

	//Send email with reports and alerts
	function sendMail($emailAddress){
		global $logger;
		$alertFile =  FINANCEMONITOR__PLUGIN_DIR . "report/alerts.json";
		$reportFile = FINANCEMONITOR__PLUGIN_DIR .  "report/report.json";
		$logger->write_log ("Sending email");
		//read files and construct email body
		$reports = file_get_contents($reportFile);				
		$alerts = file_get_contents($alertFile);
		$message = $reports . "\r\n" . $alerts;
		//Only send the email if there is something to report
		if($reports !='' or $alerts !=''){
			$sent_message = wp_mail(array($emailAddress), "finance Monitor", $message);
			
			//log message based on the result.
			if ( $sent_message ) {
				// The message was sent.
				$logger->write_log("Email was sent");
			} else {
				// The message was not sent.
				$logger->write_log("Email was sent");
			}
		}else{
			$logger->write_log ("Empty email, will not send.");
		}	

	}
	//Queries the Yahoo API to get the last closing price
	function getStockPrice($stockSymbol){
		global $logger;
		$logger->write_log ("Get Stock Price - ".$stockSymbol);
		
		$webRequest ="https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quotes%20where%20symbol%20in%20(%22"
			. $stockSymbol
			."%22)&format=json&diagnostics=false&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=";
		
		$logger->write_log ($webRequest);

		$jsonResponse = file_get_contents($webRequest);		
		$logger->write_log ($jsonResponse);

		$jsonResponseArray = json_decode($jsonResponse,TRUE);		

		$price = $jsonResponseArray["query"]["results"]["quote"]["LastTradePriceOnly"];
		$logger->write_log ("Price retrieved - ".$price);
		return $price;
	}
	//Compares the current price against the old price to see if it exceeds threshold
	//Threshold should be a negative one
	//Returns True if an alert should be added
	//False otherwise
	//If any of the input parameters are empty, it will generate an alert - Disred outcome
	function ChangeAlert($currentPrice,$oldPrice,$threshold){
		global $logger;
		$logger->write_log ("Comparing change: ".$currentPrice.",".$oldPrice.",".$threshold);
		$change= ($currentPrice - $oldPrice)/$oldPrice;
		if($change < $threshold){
			return true;
		}
		else{
			return false;
		}		
	}
}

?>
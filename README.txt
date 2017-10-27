Finance monitor tool by Yiannis Pahitas

Objective:
Monitor a portfolio and send periodical reports of performance
Monitor a portfolio and send alerts for given thresholds
Allow realtime display via webpage
Set up threshold prices for certain stocks that will send a warning

Architecture:
Run as a Wordpress plugin
Use yahoo finance api
	https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quotes%20where%20symbol%20in%20(%22CSSPX.MI%22)&format=json&diagnostics=true&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=
	select * from yahoo.finance.historicaldata where symbol = "YHOO" and startDate = "2009-09-11" and endDate = "2010-03-10"
Utilise WP cron to schedule jobs to occur periodically
	https://stackoverflow.com/questions/33762269/how-to-add-cron-to-wordpress
	https://tommcfarlin.com/wordpress-cron-jobs/
Load configuration of thresholds, stocks to monitor etc
Use WP database to store configuration, past database

StocksConfiguration json file
    Need to add an array of stocks
    Each stock element should include date of buy and total cost
    Currently only EUR values are supported. Stock value from Yahoo should also be EUR 
    Symbol (Mandatory) - the symbol as it appears on yahoo finance
    Description: short description of the stock
    NumberOfStocks (Mandatory) - integer value of number of stocks bought
    TotalCost (Mandatory) - total cost to buy them - i.e. price + fees etc

AlertsConfiguration.json file
    Need to add an array of stocks
    Threshold value denotes the value that if you cross an alert is generated
    UpOrDown configures whether the alert occurs whether the price is higher or lower than threshold. Down (default) means that alert is generated if price drops bellow threshold

To display on a webpage use the shortcode [DisplayFinanceMonitor]

Source code on:
    https://github.com/ypahitas/Wordpress-Finance-Monitor

<?php
// Code adapted from biteme post http://forum.synology.com/enu/viewtopic.php?f=10&t=41181&start=30

define('DEBUG_TO_FILE', false);
define("DEBUG_FILE", "/tmp/openload.log");

class SynoFileHostingOpenload {   
private $Url;
private $Username;
private $Password;
private $HostInfo;
private $OPENLOAD_API_URL = 'https://api.openload.io/1/';

public function __construct($Url, $Username, $Password, $HostInfo) {
	$this->Url = $Url;
	// We don't care about the other data
}
  
private function downloadJSON($JsonUrl) {
	$returnObject = NULL;

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, DOWNLOAD_TIMEOUT);
	curl_setopt($curl, CURLOPT_TIMEOUT, DOWNLOAD_TIMEOUT);
	curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
	curl_setopt($curl, CURL_OPTION_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_URL, $this->OPENLOAD_API_URL . $JsonUrl);

	if (($json = curl_exec($curl)) !== FALSE) {
		$this->debug("Response: ", $json);
		$returnObject = @json_decode($json);
	}

	curl_close($curl);
	return $returnObject;
}

//This function returns download url.
public function GetDownloadInfo() {
	$DownloadInfo = array(); // result
	$this->debug("Start: ", $this->Url);

	preg_match('/openload.io\/f\/(.+)\//', $this->Url, $matches);

	if(empty($matches[1])) {
		$DownloadInfo[DOWNLOAD_ERROR] = ERR_NOT_SUPPORT_TYPE;
		return $DownloadInfo;
	}
	$dlFile = $matches[1];

	// Get Download Ticket
	$TicketData = $this->downloadJSON('file/dlticket?file=' . $dlFile);

	if($TicketData->status != 200) {
		if ($TicketData->status == 404) {
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		} else {
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
		}
		return $DownloadInfo;
	}

	$this->debug("Ticket Data: ", $TicketData);

	// Captcha is not supported
	if(!empty($TicketData->result->captcha_url)){
		$DownloadInfo[DOWNLOAD_ERROR] = ERR_NOT_SUPPORT_TYPE;
		return $DownloadInfo;
	}

	// Wait if needed
	if(!empty($TicketData->result->wait_time) && ($TicketData->result->wait_time > 0)){
		sleep($TicketData->result->wait_time);
	}

	if(empty($TicketData->result->ticket)){
		// No ticket, no download
		$DownloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
		return $DownloadInfo;
	}
	$Ticket = $TicketData->result->ticket;

	// Get Download Link
	$dlData = $this->downloadJSON('file/dl?file=' . $dlFile . '&ticket=' . $Ticket);

	$this->debug("DL Data: ", $dlData);

	if($dlData->status != 200) {
		if ($dlData->status == 404) {
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		} else {
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
		}
		return $DownloadInfo;
	}

	$DownloadInfo[DOWNLOAD_URL] = $dlData->result->url;

	$this->debug("URL: ", $DownloadInfo[DOWNLOAD_URL]);
	return $DownloadInfo;
}


/* 
Always free account type
*/ 
public function Verify($ClearCookie) {
	return USER_IS_FREE;
}

public function IsPremiumAccount() {
	return FALSE;
}

private function debug($Header, $Value) {
	$msg = $Header . print_r($Value, TRUE) . "\n";   
	if (DEBUG_TO_FILE) {
		$msg = date("n-d H:i:s") . " " . $msg;
		file_put_contents(DEBUG_FILE, $msg, FILE_APPEND);   
	}
}

} 
?>
<?php

/**
 * Class for basic error logging in corrections
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 16 Dec 2015
 * @license AGPL-3.0
 */
class CRM_Corrections_ErrorLogger {
  private $logFile = null;
  function __construct($fileName = 'pum_corrections_errorlog') {
    $config = CRM_Core_Config::singleton();
    $runDate = new DateTime('now');
    $fileName = $config->configAndLogDir.$fileName."_".$runDate->format('YmdHis');
    $this->logFile = fopen($fileName, 'w');
  }

  public function logMessage($type, $message) {
    $this->addMessage($type, $message);
  }

  /**
   * Method to log the message
   *
   * @param $type
   * @param $message
   */
  private function addMessage($type, $message) {
    fputs($this->logFile, date('Y-m-d h:i:s'));
    fputs($this->logFile, ' ');
    fputs($this->logFile, $type);
    fputs($this->logFile, ' ');
    fputs($this->logFile, $message);
    fputs($this->logFile, "\n");
  }
}
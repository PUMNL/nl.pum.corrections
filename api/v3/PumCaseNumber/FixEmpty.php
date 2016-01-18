<?php

/**
 * PumCaseNumber.FixEmpty API
 * one time correction job to generate PUM Case Numbers for cases that do not have one
 * see http://redmine.pum.nl/issues/3122
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_pum_case_number_fixempty($params) {
  $returnValues = array();
  $validCaseTypes = _getValidCaseTypes();
  $validCaseTypeIds = array();
  foreach ($validCaseTypes as $caseTypeName => $caseTypeId) {
    $validCaseTypeIds[] = $caseTypeId;
  }
  $query = "SELECT cc.id AS caseId, cc.subject AS caseSubject, cc.case_type_id AS caseTypeId
    FROM civicrm_case cc LEFT JOIN civicrm_case_pum pum ON cc.id = pum.entity_id
    WHERE pum.case_sequence IS NULL AND cc.case_type_id IN('".implode("','", $validCaseTypeIds)."')";
  $dao = CRM_Core_DAO::executeQuery($query);
  while ($dao->fetch()) {
    _generatePumCaseNumber($dao);
    $returnValues[] = "PUM Case Number generated for case ".$dao->caseId." (".$dao->caseSubject.")";
  }

  return civicrm_api3_create_success($returnValues, $params, 'PumCaseNumber', 'FixEmpty');
}

/**
 * Function to generate Pum Case Data for case
 *
 * @param $dao
 */
function _generatePumCaseNumber($dao) {
  $pumCaseSequence = CRM_Sequence_Page_PumSequence::nextval('main_activity');
  $pumCaseCountry = _generatePumCaseCountry($dao->caseId);
  $pumCaseType = _generatePumCaseType($dao);

  if (_pumCaseRecordExists($dao->caseId) == TRUE) {
    $qry = "UPDATE civicrm_case_pum SET case_sequence = %1, case_type = %2, case_country = %3 WHERE entity_id = %4";
  } else {
    $qry = "INSERT INTO civicrm_case_pum SET case_sequence = %1, case_type = %2, case_country = %3, entity_id = %4";
  }
  $qryParams = array(
    1 => array($pumCaseSequence, "Integer"),
    2 => array($pumCaseType, "String"),
    3 => array($pumCaseCountry, "String"),
    4 => array($dao->caseId, 'Integer'));
  CRM_Core_DAO::executeQuery($qry, $qryParams);
}

/**
 * Function to determine if pum case record exists
 *
 * @param $caseId
 * @return bool
 */
function _pumCaseRecordExists($caseId) {
  $qry = "SELECT COUNT(*) AS caseCount FROM civicrm_case_pum WHERE entity_id = %1";
  $params = array(1 => array($caseId, "Integer"));
  $caseCount = CRM_Core_DAO::singleValueQuery($qry, $params);
  if ($caseCount > 0) {
    return TRUE;
  } else {
    return FALSE;
  }
}
/**
 * Function to generate PUM Case Type Code
 * @param $dao
 * @return array|string
 * @throws CiviCRM_API3_Exception
 */
function _generatePumCaseType($dao) {
  $validCaseTypes = _getValidCaseTypes();
  foreach ($validCaseTypes as $caseTypeName => $caseTypeId) {
    if ($caseTypeId == $dao->caseTypeId) {
      $optionGroupId = civicrm_api3("OptionGroup", "Getvalue", array('name' => "case_type_code", 'return' => "id"));
      $optionValue = civicrm_api3("OptionValue", "Getvalue", array(
        'option_group_id' => $optionGroupId,
        'label' => $caseTypeName,
        'return' => "value"));
      return $optionValue;
    }
  }
  return "";
}

/**
 * Function to generate PUM Case Country for case
 *
 * @param $caseId
 * @return string
 * @throws CiviCRM_API3_Exception
 */
function _generatePumCaseCountry($caseId) {
  $caseClientId = CRM_Threepeas_Utils::getCaseClientId($caseId);
  $contact = civicrm_api3("Contact", "Getsingle", array('id' => $caseClientId));
  if ($contact['country_id']) {
    $country = civicrm_api3("Country", "Getsingle", array('id' => $contact['country_id']));
    return $country['iso_code'];
  }
  return "";
}

/**
 * Function to get valid case type ids
 *
 * @return array
 */
function _getValidCaseTypes() {
  $validCaseTypeIds = array();
  $validCaseTypeNames = array("Advice", "Business", "CTM", "Grant", "PDV", "RemoteCoaching", "Seminar");
  foreach ($validCaseTypeNames as $name) {
    $caseType = CRM_Threepeas_Utils::getCaseTypeWithName($name);
    $validCaseTypeIds[$name] = CRM_Core_DAO::VALUE_SEPARATOR.$caseType['value'].CRM_Core_DAO::VALUE_SEPARATOR;
  }
  return $validCaseTypeIds;
}

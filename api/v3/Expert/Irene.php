<?php
/**
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 6 April 2017
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Expert.Irene
 * Generate one time file for Irene Clarijs <irene.clarijs@pum.nl>
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_expert_irene($params) {
  _defineConstants();
  $returnValues = array();
  $expert = CRM_Core_DAO::executeQuery("SELECT * FROM overzicht_irene WHERE contact_id NOT IN(SELECT expert_id 
    FROM result_irene) ORDER BY contact_id LIMIT 1000");
  if ($expert->N == 0) {
    $returnValues = array('Alle experts verwerkt!');
  } else {
    $maxGroupFields = 0;
    while ($expert->fetch()) {
      $returnValues[] = 'Expert '.$expert->contact_id.' wordt verwerkt';
      if (_expertInExcludedGroups($expert->contact_id) == FALSE) {
        // count number of main activities for expert
        _getGroups($expert->contact_id, $expert, $maxGroupFields);
        $expert->procus_count = CRM_Threepeas_BAO_PumCaseRelation::getExpertNumberOfCases($expert->contact_id);
        // get most recent main activity (FALSE if none found)
        $mostRecentMain = _getRecentMain($expert->contact_id);
        // get latest prins missie
        $latestPrins = _getLatestPrins($expert->contact_id);
        _writeResult($expert, $mostRecentMain, $latestPrins, $maxGroupFields);
      }
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'Expert', 'Irene');
}

/**
 * Function to add record to result_irene
 *
 * @param $expert
 * @param $mostRecentMain
 * @param $latestPrins
 * @param $maxGroupFields
 */
function _writeResult($expert, $mostRecentMain, $latestPrins, $maxGroupFields) {
  // determine if expert is older than 71
  if ($expert->age > 71) {
    $expert71 = 1;
  } else {
    $expert71 = 0;
  }
  if (is_null($expert->expert_status)) {
    $expert->expert_status = '';
  }
  if (is_null($expert->sector_coordinator)) {
    $expert->sector_coordinator = '';
  }
  if (is_null($expert->gender)) {
    $expert->gender = 'Onbekend';
  }
  if (is_null($expert->birth_date)) {
    $expert->birth_date = '';
  }
  if (is_null($expert->age)) {
    $expert->age = 0;
  }
  $sql = "INSERT INTO result_irene (expert_id, expert_name, expert_gender, expert_age, expert_birth_date, expert_71, expert_status, prins_count, 
    procus_count, recent_main_status, recent_main_start_date, recent_main_end_date, recent_main_type, recent_main_subject, recent_main_customer, 
    recent_main_country, expert_sector, sector_coordinator, latest_prins_end_date, latest_prins_project_number, latest_prins_customer, 
    latest_prins_country, expert_contact_type) 
    VALUES(%1, %2, %3, %4, %5, %6, %7, %8, %9, %10, %11, %12, %13, %14, %15, %16, %17, %18, %19, %20, %21, %22, %23)";
  $sqlParams = array(
    1 => array($expert->contact_id, 'Integer'),
    2 => array($expert->expert, 'String'),
    3 => array($expert->gender, 'String'),
    4 => array($expert->age, 'Integer'),
    5 => array($expert->birth_date, 'String'),
    6 => array($expert71, 'Integer'),
    7 => array($expert->expert_status, 'String'),
    8 => array($expert->prins_missies, 'Integer'),
    9 => array($expert->procus_count, 'Integer'),
    17 => array($expert->sector, 'String'),
    18 => array($expert->sector_coordinator, 'String'),
    );
  if (isset($mostRecentMain->main_status)) {
    $sqlParams[10] = array($mostRecentMain->main_status, 'String');
  } else {
    $sqlParams[10] = array('', 'String');
  }
  if (isset($mostRecentMain->main_start_date) && !empty($mostRecentMain->main_start_date)) {
    $sqlParams[11] = array(date('Y-m-d', strtotime($mostRecentMain->main_start_date)), 'String');
  } else {
    $sqlParams[11] = array('', 'String');
  }
  if (isset($mostRecentMain->main_end_date) && !empty($mostRecentMain->main_end_date)) {
    $sqlParams[12] = array(date('Y-m-d', strtotime($mostRecentMain->main_end_date)), 'String');
  } else {
    $sqlParams[12] = array('', 'String');
  }
  if (isset($mostRecentMain->main_type)) {
    $sqlParams[13] = array($mostRecentMain->main_type, 'String');
  } else {
    $sqlParams[13] = array('', 'String');
  }
  if (isset($mostRecentMain->main_subject)) {
    $sqlParams[14] = array($mostRecentMain->main_subject, 'String');
  } else {
    $sqlParams[14] = array('', 'String');
  }
  if (isset($mostRecentMain->main_customer)) {
    $sqlParams[15] = array($mostRecentMain->main_customer, 'String');
  } else {
    $sqlParams[15] = array('', 'String');
  }
  if (isset($mostRecentMain->main_country)) {
    $sqlParams[16] = array($mostRecentMain->main_country, 'String');
  } else {
    $sqlParams[16] = array('', 'String');
  }
  if (isset($latestPrins['end_date']) && !empty($latestPrins['end_date']) && $latestPrins['end_date'] != '0000-00-00') {
    $sqlParams[19] = array($latestPrins['end_date'], 'String');
  } else {
    $sqlParams[19] = array('', 'String');
  }
  if (isset($latestPrins['project_number']) && !empty($latestPrins['project_number'])) {
    $sqlParams[20] = array($latestPrins['project_number'], 'String');
  } else {
    $sqlParams[20] = array('', 'String');
  }
  if (isset($latestPrins['customer']) && !empty($latestPrins['customer'])) {
    $sqlParams[21] = array($latestPrins['customer'], 'String');
  } else {
    $sqlParams[21] = array('', 'String');
  }
  if (isset($latestPrins['country']) && !empty($latestPrins['country'])) {
    $sqlParams[22] = array($latestPrins['country'], 'String');
  } else {
    $sqlParams[22] = array('', 'String');
  }
  if (!empty($expert->contact_sub_type)) {
    $contactSubTypes = explode(CRM_Core_DAO::VALUE_SEPARATOR, $expert->contact_sub_type);
    $sqlParams[23] = array('No Expert', 'String');
    foreach ($contactSubTypes as $contactSubType) {
      if ($contactSubType == 'Expert') {
        $sqlParams[23] = array('Expert', 'String');
      }
    }
  } else {
    $sqlParams[23] = array('', 'String');
  }
  CRM_Core_DAO::executeQuery($sql, $sqlParams);
  // add group fields
  $index = 1;
  while ($index <= $maxGroupFields) {
    $propertyName = 'group_'.$index;
    if (isset($expert->$propertyName)) {
      if (CRM_Core_DAO::checkFieldExists('result_irene', $propertyName)) {
        $update = 'UPDATE result_irene SET '.$propertyName.' = %1 WHERE expert_id = %2';
        $params = array(
          1 => array($expert->$propertyName, 'String'),
          2 => array($expert->contact_id, 'Integer'),
        );
        CRM_Core_DAO::executeQuery($update, $params);
      }
      $index++;
    }
  }
}


/**
 * Function to get the data of the latest prins history
 *
 * @param $contactId
 * @return array
 */
function _getLatestPrins($contactId) {
  $latestPrins = array();
  $sql = "SELECT * FROM civicrm_value_prins_history WHERE entity_id = %1";
  $sqlParams = array(1 => array($contactId, 'Integer'));
  $prins = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  while ($prins->fetch()) {
    // locate end date and test if it is later than the one in the result array
    $endDateParts = explode('End date:', $prins->prins_history);
    if (isset($endDateParts[1])) {
      $endDate = new DateTime(trim($endDateParts[1]));
      if (!isset($latestPrins['end_date'])) {
        _setLatestPrins($prins->prins_history, $latestPrins);
        $latestPrins['end_date'] = $endDate->format('d-m-Y');
      } else {
        $latestDate = new DateTime($latestPrins['end_date']);
        if ($endDate > $latestDate) {
          _setLatestPrins($prins->prins_history, $latestPrins);
          $latestPrins['end_date'] = $endDate->format('d-m-Y');
        }
      }
    }
  }
  return $latestPrins;
}

/**
 * Function to set the latest prins data
 *
 * @param $prinsHistory
 * @param $latestPrins
 */
function _setLatestPrins($prinsHistory, &$latestPrins) {
  $latestPrins = array();
  // locate and set project number
  $projectNumberParts = explode('Project number:', $prinsHistory);
  if (isset($projectNumberParts[1])) {
    // locate and set company name
    $customerParts = explode('Company name:', $projectNumberParts[1]);
    $latestPrins['project_number'] = trim($customerParts[0]);
    // locate place and later country
    if (isset($customerParts[1])) {
      $placeParts = explode('Place:', $customerParts[1]);
      if (isset($placeParts[1])) {
        $latestPrins['customer'] = trim($placeParts[0]);
        $countryParts = explode('Country:', $placeParts[1]);
        if (isset($countryParts[1])) {
          $sectorParts = explode('Sector:', $countryParts[1]);
          if (isset($sectorParts[1])) {
            $latestPrins['country'] = trim($sectorParts[0]);
          }
        }
      }
    }
  }
}

/**
 * Function to get most recent main activity (not error)
 * @param $contactId
 * @return bool|Object
 */
function _getRecentMain($contactId) {
  // find most recent main activity for contact
  $sql = "SELECT cc.subject AS main_subject, ctov.label AS main_type, csov.label AS main_status, cust.display_name AS main_customer,
    cntry.name AS main_country, mai.start_date AS main_start_date, mai.end_date AS main_end_date
    FROM civicrm_relationship cr LEFT JOIN civicrm_case cc ON cr.case_id = cc.id
    LEFT JOIN civicrm_option_value ctov ON cc.case_type_id = ctov.value AND ctov.option_group_id = %1
    LEFT JOIN civicrm_option_value csov ON cc.status_id = csov.value AND csov.option_group_id = %2
    LEFT JOIN civicrm_case_contact casecust ON cc.id = casecust.case_id
    LEFT JOIN civicrm_contact cust ON casecust.contact_id = cust.id
    LEFT JOIN civicrm_address adr ON cust.id = adr.contact_id AND adr.is_primary = %3 
    LEFT JOIN civicrm_country cntry ON adr.country_id = cntry.id
    LEFT JOIN civicrm_value_main_activity_info mai ON cc.id = mai.entity_id
    WHERE cr.contact_id_b = %4 AND cr.case_id IS NOT NULL AND cr.relationship_type_id = %5
    AND ctov.label IN(%6, %7,  %8, %9) AND csov.label != %10 ORDER BY mai.start_date DESC LIMIT 1";
  $sqlParams = array(
    1 => array(CASETYPEOPTIONGROUP, 'Integer'),
    2 => array(CASESTATUSOPTIONGROUP, 'Integer'),
    3 => array(1, 'Integer'),
    4 => array($contactId, 'Integer'),
    5 => array(EXPERTRELATIONSHIPTYPE, 'Integer'),
    6 => array('Advice', 'String'),
    7 => array('Business', 'String'),
    8 => array('RemoteCoaching', 'String'),
    9 => array('Seminar', 'String'),
    10 => array('Error', 'String')
  );
  $recentMain = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  if ($recentMain->fetch()) {
    return $recentMain;
  } else {
    return FALSE;
  }
}

/**
 * Function to define constants
 */
function _defineConstants() {
  $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
    'name' => 'case_type',
    'return' => 'id'));
  $caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
    'name' => 'case_status',
    'return' => 'id'));
  $expertRelationshipTypeId = civicrm_api3('RelationshipType', 'getvalue', array(
    'name_a_b' => 'Expert',
    'name_b_a' => 'Expert',
    'return' => 'id'));
  define('CASETYPEOPTIONGROUP', $caseTypeOptionGroupId);
  define('CASESTATUSOPTIONGROUP', $caseStatusOptionGroupId);
  define('EXPERTRELATIONSHIPTYPE', $expertRelationshipTypeId);
  define('EXCLUDEGROUPS', 'Former Expert;Rejected Expert');
}

/**
 * Function to get the group of the contact and add them to expert
 *
 * @param $contactId
 * @param $expert
 * @param $maxGroupFields
 * @return bool
 */
function _getGroups($contactId, &$expert, &$maxGroupFields) {
  $sql = "SELECT cg.title FROM civicrm_group_contact gc JOIN civicrm_group cg ON gc.group_id = cg.id
    WHERE gc.contact_id = %1 and gc.status = %2";
  $sqlParams = array(
    1 => array($contactId, 'Integer'),
    2 => array('Added', 'String')
  );
  $groups = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  $countGroups = (int) $groups->N;
  while ($countGroups > $maxGroupFields) {
    $maxGroupFields++;
    $fieldName = 'group_'.$maxGroupFields;
    if (!CRM_Core_DAO::checkFieldExists('result_irene', $fieldName)) {
      CRM_Core_DAO::executeQuery('ALTER TABLE result_irene ADD COLUMN ' . $fieldName . ' VARCHAR(128)');
    }
  }
  $groupIndex = 1;
  while($groups->fetch()) {
    $propertyName = 'group_'.$groupIndex;
    $expert->$propertyName = $groups->title;
    $groupIndex++;
  }
  return FALSE;
}

/**
 * Function to determine if expert is in any group that is to be excluded
 *
 * @param $contactId
 * @return bool
 */

function _expertInExcludedGroups($contactId) {
  $excluded = explode(';', EXCLUDEGROUPS);
  $groupIds = array();
  foreach ($excluded as $groupTitle) {
    try {
      $groupId = civicrm_api3('Group', 'getvalue', array(
        'title' => $groupTitle,
        'return' => 'id'
      ));
      $groupIds[] = $groupId;
    }
    catch (CiviCRM_API3_Exception $ex) {

    }
  }
  $sql = "SELECT COUNT(*) FROM civicrm_group_contact WHERE group_id IN (%1) AND contact_id = %2 AND status = %3";
  $sqlParams = array(
    1 => array(implode(',', $groupIds), 'String'),
    2 => array($contactId, 'Integer'),
    3 => array('Added', 'String')
  );
  $excludeCount = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
  if ($excludeCount > 0) {
    return TRUE;
  }
  return FALSE;
}
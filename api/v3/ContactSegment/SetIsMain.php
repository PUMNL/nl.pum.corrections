<?php

/**
 * ContactSegment.SetIsMain API
 * PUM specific one time job to set the is_main if there is only one sector for the role Expert
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_segment_setismain($params) {
  $query = "SELECT cs.id AS contactSegmentId FROM civicrm_contact_segment cs
    JOIN civicrm_segment sg ON cs.segment_id = sg.id
    WHERE role_value = %1 AND parent_id IS NULL GROUP BY contact_id HAVING COUNT(*) = 1";
  $params = array(1 => array("Expert", "String"));
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  while ($dao->fetch()) {
    $update = "UPDATE civicrm_contact_segment SET is_main = %1 WHERE id = %2";
    $updateParams = array(1 => array(1, 'Integer'), 2 => array($dao->contactSegmentId, 'Integer'));
    CRM_Core_DAO::executeQuery($update, $updateParams);
  }
  return civicrm_api3_create_success(array('Is Main is gezet voor Experts met maar 1 Sector'), $params, 'ContactSegment', 'SetIsMain');
}


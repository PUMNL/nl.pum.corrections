<?php

/**
 * CeoCfo.Fix API
 * Correction for issue 3156: all CEO/CFO relations on cases back to Thijs and Hans
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_ceo_cfo_fix($params) {
  $ceoRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue',
    array('name_a_b' => 'CEO', 'return' => 'id'));
  $cfoRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue',
    array('name_a_b' => 'CFO', 'return' => 'id'));
  $thijsContactId = civicrm_api3('Contact', 'Getvalue',
    array('first_name' => 'Thijs', 'middle_name' => 'van', 'last_name' => 'Praag', 'return' => 'id'));
  $hansContactId = civicrm_api3('Contact', 'Getvalue',
    array('first_name' => 'Hans', 'last_name' => 'Luursema', 'return' => 'id'));

  $query = 'UPDATE civicrm_relationship SET contact_id_b = %1 WHERE relationship_type_id = %2 AND case_id IS NOT NULL';
  $ceoParams = array(1 => array($thijsContactId, 'Integer'), 2 => array($ceoRelationshipTypeId, 'Integer'));
  CRM_Core_DAO::executeQuery($query, $ceoParams);
  $cfoParams = array(1 => array($hansContactId, 'Integer'), 2 => array($cfoRelationshipTypeId, 'Integer'));
  CRM_Core_DAO::executeQuery($query, $cfoParams);

  return civicrm_api3_create_success(array(), $params, 'CeoCfo', 'Fix');
}


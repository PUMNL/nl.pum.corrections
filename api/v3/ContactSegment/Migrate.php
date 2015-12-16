<?php
/**
 * ContactSegment.Migrate API
 * Specific PUM scheduled job to migrate Sector and Area of Expertise to Contact Segment
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */

function civicrm_api3_contact_segment_migrate($params) {

  // get top level sector tag id
  $topTagId = _getTopTagId();

  // empty old data
  _removeOldData();

  // create temporary table for tag to hold segment_id
  _createTempTable();

  // select all top level sectors
  $query = 'SELECT id, name FROM civicrm_tag WHERE parent_id = %1';
  $sectorTag = CRM_Core_DAO::executeQuery($query, array(1 => $topTagId, 'Integer'));

  while ($sectorTag->fetch()) {

    // create segment for sector
    $sectorSegment = _createSegment($sectorTag->name);

    // store in temp table
    _writeTempRecord($sectorTag->id, $sectorSegment['id']);

    // process all child tags (create segment)
    _processChildren($sectorTag->id, $sectorSegment['id']);

    // all sectors and areas of expertise created, now create contact_segment for all coordinators
    _processSC($sectorTag->id);

    // now add contact_segments for sector tags
    _processContactTags($sectorTag->id);
  }

  // delete all sector coordinator relationships that are not explicitly on case on
  $scRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Sector Coordinator', 'return' => 'id'));
  $relationshipDelete = 'DELETE FROM civicrm_relationship WHERE relationship_type_id = %1 AND case_id IS NULL';
  CRM_Core_DAO::executeQuery($relationshipDelete, array(1 => array($scRelationshipTypeId, 'Integer')));

  //delete all tags and entity tags that have been created and are now in temp table
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_entity_tag WHERE tag_id IN(SELECT DISTINCT(tag_id) FROM contact_segment_migrate)");
  CRM_Core_DAO::executeQuery("DELETE FROM civicrm_tag WHERE id IN(SELECT DISTINCT(tag_id) FROM contact_segment_migrate)");

  return civicrm_api3_create_success(array(), $params, 'ContactSegment', 'Migrate');
}

/**
 * function to process all entity tags for tag into contact segment
 *
 * @param $tagId
 */
function _processContactTags($tagId) {
  // get all entity tags for tag
  $query = 'SELECT entity_id, contact_sub_type
    FROM civicrm_entity_tag JOIN civicrm_contact ON entity_id = civicrm_contact.id
    WHERE tag_id = %1 AND entity_table = %2';
  $params = array(
    1 => array($tagId, 'Integer'),
    2 => array('civicrm_contact', 'String'));
  $entityTagContact = CRM_Core_DAO::executeQuery($query, $params);
  while ($entityTagContact->fetch()) {
    $contactSubTypes = explode(CRM_Core_DAO::VALUE_SEPARATOR, $entityTagContact->contact_sub_type);
    $role = "Other";
    if (in_array('Expert', $contactSubTypes)) {
      $role = 'Expert';
    }
    if (in_array('Customer', $contactSubTypes)) {
      $role = 'Customer';
    }
    $contactSegment = array();
    $contactSegment['contact_id'] = $entityTagContact->entity_id;
    $contactSegment['segment_id'] = _getSegmentIdWithTagId($tagId);
    $contactSegment['role_value'] = $role;
    $contactSegment['start_date'] = '20150501';
    $contactSegment['is_active'] = 1;
    _createContactSegment($contactSegment);
  }
}

/**
 * function to create contact segment
 *
 * @param $params
 */
function _createContactSegment($params) {
  $params['is_active'] = 1;
  if ($params['end_date']) {
    $endDate = new DateTime($params['end_date']);
    $nowDate = new DateTime();
    if ($endDate < $nowDate) {
      $params['is_active'] = 0;
    }
  }
  civicrm_api3('ContactSegment', 'Create', $params);
}
/**
 * function to create contact segment record for sector coordinator
 *
 * @param $sectorTagId
 */
function _processSC($sectorTagId) {
  $role = 'Sector Coordinator';
  $query = 'SELECT * FROM civicrm_tag_enhanced WHERE tag_id = %1';
  $enhancedTag = CRM_Core_DAO::executeQuery($query, array(1 => array($sectorTagId, 'Integer')));
  while ($enhancedTag->fetch()) {
    $contactSegment = array();
    $contactSegment['contact_id'] = $enhancedTag->coordinator_id;
    $contactSegment['segment_id'] = $sectorTagId;
    $contactSegment['role_value'] = $role;
    if ($enhancedTag->start_date) {
      $contactSegment['start_date'] = date('Ymd', strtotime($enhancedTag->start_date));
    } else {
      $contactSegment['start_date'] = date('Ymd');
    }
    if ($enhancedTag->end_date) {
      $contactSegment['end_date'] = date('Ymd', strtotime($enhancedTag->edn_date));
    }
    _createContactSegment($contactSegment);
  }
}

/**
 * function to process area of expertise tags
 *
 * @param $parentTagId
 * @param $parentSegmentId
 */
function _processChildren($parentTagId, $parentSegmentId) {
  $query = 'SELECT id, name FROM civicrm_tag WHERE parent_id = %1';
  $aoeTag = CRM_Core_DAO::executeQuery($query, array(1 => $parentTagId, 'Integer'));
  while ($aoeTag->fetch()) {
    // create segment for area of expertise
    $aoeSegment = _createSegment($aoeTag->name, $parentSegmentId);
    // store in temp table
    _writeTempRecord($aoeTag->id, $aoeSegment['id']);
    // now add contact_segments for sector tags
    _processContactTags($aoeTag->id);
  }
}

/**
 * function to get the tag id of Sector (which should be top level)
 *
 * @return integer
 */
function _getTopTagId() {
  $query = 'SELECT id FROM civicrm_tag WHERE name = %1';
  $params = array(1 => array('Sector', 'String'));
  return CRM_Core_DAO::singleValueQuery($query, $params);
}

/**
 * function to create segment
 *
 * @param $label
 * @param $parentId
 * @return array
 */
function _createSegment($label, $parentId = NULL) {
  $segmentParams['label'] = $label;
  if ($parentId) {
    $segmentParams['parent_id'] = $parentId;
  }
  return civicrm_api3('Segment', 'Create', $segmentParams);
}

/**
 * function to remove old data before migrating
 */
function _removeOldData() {
  CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_contact_segment');
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_segment');
  CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_segment AUTO_INCREMENT = 1');
}

/**
 * function to create temp table
 */
function _createTempTable() {
  $query = "CREATE TEMPORARY TABLE contact_segment_migrate (tag_id int(11) NOT NULL, segment_id int(11) DEFAULT NULL,
  PRIMARY KEY (tag_id), UNIQUE KEY tag_id_UNIQUE (tag_id))";
  CRM_Core_DAO::executeQuery($query);
}

/**
 * function to get write temp record
 */
function _writeTempRecord($tagId, $segmentId) {
  $query = "INSERT INTO contact_segment_migrate SET tag_id = %1, segment_id = %2";
  $params = array(
    1 => array($tagId, 'Integer'),
    2 => array($segmentId, 'Integer')
  );
  CRM_Core_DAO::executeQuery($query, $params);
}

/**
 * function to get segment_id with tag_id
 */
function _getSegmentIdWithTagId($tagId) {
  $query = "SELECT segment_id FROM contact_segment_migrate WHERE tag_id = %1";
  return CRM_Core_DAO::singleValueQuery($query, array(1 => array($tagId, 'Integer')));
}


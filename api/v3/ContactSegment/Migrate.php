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
  // initiate error logger
  $errorLogger = new CRM_Corrections_ErrorLogger('contactsegment_migrate_log');

  // get top level sector tag id
  $topTagId = _getTopTagId();

  //if processed for the first time (no processed records) create processed table and remove old data
  _firstTimeProcessing($topTagId);

  // create temporary table for tag to hold segment_id
  _createTempTable();

  // select all top level sectors
  $query = 'SELECT id, name FROM civicrm_tag JOIN tags_processed tp ON id=tag_id
    WHERE parent_id = %1 AND processed = %2 LIMIT 100';
  $sectorTag = CRM_Core_DAO::executeQuery($query, array(
    1 => array($topTagId, 'Integer'),
    2 => array(0, 'Integer')));

  while ($sectorTag->fetch()) {
    // set tag to processed
    CRM_Core_DAO::executeQuery('UPDATE tags_processed SET processed = %1 WHERE tag_id = %2',
      array(1 => array(1, 'Integer'), 2 => array($sectorTag->id, 'Integer')));

    // create segment for sector
    $sectorSegment = _createSegment($sectorTag->name, $errorLogger);

    // store in temp table
    _writeTempRecord($sectorTag->id, $sectorSegment['id'], $errorLogger);

    // process all child tags (create segment)
    _processChildren($sectorTag->id, $sectorSegment['id'], $errorLogger);

    // all sectors and areas of expertise created, now create contact_segment for all coordinators
    _processSC($sectorTag->id, $errorLogger);

    _processTaxonomy($sectorTag->id, $errorLogger);

    // now add contact_segments for sector tags
    _processContactTags($sectorTag->id, $errorLogger);
  }

  //delete all sector coordinator relationships that are not explicitly on case on
  $scRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Sector Coordinator', 'return' => 'id'));
  $relationshipDelete = 'DELETE FROM civicrm_relationship WHERE relationship_type_id = %1 AND case_id IS NULL';
  CRM_Core_DAO::executeQuery($relationshipDelete, array(1 => array($scRelationshipTypeId, 'Integer')));

  //delete all tags and entity tags that have been created and are now in temp table
  //CRM_Core_DAO::executeQuery("DELETE FROM civicrm_entity_tag WHERE tag_id IN(SELECT DISTINCT(tag_id) FROM contact_segment_migrate)");
  //CRM_Core_DAO::executeQuery("DELETE FROM civicrm_tag WHERE id IN(SELECT DISTINCT(tag_id) FROM contact_segment_migrate)");

  return civicrm_api3_create_success(array(), $params, 'ContactSegment', 'Migrate');
}

/**
 * function to process all entity tags for tag into contact segment
 *
 * @param $tagId
 * @param $errorLogger
 */
function _processContactTags($tagId, $errorLogger) {
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
    $errorLogger->logMessage('Notification', 'ContactSegment created for contact '.$contactSegment['contact_id'].
      ' and segment '.$contactSegment['segment_id'].' with role '.$role);
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
  //first check if we do not have a contact segment yet for contact_id, segment_id and role. If so,
  // end date might have to be changed
  $existing = _getContactSegment($params);
  if (!empty($existing)) {
    $params['id'] = $existing['id'];
  }
  civicrm_api3('ContactSegment', 'Create', $params);
}

/**
 * function to get contact segment
 *
 * @param array $params
 * @return array $contactSegment
 */
function _getContactSegment($params) {
  $contactSegment = array();
  if (isset($params['contact_id']) && isset($params['segment_id']) && isset($params['role_value'])) {
    try {
      $existingParams = array(
        'contact_id' => $params['contact_id'],
        'segment_id' => $params['segment_id'],
        'role_value' => $params['role_value']
      );
      $contactSegment = civicrm_api3('ContactSegment', 'Getsingle', $existingParams);
    } catch (CiviCRM_API3_Exception $ex) {
      $contactSegment = array();
    }
  }
  return $contactSegment;
}

/**
 * function to create contact segment record for sector coordinator
 *
 * @param $sectorTagId
 * @param $errorLogger
 */
function _processSC($sectorTagId, $errorLogger) {
  $role = 'Sector Coordinator';
  $query = 'SELECT * FROM civicrm_tag_enhanced WHERE tag_id = %1';
  $enhancedTag = CRM_Core_DAO::executeQuery($query, array(1 => array($sectorTagId, 'Integer')));
  while ($enhancedTag->fetch()) {
    if ($enhancedTag->coordinator_id) {
      $contactSegment = array();
      $contactSegment['contact_id'] = $enhancedTag->coordinator_id;
      $contactSegment['segment_id'] = _getSegmentIdWithTagId($sectorTagId);
      $contactSegment['role_value'] = $role;
      if ($enhancedTag->start_date) {
        $contactSegment['start_date'] = date('Ymd', strtotime($enhancedTag->start_date));
      } else {
        $contactSegment['start_date'] = date('Ymd', strtotime('01-05-2015'));
      }
      if ($enhancedTag->end_date) {
        $contactSegment['end_date'] = date('Ymd', strtotime($enhancedTag->end_date));
      } else {
        if (isset($contactSegment['end_date'])) {
          unset($contactSegment['end_date']);
        }
      }
      _createContactSegment($contactSegment);
      $errorLogger->logMessage('Notification', 'ContactSegment created for contact ' . $contactSegment['contact_id'] .
        ' and segment ' . $contactSegment['segment_id'] . ' with role ' . $role);
    }
  }
}

/**
 * Function to migrate drupal taxonomy term to link to the new segment
 *
 * @param $sectorTagId
 * @param $errorLogger
 */
function _processTaxonomy($sectorTagId, $errorLogger) {
  $segment_id = _getSegmentIdWithTagId($sectorTagId);
  $segment = civicrm_api3('Segment', 'getsingle', array('id' => $segment_id));

  //determine the current sector coordinator
  $sector_coorinator_id = false;
  $civicoop_segment_role_option_group = civicrm_api3('OptionGroup', 'getvalue', array('name' => 'civicoop_segment_role', 'return' => 'id'));
  $role = civicrm_api3('OptionValue', 'getvalue', array('option_group_id' => $civicoop_segment_role_option_group, 'name' => 'sector_coordinator', 'return' => 'value'));
  try {
    $contact_segment = civicrm_api3('ContactSegment', 'getsingle', array('segment_id' => $segment_id, 'is_active' => 1, 'role_value' => $role));
    $sector_coorinator_id = $contact_segment['contact_id'];
  } catch (Exception $e) {
    //do nothing
  }

  //retrieve the linked taxonomy terms in drupal
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'taxonomy_term')
      ->fieldCondition('field_pum_tag_id', 'value', $sectorTagId, '=');
  $result = $query->execute();
  if (isset($result['taxonomy_term']) && count($result['taxonomy_term'])) {
    foreach($result['taxonomy_term'] as $term_id => $t) {
      $term = taxonomy_term_load($term_id);
      $term->name = $segment['label'];
      $term->field_pum_segment_id["und"][0]["value"] = $segment_id;
      $term->field_pum_coordinator_id["und"][0]["value"] = $sector_coorinator_id ? $sector_coorinator_id : null;
      taxonomy_term_save($term);
      $errorLogger->logMessage('Notification', 'Taxonomy updated for segment ' . $segment_id);
    }
  }
}

/**
 * function to process area of expertise tags
 *
 * @param $parentTagId
 * @param $parentSegmentId
 * @param $errorLogger
 */
function _processChildren($parentTagId, $parentSegmentId, $errorLogger) {
  $query = 'SELECT id, name FROM civicrm_tag WHERE parent_id = %1';
  $aoeTag = CRM_Core_DAO::executeQuery($query, array(1 => array($parentTagId, 'Integer')));
  while ($aoeTag->fetch()) {
    // create segment for area of expertise
    $aoeSegment = _createSegment($aoeTag->name, $errorLogger, $parentSegmentId);
    // store in temp table
    _writeTempRecord($aoeTag->id, $aoeSegment['id'], $errorLogger);
    // now add contact_segments for sector tags
    _processContactTags($aoeTag->id, $errorLogger);
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
 * @param $errorLogger
 * @param $parentId
 * @return array
 */
function _createSegment($label, $errorLogger, $parentId = NULL) {
  $segmentParams['label'] = $label;
  if ($parentId) {
    $segmentParams['parent_id'] = $parentId;
  }
  $segment = civicrm_api3('Segment', 'Create', $segmentParams);
  $errorLogger->logMessage('Notification', 'Segment created : '.$segment['values']['id'].' with label '.$label.' and parent '.$parentId);
  return $segment['values'];
}

/**
 * function to remove old data before migrating
 */
function _removeOldData() {
  CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_contact_segment');
  CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_segment_tree');
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
 *
 * @param $tagId
 * @param $segmentId
 * @param $errorLogger
 */
function _writeTempRecord($tagId, $segmentId, $errorLogger) {
  $query = "INSERT INTO contact_segment_migrate SET tag_id = %1, segment_id = %2";
  $params = array(
    1 => array($tagId, 'Integer'),
    2 => array($segmentId, 'Integer')
  );
  CRM_Core_DAO::executeQuery($query, $params);
  $errorLogger->logMessage('Notification', 'Temp record written : tagId '.$tagId.' and segmentId '.$segmentId);

}

/**
 * function to get segment_id with tag_id
 */
function _getSegmentIdWithTagId($tagId) {
  $query = "SELECT segment_id FROM contact_segment_migrate WHERE tag_id = %1";
  return CRM_Core_DAO::singleValueQuery($query, array(1 => array($tagId, 'Integer')));
}

/**
 * Functin to create processed_tags file if required, and add records to it
 *
 * @param int $topTagId
 */
function _firstTimeProcessing($topTagId) {
  $firstTime = FALSE;
  if (!CRM_Core_DAO::checkTableExists('tags_processed')) {
    $processedCreate = "CREATE TABLE IF NOT EXISTS tags_processed(
    tag_id int(10) unsigned NOT NULL,
    processed tinyint(3) unsigned DEFAULT 0)";
    CRM_Core_DAO::executeQuery($processedCreate);
    $firstTime = TRUE;
  } else {
    $countQry = CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) AS countTags FROM tags_processed');
    if ($countQry == 0) {
      $firstTime = TRUE;
    }
  }
  if ($firstTime) {
    _removeOldData();
    $insert = "INSERT INTO tags_processed (tag_id) SELECT DISTINCT(id) FROM civicrm_tag WHERE parent_id = %1";
    CRM_Core_DAO::executeQuery($insert, array(1 => array($topTagId, 'Integer')));
  }
}
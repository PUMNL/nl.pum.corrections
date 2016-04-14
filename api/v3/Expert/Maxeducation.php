<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Expert.Maxeducation
 * Correction for issue 2988 max 10 education items on expert cart
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_expert_maxeducation($params) {
  $custom_group = new CRM_Core_BAO_CustomGroup();
  $custom_group->name = 'Education';
  if ($custom_group->find(true)) {
    $custom_group->max_multiple = 10;
    $custom_group->save();

    CRM_Utils_System::flushCache();
  }

  return civicrm_api3_create_success(array(), $params, 'Expert', 'Maxeducation');
}
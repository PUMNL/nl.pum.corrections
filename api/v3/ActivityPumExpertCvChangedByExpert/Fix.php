<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

function civicrm_api3_activity_pum_expert_cv_changed_by_expert_fix($params) {
  $expert_cv_changed_by_expert = civicrm_api3('OptionValue', 'getsingle', array(
    'option_group_id' => 2,
    'name' => 'Expert PUM CV changed by Expert',
  ));
  civicrm_api3('OptionValue', 'delete', array(
    'id' => $expert_cv_changed_by_expert['id'],
  ));

  return civicrm_api3_create_success(array(), $params, 'ActivityPumExpertCvChangedByExpert', 'Fix');
}
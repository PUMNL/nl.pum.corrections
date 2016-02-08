<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Removes the state/provincie code van addressen
 * See issue #3159
 *
 * @param $params['country_id'] required
 * @return array
 */
function civicrm_api3_provincie_code_remove($params) {
    if (empty($params['country_id'])) {
        return civicrm_api3_create_error('Country ID is a required paramater');
    }

    $sql = "UPDATE `civicrm_address`  SET `state_province_id` = NULL WHERE `country_id`  = %1";
    $sqlParams[1] = array($params['country_id'], 'Integer');

    CRM_Core_DAO::executeQuery($sql, $sqlParams);

    return civicrm_api3_create_success(array(), $params, 'ProvincieCode', 'Remove');
}
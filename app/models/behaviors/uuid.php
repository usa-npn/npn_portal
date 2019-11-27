<?php
/**
 * UUID Behavior class file.
 *
 * Model Behavior to support adding UUID's when a record is saved.
 *
 * This behavior implements the beforeSave() callback for updating the
 * specified field with a UUID. The actual randomness of the generated
 * UUID has not been tested. Use at your own risk.
 *
 * Usage in model:
 *
 * Add Uuid to the $actsAs array of your model:
 * var $actsAs = array('Uuid' => array('field' => 'id'));
 *
 * @filesource
 * @package     app
 * @subpackage  models.behaviors
 */

/**
 * Add UUID behavior to a model.
 *
 * @author      Billy Gunn
 * @package     app
 * @subpackage  models.behaviors
 */
class UuidBehavior extends ModelBehavior {
    /**
     * Default model settings
     */
    var $defaultSettings = array('field' => 'id');

    /**
     * Initiate behaviour for the model using settings.
     *
     * @param object $model    Model using the behaviour
     * @param array $settings    Settings to override for model.
     *
     * @access public
     */
    function setup(&$model, $settings = array()) {
        $field = $this->defaultSettings['field'];

        if (!empty($settings['field'])) {
            $field = $settings['field'];
        }

        if ($model->hasField($field)) {
            $this->settings[$model->name] = array('field' => $field);
        }
    }

    /**
     * Generates a pseudo-random UUID.
     * Slightly modified version of a function submitted to php.net:
     * http://us2.php.net/manual/en/function.com-create-guid.php#52354
     *
     * @access public
     */
    function uuid() {
        if (function_exists('com_create_guid')) {

            return str_replace("}", "", str_replace("{", "", com_create_guid()));
        } else {
            mt_srand((double)microtime()*10000);
            $charid = md5(uniqid(rand(), true));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8).$hyphen
                  . substr($charid, 8, 4).$hyphen
                  . substr($charid,12, 4).$hyphen
                  . substr($charid,16, 4).$hyphen
                  . substr($charid,20,12);

            return $uuid;
        }
    }

    /**
     * Run before a model is saved to add a UUID to a field.
     *
     * @param object $model    Model about to be saved.
     *
     * @access public
     */
    function beforeSave(&$model) {
        if ($this->settings[$model->name]) {
            $field = $this->settings[$model->name]['field'];
            if (!isset($model->data[$model->name][$field])) {
                $model->data[$model->name][$field] = $this->uuid();
            }
        }
    }
}

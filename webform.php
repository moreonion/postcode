<?php

/**
 * @file
 * Webform module postcode component.
 */

/**
 * Implements _webform_defaults_[component]().
 */
function _webform_defaults_postcode() {
  return array(
    'name' => 'Postcode',
    'form_key' => 'postcode',
    'pid' => 0,
    'weight' => 0,
    'value' => '',
    'mandatory' => 0,
    'extra' => array(
      'width' => '',
      'unique' => 0,
      'disabled' => 0,
      'title_display' => 0,
      'description' => '',
      'attributes' => array(),
      'private' => FALSE,
      'postcode_country_mode' => 'component',
      'postcode_country' => '',
      'postcode_country_component' => NULL,
    ),
  );
}

/**
 * Implements _webform_edit_[component]().
 */
function _webform_edit_postcode($component) {
  require_once DRUPAL_ROOT . '/includes/locale.inc';

  $form['value'] = array(
    '#type' => 'postcode',
    '#title' => t('Default value'),
    '#default_value' => $component['value'],
    '#description' => t('The default value of the field.') . theme('webform_token_help'),
    '#size' => 60,
    '#maxlength' => 127,
    '#weight' => 0,
  );
  $form['display']['width'] = array(
    '#type' => 'textfield',
    '#title' => t('Width'),
    '#default_value' => $component['extra']['width'],
    '#description' => t('Width of the textfield.') . ' ' . t('Leaving blank will use the default size.'),
    '#size' => 5,
    '#maxlength' => 10,
    '#parents' => array('extra', 'width'),
  );
  $form['display']['disabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Disabled'),
    '#return_value' => 1,
    '#description' => t('Make this field non-editable. Useful for setting an unchangeable default value.'),
    '#weight' => 11,
    '#default_value' => $component['extra']['disabled'],
    '#parents' => array('extra', 'disabled'),
  );
  $form['validation']['unique'] = array(
    '#type' => 'checkbox',
    '#title' => t('Unique'),
    '#return_value' => 1,
    '#description' => t('Check that all entered values for this field are unique. The same value is not allowed to be used twice.'),
    '#weight' => 1,
    '#default_value' => $component['extra']['unique'],
    '#parents' => array('extra', 'unique'),
  );
  $country_class = drupal_html_id('postcode-country-mode');
  $form['validation']['postcode_country_mode'] = [
    '#type' => 'radios',
    '#title' => t('Country for validation'),
    '#description' => t('Choose how the country that is used to validate the postcode is selected.'),
    '#options' => [
      'component' => t('From another form component'),
      'fixed' => t('Fixed country'),
    ],
    '#weight' => 2,
    '#attributes' => ['class' => [$country_class]],
    '#default_value' => $component['extra']['postcode_country_mode'],
    '#parents' => array('extra', 'postcode_country_mode'),
  ];
  $form['validation']['postcode_country'] = [
    '#type' => 'select',
    '#options' => country_get_list(),
    '#states' => ['visible' => [".$country_class input" => ['value' => 'fixed']]],
    '#weight' => 3,
    '#default_value' => $component['extra']['postcode_country'],
    '#parents' => array('extra', 'postcode_country'),
  ];
  $my_page = isset($component['page_num']) ? $component['page_num'] : NULL;
  $my_cid = isset($component['cid']) ? [$component['cid']] : [];
  $form['validation']['postcode_country_component'] = [
    '#states' => ['visible' => [".$country_class input" => ['value' => 'component']]],
    '#weight' => 4,
    '#default_value' => $component['extra']['postcode_country_component'],
    '#parents' => array('extra', 'postcode_country_component'),
  ] + _postcode_component_selector(node_load($component['nid']), $my_page, $my_cid);
  return $form;
}

function _postcode_component_selector($node, $max_page = NULL, $disable_cids = [], $exclude_types = ['pagebreak', 'fieldset']) {
  $options = [];
  foreach ($node->webform['components'] as $cid => $component) {
    if (!in_array($component['type'], $exclude_types) && !in_array($cid, $disable_cids)) {
      $options[$cid] = $component['name'];
    }
  }
  $component_list_disabled = empty($options);
  if (!$options) {
    $options = ['' => t('No available components')];
  }
  return [
    '#type' => 'select',
    '#title' => t('Choose component'),
    '#options' => $options,
    '#disabled' => $component_list_disabled,
    '#process' => array_merge(['postcode_update_component_options'], element_info('select')['#process']),
  ];
}

/**
 * Element process callback for the component selector.
 */
function postcode_update_component_options($element, &$form_state, $form) {
  if ($form['#form_id'] == 'form_builder_field_configure') {
    $args = $form_state['build_info']['args'];
    $cache = FormBuilderLoader::instance()->fromCache($args[0], $args[1]);
    $node = node_load($args[1]);
    $options = [];
    $my_id = $form['#_edit_element_id'];
    foreach ($cache->getComponents(node_load($args[1])) as $component) {
      $element_id = $component['form_builder_element_id'];
      if (!in_array($component['type'], ['fieldset', 'pagebreak']) && $element_id != $my_id) {
        $options[$element_id] = $component['name'];
      }
    }
    $element['#options'] = $options;
  }
  return $element;

}

/**
 * Implements _webform_render_[component]().
 */
function _webform_render_postcode($component, $value = NULL, $filter = TRUE) {
  $node = isset($component['nid']) ? node_load($component['nid']) : NULL;

  $element = array(
    '#type' => 'postcode',
    '#title' => $filter ? _webform_filter_xss($component['name']) : $component['name'],
    '#title_display' => $component['extra']['title_display'] ? $component['extra']['title_display'] : 'before',
    '#default_value' => $filter ? _webform_filter_values($component['value'], $node) : $component['value'],
    '#required' => $component['mandatory'],
    '#weight' => $component['weight'],
    '#description' => $filter ? _webform_filter_descriptions($component['extra']['description'], $node) : $component['extra']['description'],
    '#attributes' => $component['extra']['attributes'],
    '#theme_wrappers' => array('webform_element'),
    '#translatable' => array('title', 'description'),
  );
  if ($component['extra']['postcode_country_mode'] == 'component') {
    $cid = $component['extra']['postcode_country_component'];
    $element['#postcode_country_cid'] = $cid;
    $parents = [];
    while ($cid > 0) {
      $c = $node->webform['components'][$cid];
      $parents[] = $c['form_key'];
      $cid = $c['pid'];
    }
    $element['#postcode_country_parents'] = array_merge(['submitted'], array_reverse($parents));
  }
  else {
    $element['#postcode_country'] = $component['extra']['postcode_country'];
  }

  // Add an postcode class for identifying the difference from normal textfields.
  $element['#attributes']['class'][] = 'postcode';

  // Enforce uniqueness.
  if ($component['extra']['unique']) {
    $element['#element_validate'][] = 'webform_validate_unique';
  }

  if (isset($value)) {
    $element['#default_value'] = $value[0];
  }

  if ($component['extra']['disabled']) {
    if ($filter) {
      $element['#attributes']['readonly'] = 'readonly';
    }
    else {
      $element['#disabled'] = TRUE;
    }
  }

  // Change the 'width' option to the correct 'size' option.
  if ($component['extra']['width'] > 0) {
    $element['#size'] = $component['extra']['width'];
  }

  return $element;
}

/**
 * Implements _webform_display_[component]().
 */
function _webform_display_postcode($component, $value, $format = 'html') {
  return [
    '#markup' => isset($value[0]) ? $value[0] : '',
  ];
}

/**
 * Implements _webform_table_component().
 */
function _webform_table_postcode($component, $value) {
  return check_plain(empty($value[0]) ? '' : $value[0]);
}


/**
 * Implements _webform_csv_headers_component().
 */
function _webform_csv_headers_postcode($component, $export_options) {
  $header = array();
  $header[0] = '';
  $header[1] = '';
  $header[2] = $component['name'];
  return $header;
}

/**
 * Implements _webform_csv_data_component().
 */
function _webform_csv_data_postcode($component, $export_options, $value) {
  return empty($value[0]) ? '' : $value[0];
}


/**
 * Implements _webform_form_builder_map_<webform-component>().
 */
function _webform_form_builder_map_postcode() {
  return [
    'form_builder_type' => 'postcode',
    'properties' => [
      'postcode_country' => [
        'storage_parents' => ['extra', 'postcode_country']
      ],
      'postcode_country_mode' => [
        'storage_parents' => ['extra', 'postcode_country_mode']
      ],
      'postcode_country_component' => [
        'storage_parents' => ['extra', 'postcode_country_component']
      ],
    ],
  ];
}

/**
 * Implements _webform_form_builder_properties_<webform-component>().
 *
 * Component specific properties.
 * This is currently broken as the component specific properties are merged into
 * the global property list. That makes it behave the same way as an implementation
 * of hook_form_builder_properties().
 *
 * @see form_builder_webform_form_builder_properties().
 */
function _webform_form_builder_properties_postcode() {
  return [
    'postcode_country' => [
      'form' => '_postcode_country_form_builder_form',
      'submit' => ['_postcode_country_form_builder_form_submit'],
    ],
    'postcode_country_mode' => [],
    'postcode_country_component' => [],
  ];
}

/**
 * Form callback for the newsletter property.
 *
 * @see _webform_form_builder_map_postcode().
 */
function _postcode_country_form_builder_form(&$form_state, $form_type, $element, $property) {
  $edit = _webform_edit_postcode($element['#webform_component']);
  foreach (['country_mode', 'country', 'country_component'] as  $f) {
    $f = "postcode_$f";
    $form[$f] = $edit['validation'][$f];
    $form[$f]['#form_builder']['property_group'] = 'validation';
    $form[$f]['#parents'] = [$f];
  }

  return $form;
}


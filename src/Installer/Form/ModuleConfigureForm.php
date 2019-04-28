<?php

namespace Drupal\contenta_jsonapi\Installer\Form;

use Drupal\contenta_jsonapi\OptionalModulesManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the site configuration form.
 */
class ModuleConfigureForm extends ConfigFormBase {

  /**
   * The plugin manager.
   *
   * @var \Drupal\contenta_jsonapi\OptionalModulesManager
   */
  protected $optionalModulesManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\contenta_jsonapi\OptionalModulesManager $optional_modules_manager
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, OptionalModulesManager $optional_modules_manager) {
    parent::__construct($config_factory);
    $this->optionalModulesManager = $optional_modules_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.contenta_jsonapi.optional_modules')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contenta_jsonapi_module_configure_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Keep calm. You can install all the modules later, too.'),
    ];

    $form['install_modules'] = [
      '#type' => 'container',
    ];

    $providers = $this->optionalModulesManager->getDefinitions();

    static::sortByWeights($providers);

    foreach ($providers as $provider) {
      $instance = $this->optionalModulesManager->createInstance($provider['id']);

      $form['install_modules_' . $provider['id']] = [
        '#type' => 'checkbox',
        '#title' => $provider['label'],
        '#description' => isset($provider['description']) ? $provider['description'] : '',
        '#default_value' => isset($provider['standardlyEnabled']) ? $provider['standardlyEnabled'] : 0,
      ];

      $form = $instance->buildForm($form, $form_state);
    }

    $form['#title'] = $this->t('Install & configure modules');
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* Get forms from build info to allow for drush overrides
    on default form_state->getValue() that are sent */
    $build_info = $form_state->getBuildInfo();
    $install_state = $build_info['args'][0]['forms'];

    $install_state['contenta_jsonapi_additional_modules'] = [];
    // Determine form state based off override existance
    if (isset($install_state['form_state_values'])) {
      $install_state['form_state_values'] += $form_state->getValues();
    } else {
      $install_state['form_state_values'] = $form_state->getValues();
    }

    // Iterate over the form state values to determine modules to install
    foreach ($install_state['form_state_values'] as $key => $value) {
      // Only operate on any values that have `install_modules_`
      if (strpos($key, 'install_modules_') !== false && $value) {
        // Add module to the additional list
        $install_state['contenta_jsonapi_additional_modules'][] =
          ltrim($key, 'install_modules_');
      }
    }

    $build_info['args'][0]['forms'] = $install_state;
    $form_state->setBuildInfo($build_info);
  }

  /**
   * Returns a sorting function to sort an array by weights.
   *
   * If an array element doesn't provide a weight, it will be set to 0.
   * If two elements have the same weight, they are sorted by label.
   *
   * @param array $array
   *   The array to be sorted.
   */
  private static function sortByWeights(array &$array) {
    uasort($array, function ($a, $b) {
      $a_weight = isset($a['weight']) ? $a['weight'] : 0;
      $b_weight = isset($b['weight']) ? $b['weight'] : 0;

      if ($a_weight == $b_weight) {
        return ($a['label'] > $b['label']) ? 1 : -1;
      }
      return ($a_weight > $b_weight) ? 1 : -1;
    });
  }

}

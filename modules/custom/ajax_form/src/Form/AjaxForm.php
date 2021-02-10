<?php

namespace Drupal\ajax_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

  /**
   * Extend FormBase Class
   */
class AjaxForm extends FormBase{

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['color'] = [
      '#title' => $this->t("Choose Your Favourite Color"),
      '#type' => 'select',
      '#options' => [
        'black' => 'black',
        'red' => 'red',
        'green' => 'green',
        'orange' => 'orange',
      ],
      '#ajax' => [
        // #ajax has two required keys: callback and wrapper.
        // 'callback' is a function that will be called when this element
        // changes.
        'callback' => '::promptCallback',
        // 'wrapper' is the HTML id of the page element that will be replaced.
        'wrapper' => 'replace-container',
      ],
    ];

    // The 'replace-textfield-container' container will be replaced whenever
    // 'changethis' is updated.
    $form['replace_container'] = [
      '#type' => 'markup',
      '#attributes' => ['id' => 'replace-textfield-container'],
    ];

    // An AJAX request calls the form builder function for every change.
    // We can change how we build the form based on $form_state.
    $value = $form_state->getValue('color');
    // The getValue() method returns NULL by default if the form element does
    // not exist. It won't exist yet if we're building it for the first time.
    if ($value !== NULL) {
      $form['replace_textfield_container']['replace_textfield']['#description'] =
        $this->t("Say why you chose '@value'", ['@value' => $value]);
    }
    return $form;
  }

   /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op. Our form doesn't need a submit handler, because the form is never
    // submitted. We add the method here so we fulfill FormInterface.
  }

  /**
   * Handles switching the available regions based on the selected theme.
   */
  public function promptCallback($form, FormStateInterface $form_state) {
    return $form['replace_textfield_container'];
  }
}
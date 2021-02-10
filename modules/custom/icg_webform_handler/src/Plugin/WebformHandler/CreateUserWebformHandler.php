<?php
namespace Drupal\icg_webform_handler\Plugin\WebformHandler;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\user\Entity\User;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;


/**
 * Create a new User entity from a webform submission.
 *
 * @WebformHandler(
 *   id = "Icegagte Create a User",
 *   label = @Translation("Create a User"),
 *   category = @Translation("Entity Creation"),
 *   description = @Translation("Creates a new user from Webform Submissions."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class CreateUserWebformHandler extends WebformHandlerBase{
  /**
   * {@inheritdoc}
   */

  // Function to be fired after submitting the Webform.
  public function postSave(\Drupal\webform\WebformSubmissionInterface $webform_submission, $update = TRUE)
  {
    $values = $webform_submission->getData();
    dpr($values);
  echo 'User Created Here';
  exit;
  }

  /**
   * Creates a new user account based on a list of values.
   *
   * This does NOT save the user entity. This happens in the postSave()
   * function.
   *
   * @param array $user_data
   *   Associative array of user data, keyed by user entity property/field.
   *
   * @return \Drupal\user\UserInterface
   *   The user account entity, populated with values.
   */
  protected function createUserAccount(array $user_data) {
    $lang = $this->languageManager->getCurrentLanguage()->getId();
    $mail = $user_data['mail'];
    $default_user_data = [
      'init' => $mail,
      'name' => str_replace('@', '.', $mail),
      'pass' => user_password(),
      'langcode' => $lang,
      'preferred_langcode' => $lang,
      'preferred_admin_langcode' => $lang,
      'roles' => array_keys(array_filter($this->configuration['create_user']['roles'])) ?? [],
    ];
    $user_data = array_merge($default_user_data, $user_data);

    $account = User::create();
    $account->enforceIsNew();

    foreach ($user_data as $name => $value) {
      $account->set($name, $value);
    }

    // Does the account require admin approval?
    $admin_approval = $this->configuration['create_user']['admin_approval'];
    if ($admin_approval) {
      // The account registration requires further approval.
      $account->block();
    }
    else {
      // No further admin approval is required, log the user in.
      $account->activate();
    }

    return $account;
  }
}

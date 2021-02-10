<?php
namespace Drupal\icg_content_access\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block with Icg Content access.
 *
 * @Block(
 *   id = "icg_content_access_block",
 *   admin_label = @Translation("Icg Content Access block"),
 * )
 */
class IcgContentAccessBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */

  public function build() {
    return [
      '#markup' => $this->getUserContent(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['icg_content_block_settings'] = $form_state->getValue('icg_content_block_settings');
  }

  public function getUserContent(){
    if (\Drupal::currentUser()->isAnonymous()) {
      return FALSE;
    }

   $role_maping = [
     'content_creater' => 20,
     'content_moderator' => 21,
   ];

      $current_user = \Drupal::currentUser();
      $roles = $current_user->getRoles();
      // Check if user is an admin.
      $tids = [];
        if (in_array('administrator', $roles)) {
          $tids = array_values(($role_maping));
        }
        else {
        foreach($roles as $value) {
          if(!empty($role_maping[$value])) {
           $tids[] = $role_maping[$value];
          }
        }
    }

    $database = \Drupal::database();
    $sql = "SELECT DISTINCT node_field_data.langcode AS node_field_data_langcode,
     node_field_data.created AS node_field_data_created,
     node_field_data.nid AS nid,
     node_field_data.title AS title
      FROM {node_field_data} node_field_data
      INNER JOIN {node__field_user_role} node__field_user_role ON node_field_data.nid = node__field_user_role.entity_id AND node__field_user_role.deleted = '0'
      WHERE ((node__field_user_role.field_user_role_target_id IN (:tids[])))
      AND (node_field_data.status = '1')
      ORDER BY node_field_data_created DESC LIMIT 30";
    $query = $database->query($sql, [':tids[]'=> $tids]);
    $result = $query->fetchAll();
 foreach($result as $obj) {
      $items[]= $obj->title;
    }

    $content = [
  '#theme' => 'item_list',
  '#list_type' => 'ul',
  '#title' => '',
  '#items' => $items,
  '#attributes' => ['class' => 'view-content'],
  '#wrapper_attributes' => ['class' => 'container'],
];
//     $view = \Drupal::Views::getView('role_by_content_access');
// $render_array = $view->buildRenderable('role_by_content', 21);
    return render($content);
  }
}

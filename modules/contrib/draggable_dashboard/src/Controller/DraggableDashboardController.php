<?php

namespace Drupal\draggable_dashboard\Controller;

use Drupal\block\Entity\Block;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\draggable_dashboard\Entity\DashboardEntity;
use Drupal\draggable_dashboard\Entity\DashboardEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for draggable dashboards.
 */
class DraggableDashboardController extends ControllerBase {

  /**
   * DraggableDashboardController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the draggable dashboard administration overview page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function adminOverview(Request $request) {

    $rows = [];
    $storage = $this->entityTypeManager->getStorage('dashboard_entity');
    $header = [
      $this->t('Title'),
      $this->t('Description'),
      $this->t('Operations'),
    ];

    $dashboards = $storage->getQuery()->execute();

    foreach ($dashboards as $dashboardID) {
      $dashboard = $storage->load($dashboardID);
      $row = [];
      $row[] = $dashboard->get('title');
      $row[] = $dashboard->get('description');
      $links = [
        'manage' => [
          'title' => $this->t('Manage Blocks'),
          'url' => Url::fromRoute('draggable_dashboard.manage_dashboard', ['did' => $dashboard->id()]),
        ],
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('draggable_dashboard.edit_dashboard', ['did' => $dashboard->id()]),
        ],
        'delete' => [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('draggable_dashboard.delete_dashboard', ['did' => $dashboard->id()]),
        ],
      ];
      $row[] = ['data' => ['#type' => 'operations', '#links' => $links]];
      $rows[] = $row;
    }

    $form['dashboard_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No draggable dashboards available.'),
      '#weight' => 120,
    ];

    return $form;
  }

  /**
   * Assign block to a region.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public static function assignBlock(array &$form, FormStateInterface $form_state) {
    // Save block entity.
    $settings = $form_state->getValue('settings');
    $region = $form_state->getValue('region');
    $dashboard_id = $form_state->getValue('dashboard_id');
    $block_id = $form_state->getValue('id');
    $obj = $form_state->getBuildInfo()['callback_object'];
    /** @var \Drupal\block\Entity\Block $block */
    $block = $obj->getEntity();
    $block->set('id', $block_id);
    $block->set('region', DashboardEntityInterface::BASE_REGION_NAME);
    $block->set('settings', $settings);
    $block->enable();
    $block->save();
    /** @var \Drupal\draggable_dashboard\Entity\DashboardEntity $dashboard */
    $dashboard = DashboardEntity::load($dashboard_id);
    $blocks = json_decode($dashboard->get('blocks'), TRUE);
    $relationFounded = FALSE;
    if (!empty($blocks)) {
      foreach ($blocks as $key => $relation) {
        if ($relation['bid'] == $block_id) {
          $blocks[$key]['cln'] = (int) $region;
          $relationFounded = TRUE;
          break;
        }
      }
    }
    if (!$relationFounded) {
      $blocks[] = [
        'bid' => $block_id,
        'cln' => (int) $region,
        'position' => 0,
      ];
    }
    // Save relation.
    $dashboard->set('blocks', json_encode($blocks))->save();
    // Redirect to manage blocks screen.
    $form_state->setRedirect('draggable_dashboard.manage_dashboard', ['did' => $dashboard_id]);
  }

  /**
   * Delete block.
   *
   * @param string $did
   *   Dashboard Id.
   * @param string $bid
   *   Block Id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response back to dashboard.
   */
  public function deleteBlock($did, $bid) {
    /** @var \Drupal\draggable_dashboard\Entity\DashboardEntity $dashboard */
    $dashboard = $this->entityTypeManager->getStorage('dashboard_entity')->load($did);
    $blocks = json_decode($dashboard->get('blocks'), TRUE);
    if (!empty($blocks)) {
      foreach ($blocks as $key => $relation) {
        if ($relation['bid'] == $bid) {
          $block = Block::load($relation['bid']);
          $block->delete();
          unset($blocks[$key]);
        }
      }
    }
    // Delete block relation.
    $dashboard->set('blocks', json_encode($blocks))->save();
    $manageURL = Url::fromRoute('draggable_dashboard.manage_dashboard', ['did' => $did]);
    $response = new RedirectResponse($manageURL->toString());
    return $response->send();
  }

  /**
   * Returns a set of nodes' last read timestamps.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function updateBlockPositions(Request $request) {
    if ($this->currentUser()->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    $did = $request->request->get('did');
    $blocks = $request->request->get('blocks');

    /** @var \Drupal\draggable_dashboard\Entity\DashboardEntity $dashboard */
    $dashboard = $this->entityTypeManager->getStorage('dashboard_entity')->load($did);
    $dBlocks = json_decode($dashboard->get('blocks'), TRUE);

    if (!isset($blocks) && empty($dashboard)) {
      throw new NotFoundHttpException();
    }

    // Update dashboard blocks positions.
    foreach ($dBlocks as $key => $dBlock) {
      foreach ($blocks as $bid => $relation) {
        if ($dBlock['bid'] == $bid) {
          $dBlocks[$key]['cln'] = $relation['region'];
          $dBlocks[$key]['position'] = $relation['order'];
        }
      }
    }
    $dashboard->set('blocks', json_encode($dBlocks))->save();

    return new JsonResponse(['success' => TRUE]);
  }

}

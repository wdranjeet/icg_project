<?php

namespace Drupal\draggable_dashboard\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a draggable block with a simple text.
 *
 * @Block(
 *   id = "draggable_dashboard_block",
 *   admin_label = @Translation("Draggable Block"),
 *   deriver = "Drupal\draggable_dashboard\Plugin\Block\DraggableBlockDeriver"
 * )
 */
class DraggableBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * Current dashboard.
   *
   * @var \Drupal\draggable_dashboard\Entity\DashboardEntityInterface
   */
  protected $dashboard;

  /**
   * Block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * DraggableBlock constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Current Route Matcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   Title resolver.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager
   *   Block manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, RouteMatchInterface $route_match, ThemeManagerInterface $theme_manager, TitleResolverInterface $title_resolver, BlockManagerInterface $block_plugin_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeManager = $theme_manager;
    $this->titleResolver = $title_resolver;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_plugin_manager;
    $config = $this->getConfiguration();
    $did = preg_replace('%[^\d]%', '', $config['id']);
    $this->dashboard = $this->entityTypeManager->getStorage('dashboard_entity')->load($did);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('theme.manager'),
      $container->get('title_resolver'),
      $container->get('plugin.manager.block'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!empty($this->dashboard)) {
      $columns = [];
      $all_blocks = json_decode($this->dashboard->get('blocks'), TRUE);
      $max_blocks = 0;
      // Create dashboard columns.
      for ($i = 1; $i <= $this->dashboard->get('columns'); $i++) {
        $blocks = [];
        if (!empty($all_blocks)) {
          foreach ($all_blocks as $relation) {
            if ($relation['cln'] == $i) {
              $blocks[] = $relation;
            }
          }
        }
        if (!empty($blocks)) {
          if ($max_blocks < count($blocks)) {
            $max_blocks = count($blocks);
          }
          foreach ($blocks as $relation) {
            /** @var \Drupal\block\BlockInterface $block */
            $block = $this->entityTypeManager->getStorage('block')->load($relation['bid']);
            if (empty($block)) {
              continue;
            }
            // You can hard code configuration or you load from settings.
            $config = $block->getPlugin()->getConfiguration();
            $isTitleVisible = empty($config['label_display']) ? FALSE : TRUE;
            $config['label_display'] = 0;

            $plugin_block = $this->blockManager->createInstance($block->getPluginId(), $config);

            if ($plugin_block instanceof TitleBlockPluginInterface) {
              $pageTitle = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $this->routeMatch->getRouteObject());
              if ($pageTitle) {
                $plugin_block->setTitle($pageTitle);
              }
            }

            // Some blocks might implement access check.
            // Return empty render array if user doesn't have access.
            if ($plugin_block->access($this->currentUser)) {

              $render = $this->entityTypeManager
                ->getViewBuilder('block')
                ->view($block);

              if (!isset($render['#lazy_builder'])) {
                unset($render['#pre_render']);
                $content = $plugin_block->build();
                $render['content'] = $content;
              }
              else {
                unset($render['#lazy_builder']);
                $content = $plugin_block->build();
                $render['content'] = $content;
              }
              $columns[$i][] = [
                'id' => $relation['bid'],
                'title' => $isTitleVisible ? $config['label'] : '',
                'view' => [
                  'data' => $render,
                ],
              ];
            }
          }
        }
      }
    }

    $build = [
      '#theme' => 'draggable_dashboard_block',
      '#dashboard' => $this->dashboard,
      '#columns' => $columns,
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => [
          'draggable_dashboard/draggable_dashboard.frontend',
        ],
      ],
    ];

    if ($this->currentUser->hasPermission('administer_draggable_dashboard')) {
      $build['#attached']['library'][] = 'draggable_dashboard/draggable_dashboard.draggable';
    }

    return $build;
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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

}

<?php

namespace Drupal\webg_rest_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a WebG Resource
 *
 * @RestResource(
 *   id = "webg_rest_api",
 *   label = @Translation("WebG Resource"),
 *   uri_paths = {
 *     "canonical" = "/webg_rest_api/api"
 *   }
 * )
 */
class WebgRestApi extends ResourceBase {


  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get(){
      $response = ['rest_message' => 'Rest Message Resource'];
     return new ResourceResponse($response);
  }
}
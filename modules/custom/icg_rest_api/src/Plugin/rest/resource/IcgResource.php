<?php

namespace Drupal\icg_rest_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a Icg Resource
 *
 * @RestResource(
 *   id = "icg_rest_api",
 *   label = @Translation("Icegate Resource"),
 *   uri_paths = {
 *     "canonical" = "/icg_rest_api/api"
 *   }
 * )
 */
class IcgResouce extends ResourceBase {

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */

 public function get() {
      $response = ['message' => 'This is Icegate Demo Resource' ];
     return new ResourceResponse($response);
 }

}

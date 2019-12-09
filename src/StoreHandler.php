<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Store handler.
 */
class StoreHandler implements StoreHandlerInterface {

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('mailchimp_ecommerce');
  }

  /**
   * @inheritdoc
   */
  public function getStore($store_id) {
    $store = NULL;
    try {
      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $store = $mc_ecommerce->getStore($store_id);
    }
    catch (\Exception $e) {
      if ($e->getCode() == 404) {
        // Store doesn't exist; no need to log an error.
      }
      else {
        $this->logger->error('Unable to get store: %message', ['%message' => $e->getMessage()]);
        drupal_set_message($e->getMessage(), 'error');
      }
    }

    return $store;
  }

  /**
   * @inheritdoc
   */
  public function addStore($store_id, $store, $platform) {
    try {
      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

      $parameters = [
        'platform' => $platform,
      ];

      $mc_store = $mc_ecommerce->addStore($store_id, $store, $parameters);

      \Drupal::moduleHandler()->invokeAll('mailchimp_ecommerce_add_store', [$mc_store]);
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to add a new store: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function updateStore($store_id, $name, $currency_code, $platform) {
    try {
      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

      $parameters = [
        'platform' => $platform,
      ];

      $mc_ecommerce->updateStore($store_id, $name, $currency_code, $parameters);
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to update a store: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

}

<?php

namespace Drupal\mailchimp_ecommerce;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;

/**
 * Product handler.
 */
class ProductHandler implements ProductHandlerInterface {

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
  public function addProduct($product_id, $title, $url, $image_url, $description, $type, $variants) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a product without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

      $mc_ecommerce->addProduct($store_id, (string) $product_id, $title, $url, $variants, [
        'description' => $description,
        'type' => $type,
        'url' => $url,
        'image_url' => $image_url,
      ]);
    }
    catch (\Exception $e) {

      //TODO: If add fails with product exists error code, run an update here.
      $this->logger->error($e->getMessage());
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function updateProduct($product, $title, $url, $image_url, $description, $type, $variants) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot update a product without a store ID.');
      }

      // Ubercart doesn't have product objects. So, just pass the ID.
      if (!is_string($product)) {
        $product_id = $product->get('product_id')->value;
      }
      else {
        $product_id = $product;
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

      // Update the base product with no variant.
      $mc_ecommerce->updateProduct($store_id, $product_id, $variants, [
          'title' => $title,
          'description' => $description,
          'type' => $type,
          'url' => $url,
          'image_url' => $image_url,
      ]);
    }
    catch (\Exception $e) {
      if ($e->getCode() == 404) {
        drupal_set_message('This product doesn\'t exist in Mailchimp. Please sync all your products.');
      }
      else {
        // An actual error occurred; pass on the exception.
        $this->logger->error('Unable to update product: %message', ['%message' => $e->getMessage()]);
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function deleteProduct($product_id) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot delete a product without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->deleteProduct($store_id, $product_id);
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to delete product: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function addProductVariant($product_id, $product_variant_id, $title, $url, $image_url, $sku, $price, $stock) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot add a product variant without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $mc_ecommerce->addProductVariant($store_id, $product_id, [
        'id' => $product_variant_id,
        'title' => $title,
        'url' => $url,
        'image_url' => $image_url,
        'sku' => $sku,
        'price' => $price,
        'inventory_quantity' => $stock,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to add product variant: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * @inheritdoc
   */
  public function getProductVariant($product_id, $product_variant_id) {
    $product_variant = NULL;
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot get a product variant without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');
      $product_variant = $mc_ecommerce->getProductVariant($store_id, $product_id, $product_variant_id);

      // Mailchimp will return a product variant object even if the variant
      // doesn't exist. Checking for an empty SKU is a reliable way to
      // determine if a product variant doesn't exist in Mailchimp.
      if (empty($product_variant->sku)) {
        return NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to get product variant: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }

    return $product_variant;
  }

  /**
   * @inheritdoc
   */
  public function getProductImageUrl($product) {
    $image_url = '';

    $config = \Drupal::config('mailchimp_ecommerce.settings');
    $image_field_name = $config->get('product_image');

    if (isset($product->{$image_field_name}->entity)) {
      $image_url = $product->{$image_field_name}->entity->url();
    }

    return $image_url;
  }

  /**
   * @inheritdoc
   */
  public function getNodeImageUrl($product) {
    $image_url = '';

    $config = \Drupal::config('mailchimp_ecommerce.settings');
    $image_field_name = $config->get('product_image');

    if (isset($product->{$image_field_name}->entity)) {
      $image_url = $product->{$image_field_name}->entity->url();
    }

    return $image_url;
  }


  /**
   * @inheritdoc
   */
  public function deleteProductVariant($product_id, $product_variant_id) {
    try {
      $store_id = mailchimp_ecommerce_get_store_id();
      if (empty($store_id)) {
        throw new \Exception('Cannot delete a product variant without a store ID.');
      }

      /* @var \Mailchimp\MailchimpEcommerce $mc_ecommerce */
      $mc_ecommerce = mailchimp_get_api_object('MailchimpEcommerce');

      try {
        $variants = $mc_ecommerce->getProductVariants($store_id, $product_id);

        // Delete the variant if the product contains multiple variants.
        if ($variants->total_items > 1) {
          $mc_ecommerce->deleteProductVariant($store_id, $product_id, $product_variant_id);
        }
        else {
          // Delete the product if the product has only one variant.
          $mc_ecommerce->deleteProduct($store_id, $product_id);
        }
      }
      catch (\Exception $e) {
        if ($e->getCode() == 404) {
          // This product isn't in Mailchimp.
          return;
        }
        else {
          // An actual error occurred; pass on the exception.
          throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to delete product variant: %message', ['%message' => $e->getMessage()]);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Returns product variant data formatted for use with Mailchimp.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The Commerce Product.
   *
   * @return array
   *   Array of product variant data.
   */
  public function buildProductVariants(Product $product) {
    $variants = [];

    $product_variations = $product->get('variations')->getValue();
    if (!empty($product_variations)) {
      foreach ($product_variations as $variation_data) {
        /** @var ProductVariation $product_variation */
        $product_variation = ProductVariation::load($variation_data['target_id']);
        $url = $this->buildProductUrl($product);

        $variant = [
          'id' => $product_variation->id(),
          'title' => $product->getTitle(),
          'url' => $url,
          'sku' => $product_variation->getSku(),
          'stock' => 100,
        ];

        $price = $product_variation->getPrice();
        if (!empty($price)) {
          $variant['price'] = $price->getNumber();
        }
        else {
          $variant['price'] = 0;
        }

        // Product variations contain a currency code, but Mailchimp requires
        // store currency to be set at the point when the store is created, so
        // the variation currency is ignored here.
        // TODO: Make sure the user knows this through a form hint.

        $variants[] = $variant;
      }
    }

    return $variants;
  }

  /**
   * Build Mailchimp product values from an Ubercart product node.
   *
   * @param Node $node
   *   The Ubercart 'product' type node.
   *
   * @return array
   *   Array of product values for use with Mailchimp.
   */
  function buildProductFromNode(Node $node) {

    $url = $this->buildNodeUrl($node);
    $image_url = $this->getNodeImageUrl($node);

    $variant = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'url' => $url,
      'image_url' => $image_url,
      'sku' => $node->model->value,
      'price' => $node->price->value,
    ];

    $product = array(
      'id' => $node->id(),
      'variant_id' => $node->id(),
      'sku' => $node->model->value,
      'title' => $node->getTitle(),
      'url' => $url,
      'image_url' => $image_url,
      'description' => $node->body->value,
      'price' => $node->price->value,
      'type' => $node->getType(),
      'variants' => [$variant],
    );

    return $product;
  }

  /**
   * Creates a URL from a product.
   *
   * @param Product $product
   *   The Commerce product object.
   *
   * @return string
   *   The URL of the product.
   */
  public function buildProductUrl($product) {
    global $base_url;

    // Mailchimp will accept an empty string if no URL is available.
    $full_url = '';

    $url = $product->toURL();
    if (!empty($url)) {
      $full_url = $base_url . $url->toString();
    }

    return $full_url;
  }

  /**
   * Creates a product URL from a node.
   *
   * @param Node $product
   *   The Commerce product object.
   *
   * @return string
   *   The URL of the product.
   */
  public function buildNodeUrl(Node $product) {
    global $base_url;

    // Mailchimp will accept an empty string if no URL is available.
    $full_url = '';

    $url = $product->toUrl()->toString();
    if (!empty($url)) {
      $full_url = $base_url . $url;
    }

    return $full_url;
  }

}

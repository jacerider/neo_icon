<?php

namespace Drupal\neo_icon;

/**
 * Interface for an icon item.
 */
interface IconInterface {

  /**
   * Get the icon library.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface
   *   The icon library.
   */
  public function getLibrary();

  /**
   * Get the selector.
   *
   * @return string
   *   The selector.
   */
  public function getSelector();

  /**
   * Get the name.
   *
   * @return string
   *   The name.
   */
  public function getName();

  /**
   * Get the code.
   *
   * @return string
   *   The code.
   */
  public function getCode();

  /**
   * Get the name.
   *
   * @return array
   *   The codes.
   */
  public function getCodes();

  /**
   * Get the hex.
   *
   * @return string
   *   The hex.
   */
  public function getHex();

  /**
   * Get wrapping tag.
   *
   * @return string
   *   The wrapping tag.
   */
  public function getTag();

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The children.
   */
  public function getChildren();

  /**
   * Return as render array.
   *
   * @return array
   *   The renderable array.
   */
  public function render();

}

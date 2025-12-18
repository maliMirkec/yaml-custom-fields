<?php
/**
 * Service Container
 * Simple dependency injection container for managing plugin services
 */

namespace YamlCF\Core;

class ServiceContainer {
  private $services = [];
  private $factories = [];

  /**
   * Register a service factory
   *
   * @param string $name Service name
   * @param callable $factory Factory function that creates the service
   * @return void
   */
  public function register($name, callable $factory) {
    $this->factories[$name] = $factory;
  }

  /**
   * Get a service instance (singleton pattern)
   *
   * @param string $name Service name
   * @return mixed Service instance
   * @throws \Exception If service not found
   */
  public function get($name) {
    // Return existing instance if already created
    if (isset($this->services[$name])) {
      return $this->services[$name];
    }

    // Create new instance using factory
    if (!isset($this->factories[$name])) {
      throw new \Exception("Service not found: {$name}");
    }

    $this->services[$name] = call_user_func($this->factories[$name], $this);
    return $this->services[$name];
  }

  /**
   * Check if service is registered
   *
   * @param string $name Service name
   * @return bool
   */
  public function has($name) {
    return isset($this->factories[$name]);
  }

  /**
   * Set a service instance directly (useful for testing)
   *
   * @param string $name Service name
   * @param mixed $service Service instance
   * @return void
   */
  public function set($name, $service) {
    $this->services[$name] = $service;
  }
}

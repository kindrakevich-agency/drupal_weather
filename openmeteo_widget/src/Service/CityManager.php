<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for managing cities.
 */
final class CityManager {

  /**
   * Constructs a CityManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Gets all configured cities.
   *
   * @return array<string, array<string, mixed>>
   *   Array of cities keyed by ID.
   */
  public function getAllCities(): array {
    $config = $this->configFactory->get('openmeteo_widget.settings');
    return $config->get('cities') ?? [];
  }

  /**
   * Gets a city by ID.
   *
   * @param string $cityId
   *   The city ID.
   *
   * @return array<string, mixed>|null
   *   The city data or NULL if not found.
   */
  public function getCity(string $cityId): ?array {
    $cities = $this->getAllCities();
    return $cities[$cityId] ?? NULL;
  }

  /**
   * Adds a new city.
   *
   * @param string $name
   *   The city name.
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return string
   *   The new city ID.
   */
  public function addCity(string $name, float $latitude, float $longitude): string {
    $config = $this->configFactory->getEditable('openmeteo_widget.settings');
    $cities = $config->get('cities') ?? [];

    $cityId = $this->generateCityId($name);

    $cities[$cityId] = [
      'id' => $cityId,
      'name' => $name,
      'latitude' => $latitude,
      'longitude' => $longitude,
    ];

    $config->set('cities', $cities)->save();

    return $cityId;
  }

  /**
   * Updates an existing city.
   *
   * @param string $cityId
   *   The city ID.
   * @param string $name
   *   The city name.
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function updateCity(string $cityId, string $name, float $latitude, float $longitude): bool {
    $config = $this->configFactory->getEditable('openmeteo_widget.settings');
    $cities = $config->get('cities') ?? [];

    if (!isset($cities[$cityId])) {
      return FALSE;
    }

    $cities[$cityId] = [
      'id' => $cityId,
      'name' => $name,
      'latitude' => $latitude,
      'longitude' => $longitude,
    ];

    $config->set('cities', $cities)->save();

    return TRUE;
  }

  /**
   * Removes a city.
   *
   * @param string $cityId
   *   The city ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function removeCity(string $cityId): bool {
    $config = $this->configFactory->getEditable('openmeteo_widget.settings');
    $cities = $config->get('cities') ?? [];

    if (!isset($cities[$cityId])) {
      return FALSE;
    }

    unset($cities[$cityId]);
    $config->set('cities', $cities)->save();

    return TRUE;
  }

  /**
   * Gets the default city (first configured city).
   *
   * @return array<string, mixed>|null
   *   The default city or NULL if none configured.
   */
  public function getDefaultCity(): ?array {
    $cities = $this->getAllCities();
    if (empty($cities)) {
      return NULL;
    }

    return reset($cities);
  }

  /**
   * Generates a unique city ID from name.
   *
   * @param string $name
   *   The city name.
   *
   * @return string
   *   The generated city ID.
   */
  private function generateCityId(string $name): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name) ?? '');
    $id = $base;
    $counter = 1;

    $cities = $this->getAllCities();
    while (isset($cities[$id])) {
      $id = $base . '_' . $counter++;
    }

    return $id;
  }

}

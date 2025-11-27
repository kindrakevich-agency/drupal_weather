<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Service for caching weather data.
 */
final class WeatherCache {

  /**
   * Cache lifetime in seconds (3 hours).
   */
  private const CACHE_LIFETIME = 10800;

  /**
   * Constructs a WeatherCache object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\openmeteo_widget\Service\OpenMeteoClient $client
   *   The Open-Meteo client.
   */
  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OpenMeteoClient $client,
  ) {}

  /**
   * Gets weather data from cache or fetches fresh data.
   *
   * @param string $cityId
   *   The city ID.
   * @param float $latitude
   *   The city latitude.
   * @param float $longitude
   *   The city longitude.
   * @param bool $forceFresh
   *   Whether to force fresh data fetch.
   *
   * @return array<string, mixed>|null
   *   The weather data or NULL on failure.
   */
  public function getWeather(string $cityId, float $latitude, float $longitude, bool $forceFresh = FALSE): ?array {
    $config = $this->configFactory->get('openmeteo_widget.settings');
    $cachingEnabled = $config->get('enable_caching') ?? TRUE;

    if (!$cachingEnabled || $forceFresh) {
      return $this->fetchAndCache($cityId, $latitude, $longitude);
    }

    $cacheKey = $this->getCacheKey($cityId);
    $cached = $this->cache->get($cacheKey);

    if ($cached && $cached->data) {
      // Check if cache is still valid.
      if ($this->isCacheValid($cached)) {
        return $cached->data;
      }
    }

    // Cache miss or expired - fetch fresh data.
    return $this->fetchAndCache($cityId, $latitude, $longitude);
  }

  /**
   * Fetches fresh weather data and caches it.
   *
   * @param string $cityId
   *   The city ID.
   * @param float $latitude
   *   The city latitude.
   * @param float $longitude
   *   The city longitude.
   *
   * @return array<string, mixed>|null
   *   The weather data or NULL on failure.
   */
  public function fetchAndCache(string $cityId, float $latitude, float $longitude): ?array {
    $data = $this->client->getWeatherData($latitude, $longitude);

    if ($data !== NULL) {
      $cacheKey = $this->getCacheKey($cityId);
      $expiry = $this->time->getRequestTime() + self::CACHE_LIFETIME;
      $this->cache->set($cacheKey, $data, $expiry);
    }

    return $data;
  }

  /**
   * Clears cached weather data for a specific city.
   *
   * @param string $cityId
   *   The city ID.
   */
  public function clearCity(string $cityId): void {
    $cacheKey = $this->getCacheKey($cityId);
    $this->cache->delete($cacheKey);
  }

  /**
   * Clears all weather cache.
   */
  public function clearAll(): void {
    $this->cache->deleteAll();
  }

  /**
   * Gets cache metadata for a city.
   *
   * @param string $cityId
   *   The city ID.
   *
   * @return array<string, mixed>|null
   *   Cache metadata or NULL if not cached.
   */
  public function getCacheMetadata(string $cityId): ?array {
    $cacheKey = $this->getCacheKey($cityId);
    $cached = $this->cache->get($cacheKey);

    if (!$cached) {
      return NULL;
    }

    return [
      'created' => $cached->created ?? NULL,
      'expire' => $cached->expire ?? NULL,
      'valid' => $this->isCacheValid($cached),
      'age' => $this->time->getRequestTime() - ($cached->created ?? 0),
    ];
  }

  /**
   * Checks if cached data is still valid.
   *
   * @param object $cached
   *   The cached object.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  private function isCacheValid(object $cached): bool {
    if (!isset($cached->expire)) {
      return FALSE;
    }

    return $this->time->getRequestTime() < $cached->expire;
  }

  /**
   * Generates cache key for a city.
   *
   * @param string $cityId
   *   The city ID.
   *
   * @return string
   *   The cache key.
   */
  private function getCacheKey(string $cityId): string {
    return 'openmeteo_widget:weather:' . $cityId;
  }

  /**
   * Gets the last cache update time.
   *
   * @return int|null
   *   The timestamp or NULL if never run.
   */
  public function getLastUpdateTime(): ?int {
    $config = $this->configFactory->get('openmeteo_widget.settings');
    return $config->get('last_cron_run');
  }

  /**
   * Updates the last cache update time.
   */
  public function updateLastUpdateTime(): void {
    $config = $this->configFactory->getEditable('openmeteo_widget.settings');
    $config->set('last_cron_run', $this->time->getRequestTime())->save();
  }

}

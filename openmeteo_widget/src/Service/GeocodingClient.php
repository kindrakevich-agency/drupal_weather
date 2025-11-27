<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for geocoding city searches.
 */
final class GeocodingClient {

  /**
   * The Open-Meteo Geocoding API base URL.
   */
  private const GEOCODING_API_URL = 'https://geocoding-api.open-meteo.com/v1/search';

  /**
   * Constructs a GeocodingClient object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Searches for cities by name.
   *
   * @param string $query
   *   The search query.
   * @param int $limit
   *   Maximum number of results to return.
   *
   * @return array<int, array<string, mixed>>
   *   Array of city results with name, country, latitude, longitude.
   */
  public function searchCities(string $query, int $limit = 10): array {
    if (strlen($query) < 2) {
      return [];
    }

    $url = self::GEOCODING_API_URL . '?' . http_build_query([
      'name' => $query,
      'count' => $limit,
      'language' => 'en',
      'format' => 'json',
    ]);

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 5,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);

      if (empty($data['results'])) {
        return [];
      }

      return $this->processResults($data['results']);
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('openmeteo_widget')->error(
        'Failed to search cities: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
    catch (\JsonException $e) {
      $this->loggerFactory->get('openmeteo_widget')->error(
        'Failed to parse geocoding data: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Processes raw geocoding results.
   *
   * @param array<int, array<string, mixed>> $results
   *   The raw API results.
   *
   * @return array<int, array<string, mixed>>
   *   Processed results.
   */
  private function processResults(array $results): array {
    $processed = [];

    foreach ($results as $result) {
      $cityName = $result['name'] ?? '';
      $country = $result['country'] ?? '';
      $admin1 = $result['admin1'] ?? '';

      // Build full location name.
      $parts = array_filter([$cityName, $admin1, $country]);
      $fullName = implode(', ', $parts);

      $processed[] = [
        'label' => $fullName,
        'value' => $fullName,
        'city_name' => $cityName,
        'country' => $country,
        'admin1' => $admin1,
        'latitude' => (float) ($result['latitude'] ?? 0),
        'longitude' => (float) ($result['longitude'] ?? 0),
        'population' => $result['population'] ?? 0,
      ];
    }

    return $processed;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for communicating with Open-Meteo API.
 */
final class OpenMeteoClient {

  /**
   * The Open-Meteo API base URL.
   */
  private const API_BASE_URL = 'https://api.open-meteo.com/v1/forecast';

  /**
   * Constructs an OpenMeteoClient object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Fetches weather data for a city.
   *
   * @param float $latitude
   *   The city latitude.
   * @param float $longitude
   *   The city longitude.
   *
   * @return array<string, mixed>|null
   *   The weather data or NULL on failure.
   */
  public function getWeatherData(float $latitude, float $longitude): ?array {
    $config = $this->configFactory->get('openmeteo_widget.settings');
    $apiEndpoint = $config->get('api_endpoint') ?: self::API_BASE_URL;

    $url = $apiEndpoint . '?' . http_build_query([
      'latitude' => $latitude,
      'longitude' => $longitude,
      'current' => 'temperature_2m,relative_humidity_2m,precipitation_probability,wind_speed_10m,weather_code',
      'daily' => 'temperature_2m_max,temperature_2m_min,weather_code',
      'timezone' => 'auto',
      'forecast_days' => 7,
    ]);

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 10,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      return $this->processWeatherData($data);
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('openmeteo_widget')->error(
        'Failed to fetch weather data: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
    catch (\JsonException $e) {
      $this->loggerFactory->get('openmeteo_widget')->error(
        'Failed to parse weather data: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Processes raw API data into a structured format.
   *
   * @param array<string, mixed> $data
   *   The raw API data.
   *
   * @return array<string, mixed>
   *   The processed weather data.
   */
  private function processWeatherData(array $data): array {
    $current = $data['current'] ?? [];
    $daily = $data['daily'] ?? [];

    // Process 7-day forecast.
    $forecast = [];
    if (!empty($daily['time']) && is_array($daily['time'])) {
      foreach ($daily['time'] as $index => $date) {
        $forecast[] = [
          'date' => $date,
          'temp_max' => $daily['temperature_2m_max'][$index] ?? NULL,
          'temp_min' => $daily['temperature_2m_min'][$index] ?? NULL,
          'weather_code' => $daily['weather_code'][$index] ?? NULL,
        ];
      }
    }

    return [
      'current' => [
        'time' => $current['time'] ?? NULL,
        'temperature' => $current['temperature_2m'] ?? NULL,
        'humidity' => $current['relative_humidity_2m'] ?? NULL,
        'precipitation_probability' => $current['precipitation_probability'] ?? NULL,
        'wind_speed' => $current['wind_speed_10m'] ?? NULL,
        'weather_code' => $current['weather_code'] ?? NULL,
      ],
      'daily' => $forecast,
      'timezone' => $data['timezone'] ?? 'UTC',
    ];
  }

  /**
   * Maps weather codes to icon types.
   *
   * @param int $code
   *   The WMO weather code.
   *
   * @return string
   *   The icon identifier.
   */
  public function getWeatherIcon(int $code): string {
    return match (true) {
      $code === 0 => 'clear',
      $code >= 1 && $code <= 3 => 'partly-cloudy',
      $code >= 45 && $code <= 48 => 'fog',
      $code >= 51 && $code <= 67 => 'rain',
      $code >= 71 && $code <= 77 => 'snow',
      $code >= 80 && $code <= 82 => 'rain-heavy',
      $code >= 85 && $code <= 86 => 'snow-heavy',
      $code >= 95 && $code <= 99 => 'thunderstorm',
      default => 'unknown',
    };
  }

  /**
   * Gets human-readable weather description.
   *
   * @param int $code
   *   The WMO weather code.
   *
   * @return string
   *   The weather description.
   */
  public function getWeatherDescription(int $code): string {
    return match (true) {
      $code === 0 => 'Clear sky',
      $code === 1 => 'Mainly clear',
      $code === 2 => 'Partly cloudy',
      $code === 3 => 'Overcast',
      $code >= 45 && $code <= 48 => 'Foggy',
      $code >= 51 && $code <= 55 => 'Drizzle',
      $code >= 56 && $code <= 57 => 'Freezing drizzle',
      $code >= 61 && $code <= 65 => 'Rain',
      $code >= 66 && $code <= 67 => 'Freezing rain',
      $code >= 71 && $code <= 75 => 'Snow',
      $code === 77 => 'Snow grains',
      $code >= 80 && $code <= 82 => 'Rain showers',
      $code >= 85 && $code <= 86 => 'Snow showers',
      $code === 95 => 'Thunderstorm',
      $code >= 96 && $code <= 99 => 'Thunderstorm with hail',
      default => 'Unknown',
    };
  }

}

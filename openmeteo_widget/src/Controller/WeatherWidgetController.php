<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\openmeteo_widget\Service\CityManager;
use Drupal\openmeteo_widget\Service\OpenMeteoClient;
use Drupal\openmeteo_widget\Service\WeatherCache;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Controller for weather widget AJAX endpoints.
 */
final class WeatherWidgetController extends ControllerBase {

  /**
   * Constructs a WeatherWidgetController object.
   *
   * @param \Drupal\openmeteo_widget\Service\CityManager $cityManager
   *   The city manager service.
   * @param \Drupal\openmeteo_widget\Service\WeatherCache $weatherCache
   *   The weather cache service.
   * @param \Drupal\openmeteo_widget\Service\OpenMeteoClient $meteoClient
   *   The Open-Meteo client.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   */
  public function __construct(
    private readonly CityManager $cityManager,
    private readonly WeatherCache $weatherCache,
    private readonly OpenMeteoClient $meteoClient,
    private readonly AccountInterface $currentUser,
    private readonly UserDataInterface $userData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openmeteo_widget.city_manager'),
      $container->get('openmeteo_widget.weather_cache'),
      $container->get('openmeteo_widget.client'),
      $container->get('current_user'),
      $container->get('user.data'),
    );
  }

  /**
   * Gets weather data for a specific city.
   *
   * @param string $city_id
   *   The city ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with weather data.
   */
  public function getWeather(string $city_id): JsonResponse {
    $city = $this->cityManager->getCity($city_id);

    if (!$city) {
      return new JsonResponse(
        ['error' => 'City not found'],
        404
      );
    }

    $weatherData = $this->weatherCache->getWeather(
      $city_id,
      (float) $city['latitude'],
      (float) $city['longitude']
    );

    if (!$weatherData) {
      return new JsonResponse(
        ['error' => 'Failed to fetch weather data'],
        500
      );
    }

    // Add city info and icon mappings.
    $response = [
      'city' => $city,
      'weather' => $weatherData,
      'current_icon' => $this->meteoClient->getWeatherIcon(
        (int) ($weatherData['current']['weather_code'] ?? 0)
      ),
      'current_description' => $this->meteoClient->getWeatherDescription(
        (int) ($weatherData['current']['weather_code'] ?? 0)
      ),
    ];

    // Add icons for daily forecast.
    foreach ($weatherData['daily'] ?? [] as $index => $day) {
      $response['weather']['daily'][$index]['icon'] = $this->meteoClient->getWeatherIcon(
        (int) ($day['weather_code'] ?? 0)
      );
      $response['weather']['daily'][$index]['description'] = $this->meteoClient->getWeatherDescription(
        (int) ($day['weather_code'] ?? 0)
      );
    }

    return new JsonResponse($response);
  }

  /**
   * Saves user's city preference.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function savePreference(Request $request): JsonResponse {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (!isset($data['city_id'])) {
      return new JsonResponse(
        ['error' => 'Missing city_id parameter'],
        400
      );
    }

    $cityId = $data['city_id'];

    // Verify city exists.
    $city = $this->cityManager->getCity($cityId);
    if (!$city) {
      return new JsonResponse(
        ['error' => 'City not found'],
        404
      );
    }

    // Save preference.
    if ($this->currentUser->isAuthenticated()) {
      // Save to user data.
      $this->userData->set(
        'openmeteo_widget',
        $this->currentUser->id(),
        'selected_city',
        $cityId
      );

      return new JsonResponse(['success' => TRUE, 'method' => 'user_data']);
    }

    // For anonymous users, set cookie.
    $response = new JsonResponse(['success' => TRUE, 'method' => 'cookie']);
    $cookie = Cookie::create('openmeteo_city')
      ->withValue($cityId)
      ->withExpires(strtotime('+1 year'))
      ->withPath('/')
      ->withSecure(FALSE)
      ->withHttpOnly(FALSE);

    $response->headers->setCookie($cookie);

    return $response;
  }

  /**
   * Manual fetch endpoint for admin.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function fetchNow(): JsonResponse {
    $cities = $this->cityManager->getAllCities();
    $results = [];

    foreach ($cities as $cityId => $city) {
      $data = $this->weatherCache->fetchAndCache(
        $cityId,
        (float) $city['latitude'],
        (float) $city['longitude']
      );

      $results[$cityId] = [
        'city' => $city['name'],
        'success' => $data !== NULL,
      ];
    }

    $this->weatherCache->updateLastUpdateTime();

    return new JsonResponse([
      'success' => TRUE,
      'results' => $results,
      'timestamp' => time(),
    ]);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openmeteo_widget\Service\CityManager;
use Drupal\openmeteo_widget\Service\OpenMeteoClient;
use Drupal\openmeteo_widget\Service\WeatherCache;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a weather widget block.
 *
 * @Block(
 *   id = "openmeteo_weather_widget",
 *   admin_label = @Translation("Open-Meteo Weather Widget"),
 *   category = @Translation("Weather")
 * )
 */
final class WeatherWidgetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a WeatherWidgetBlock object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\openmeteo_widget\Service\CityManager $cityManager
   *   The city manager service.
   * @param \Drupal\openmeteo_widget\Service\WeatherCache $weatherCache
   *   The weather cache service.
   * @param \Drupal\openmeteo_widget\Service\OpenMeteoClient $meteoClient
   *   The Open-Meteo client.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly CityManager $cityManager,
    private readonly WeatherCache $weatherCache,
    private readonly OpenMeteoClient $meteoClient,
    private readonly AccountInterface $account,
    private readonly UserDataInterface $userData,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('openmeteo_widget.city_manager'),
      $container->get('openmeteo_widget.weather_cache'),
      $container->get('openmeteo_widget.client'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cities = $this->cityManager->getAllCities();

    if (empty($cities)) {
      return [
        '#markup' => $this->t('No cities configured. Please configure cities in the <a href="@url">settings</a>.', [
          '@url' => '/admin/config/services/openmeteo-widget',
        ]),
      ];
    }

    // Determine selected city.
    $selectedCity = $this->getSelectedCity($cities);

    if (!$selectedCity) {
      $selectedCity = $this->cityManager->getDefaultCity();
    }

    // Get weather data.
    $weatherData = NULL;
    if ($selectedCity) {
      $weatherData = $this->weatherCache->getWeather(
        $selectedCity['id'],
        (float) $selectedCity['latitude'],
        (float) $selectedCity['longitude']
      );
    }

    return [
      '#theme' => 'openmeteo_widget',
      '#cities' => $cities,
      '#selected_city' => $selectedCity,
      '#weather_data' => $weatherData,
      '#meteo_client' => $this->meteoClient,
      '#attached' => [
        'library' => [
          'openmeteo_widget/weather_widget',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['user', 'cookies:openmeteo_city'],
      ],
    ];
  }

  /**
   * Gets the selected city based on user preference.
   *
   * @param array<string, array<string, mixed>> $cities
   *   All available cities.
   *
   * @return array<string, mixed>|null
   *   The selected city or NULL.
   */
  private function getSelectedCity(array $cities): ?array {
    // For authenticated users, check user data.
    if ($this->account->isAuthenticated()) {
      $cityId = $this->userData->get(
        'openmeteo_widget',
        $this->account->id(),
        'selected_city'
      );

      if ($cityId && isset($cities[$cityId])) {
        return $cities[$cityId];
      }
    }

    // For anonymous users, check cookie.
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      $cityId = $request->cookies->get('openmeteo_city');
      if ($cityId && isset($cities[$cityId])) {
        return $cities[$cityId];
      }
    }

    return NULL;
  }

}

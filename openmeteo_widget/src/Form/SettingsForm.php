<?php

declare(strict_types=1);

namespace Drupal\openmeteo_widget\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openmeteo_widget\Service\CityManager;
use Drupal\openmeteo_widget\Service\WeatherCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Open-Meteo Weather Widget settings.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\openmeteo_widget\Service\CityManager $cityManager
   *   The city manager service.
   * @param \Drupal\openmeteo_widget\Service\WeatherCache $weatherCache
   *   The weather cache service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly CityManager $cityManager,
    private readonly WeatherCache $weatherCache,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('openmeteo_widget.city_manager'),
      $container->get('openmeteo_widget.weather_cache'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openmeteo_widget_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openmeteo_widget.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openmeteo_widget.settings');

    $form['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('API Settings'),
      '#open' => TRUE,
    ];

    $form['api_settings']['api_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint'),
      '#default_value' => $config->get('api_endpoint'),
      '#description' => $this->t('Open-Meteo API endpoint. Leave empty to use default: https://api.open-meteo.com/v1/forecast'),
    ];

    $form['api_settings']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('API key (optional - Open-Meteo free tier does not require one)'),
    ];

    $form['caching'] = [
      '#type' => 'details',
      '#title' => $this->t('Caching Settings'),
      '#open' => TRUE,
    ];

    $form['caching']['enable_caching'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable caching'),
      '#default_value' => $config->get('enable_caching') ?? TRUE,
      '#description' => $this->t('Cache weather data for 3 hours to reduce API calls.'),
    ];

    $lastRun = $this->weatherCache->getLastUpdateTime();
    $form['caching']['last_cron_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Last cache update'),
      '#markup' => $lastRun
        ? $this->t('Last run: @time', ['@time' => date('Y-m-d H:i:s', $lastRun)])
        : $this->t('Never run'),
    ];

    $form['caching']['fetch_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fetch weather now'),
      '#submit' => ['::fetchNowSubmit'],
    ];

    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Settings'),
      '#open' => TRUE,
    ];

    $form['display']['use_tailwind_cdn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Tailwind CDN'),
      '#default_value' => $config->get('use_tailwind_cdn') ?? TRUE,
      '#description' => $this->t('Load TailwindCSS from CDN for widget styling. Disable if your theme already includes Tailwind or you want to use custom styles.'),
    ];

    $form['display']['use_openweather_icons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use OpenWeather icons'),
      '#default_value' => $config->get('use_openweather_icons') ?? FALSE,
      '#description' => $this->t('Use OpenWeather icon images instead of built-in SVG icons. OpenWeather provides colorful, detailed weather icons.'),
    ];

    $form['cities'] = [
      '#type' => 'details',
      '#title' => $this->t('City Management'),
      '#open' => TRUE,
    ];

    // Display existing cities.
    $cities = $this->cityManager->getAllCities();
    if (!empty($cities)) {
      $form['cities']['existing'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('City Name'),
          $this->t('Latitude'),
          $this->t('Longitude'),
          $this->t('Actions'),
        ],
        '#empty' => $this->t('No cities configured.'),
      ];

      foreach ($cities as $cityId => $city) {
        $form['cities']['existing'][$cityId] = [
          'name' => ['#plain_text' => $city['name']],
          'latitude' => ['#plain_text' => (string) $city['latitude']],
          'longitude' => ['#plain_text' => (string) $city['longitude']],
          'remove' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Remove'),
            '#title_display' => 'invisible',
          ],
        ];
      }
    }

    // Add new city form.
    $form['cities']['new_city'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add New City'),
      '#attached' => [
        'library' => [
          'openmeteo_widget/city_autocomplete',
        ],
      ],
    ];

    $form['cities']['new_city']['city_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City Name'),
      '#description' => $this->t('Start typing to search for a city. Coordinates will be filled automatically.'),
      '#attributes' => [
        'class' => ['city-autocomplete'],
        'autocomplete' => 'off',
        'data-autocomplete-url' => '/openmeteo-widget/autocomplete/city',
      ],
    ];

    $form['cities']['new_city']['coordinates_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item-coordinates-wrapper']],
    ];

    $form['cities']['new_city']['coordinates_wrapper']['city_latitude'] = [
      '#type' => 'number',
      '#title' => $this->t('Latitude'),
      '#step' => 'any',
      '#description' => $this->t('Auto-filled from city selection'),
      '#attributes' => [
        'class' => ['city-latitude'],
        'readonly' => 'readonly',
      ],
    ];

    $form['cities']['new_city']['coordinates_wrapper']['city_longitude'] = [
      '#type' => 'number',
      '#title' => $this->t('Longitude'),
      '#step' => 'any',
      '#description' => $this->t('Auto-filled from city selection'),
      '#attributes' => [
        'class' => ['city-longitude'],
        'readonly' => 'readonly',
      ],
    ];

    $form['cities']['new_city']['help'] = [
      '#markup' => '<p><small>' . $this->t('Or manually enter coordinates if you prefer.') . '</small></p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('openmeteo_widget.settings');

    // Save API settings.
    $config->set('api_endpoint', $form_state->getValue('api_endpoint'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('enable_caching', (bool) $form_state->getValue('enable_caching'))
      ->set('use_tailwind_cdn', (bool) $form_state->getValue('use_tailwind_cdn'))
      ->set('use_openweather_icons', (bool) $form_state->getValue('use_openweather_icons'))
      ->save();

    // Handle city removal.
    $existing = $form_state->getValue('existing');
    if (is_array($existing)) {
      foreach ($existing as $cityId => $values) {
        if (!empty($values['remove'])) {
          $this->cityManager->removeCity($cityId);
          $this->weatherCache->clearCity($cityId);
          $this->messenger()->addStatus($this->t('City removed successfully.'));
        }
      }
    }

    // Handle new city addition.
    $cityName = $form_state->getValue('city_name');
    $cityLat = $form_state->getValue('city_latitude');
    $cityLon = $form_state->getValue('city_longitude');

    if (!empty($cityName) && $cityLat !== NULL && $cityLon !== NULL) {
      $this->cityManager->addCity($cityName, (float) $cityLat, (float) $cityLon);
      $this->messenger()->addStatus($this->t('City added successfully.'));
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for "Fetch weather now" button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function fetchNowSubmit(array &$form, FormStateInterface $form_state): void {
    $cities = $this->cityManager->getAllCities();
    $count = 0;

    foreach ($cities as $cityId => $city) {
      $result = $this->weatherCache->fetchAndCache(
        $cityId,
        (float) $city['latitude'],
        (float) $city['longitude']
      );

      if ($result !== NULL) {
        $count++;
      }
    }

    $this->weatherCache->updateLastUpdateTime();

    $this->messenger()->addStatus(
      $this->t('Fetched weather data for @count cities.', ['@count' => $count])
    );
  }

}

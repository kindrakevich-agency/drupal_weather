# Open-Meteo Weather Widget

A production-ready Drupal 11 custom module that displays weather information using the Open-Meteo API. Features multilanguage support, intelligent caching, and user-specific city preferences.

## Features

- **Real-time Weather Data**: Fetches current weather and 7-day forecasts from Open-Meteo API
- **Intelligent City Search**: Autocomplete city search with automatic coordinate filling using Open-Meteo Geocoding API
- **Multi-City Support**: Configure multiple cities with easy management interface
- **User Preferences**:
  - Authenticated users: City preference saved to user profile
  - Anonymous users: City preference saved to browser cookie
- **Smart Caching**:
  - Weather data cached for 3 hours
  - Automatic refresh via Drupal cron
  - On-demand cache refresh when cron hasn't run
- **Responsive Design**: Built with TailwindCSS for mobile-first responsive layout
- **AJAX City Switching**: Switch cities without page reload
- **Weather Icons**: Visual weather indicators based on WMO weather codes
- **Multilanguage Ready**: All strings translatable via Drupal's translation interface

## Requirements

- Drupal 11.x
- PHP 8.3+
- HTTP client (Guzzle - included in Drupal core)
- Internet connection for API access

## Installation

### Standard Installation

1. Download the module to your modules directory:
   ```bash
   cd /path/to/drupal/modules/custom
   git clone [repository-url] openmeteo_widget
   ```

2. Enable the module:
   ```bash
   drush en openmeteo_widget
   ```

   Or via the UI: Admin → Extend → Enable "Open-Meteo Weather Widget"

3. Configure the module:
   - Navigate to: Admin → Configuration → Web Services → Open-Meteo Weather Widget
   - Add your cities (name, latitude, longitude)
   - Configure caching preferences
   - Optionally set custom API endpoint

4. Place the block:
   - Navigate to: Admin → Structure → Block layout
   - Click "Place block" in your desired region
   - Select "Open-Meteo Weather Widget"
   - Configure and save

### Composer Installation (if publishing to Packagist)

```bash
composer require drupal/openmeteo_widget
drush en openmeteo_widget
```

## Configuration

### Admin Settings

Access the configuration form at: `/admin/config/services/openmeteo-widget`

**API Settings:**
- **API Endpoint**: Override the default Open-Meteo API endpoint (optional)
- **API Key**: Enter API key if using premium tier (free tier doesn't require one)

**Caching Settings:**
- **Enable Caching**: Toggle weather data caching (3-hour lifetime)
- **Last Cache Update**: View when cron last updated weather data
- **Fetch Weather Now**: Manual button to refresh all city data immediately

**City Management:**
- **Smart City Search**: Start typing a city name and get instant autocomplete suggestions
- **Automatic Coordinates**: Latitude and longitude are filled automatically when you select a city
- **Manual Override**: Click on coordinate fields to edit manually if needed
- **Remove Cities**: Check the "Remove" box for existing cities to delete them
- **Default City**: The first configured city becomes the default for new visitors

### Adding Cities with Autocomplete

The module features intelligent city search powered by Open-Meteo's Geocoding API:

1. **Start Typing**: Enter at least 2 characters in the "City Name" field
2. **Select from Suggestions**: A dropdown will appear with matching cities showing:
   - Full city name with country (e.g., "Madrid, Madrid, Spain")
   - Coordinates preview
3. **Auto-fill**: When you click a suggestion, coordinates are filled automatically
4. **Keyboard Navigation**: Use arrow keys to navigate suggestions, Enter to select, Esc to close
5. **Manual Entry**: Click coordinate fields if you prefer to enter them manually

Example cities you can search for:
- Type "Madrid" → Select "Madrid, Madrid, Spain"
- Type "New York" → Select "New York, New York, United States"
- Type "London" → Select "London, England, United Kingdom"

## Cron and Caching

### How Caching Works

1. **First Request**: Weather data fetched from API and cached for 3 hours
2. **Subsequent Requests**: Data served from cache (fast)
3. **Cache Expiry**: After 3 hours, next request fetches fresh data
4. **Cron Updates**: Every cron run, all city data is refreshed proactively

### Cron Configuration

The module uses Drupal's standard cron system:

```bash
# Run cron manually
drush cron

# Set up cron (system crontab)
*/30 * * * * cd /path/to/drupal && drush cron
```

Or configure via: Admin → Configuration → System → Cron

### Cache Management

Clear specific city cache programmatically:
```php
$weatherCache = \Drupal::service('openmeteo_widget.weather_cache');
$weatherCache->clearCity('madrid');
```

Clear all weather cache:
```php
$weatherCache->clearAll();
```

## City Selection Behavior

### Authenticated Users

When a user switches cities, their preference is saved to their user profile:
- Stored in `user.data` table
- Persists across sessions and devices
- Survives cache clears

### Anonymous Users

City preference saved to browser cookie:
- Cookie name: `openmeteo_city`
- Lifetime: 1 year
- Migrates to user profile upon login

### Default City

If no preference exists, the first configured city is used.

## API Integration

### Open-Meteo API

This module uses the free Open-Meteo API: https://open-meteo.com/

**API Fields Used:**
- `current`: temperature_2m, relative_humidity_2m, precipitation_probability, wind_speed_10m, weather_code
- `daily`: temperature_2m_max, temperature_2m_min, weather_code

**Weather Codes**: Module uses WMO weather codes (0-99) and maps them to appropriate icons and descriptions.

### API Rate Limits

Open-Meteo free tier:
- 10,000 API calls per day
- Non-commercial use
- No API key required

For higher limits, consider premium tier: https://open-meteo.com/en/pricing

## Theming

### Template Override

Copy template to your theme:
```bash
cp modules/custom/openmeteo_widget/templates/openmeteo-widget.html.twig \
   themes/custom/YOURTHEME/templates/
```

### Available Variables

```twig
{# cities: Array of all configured cities #}
{# selected_city: Currently selected city #}
{# weather_data: Current weather and forecast #}
{# meteo_client: Service for icon/description mapping #}
```

### CSS Customization

The widget uses TailwindCSS. To customize:

1. **Override template classes**: Change Tailwind classes in your template override

2. **Add custom CSS**: Create `YOURTHEME/css/weather-widget.css`:
   ```css
   .openmeteo-widget {
     /* Your custom styles */
   }
   ```

3. **Replace TailwindCSS**: Remove CDN link in template, use your own styling framework

### Weather Icons

Icons are rendered as inline SVG. To customize, edit the `weather_icon` macro in the template.

Current icon types:
- `clear`: Sunny/clear sky
- `partly-cloudy`: Partly cloudy
- `rain`: Rain
- `rain-heavy`: Heavy rain
- `thunderstorm`: Thunder and lightning
- `snow`: Snow
- `snow-heavy`: Heavy snow
- `fog`: Foggy conditions
- `unknown`: Unknown/default

## Extending the Module

### Adding AQI (Air Quality Index)

To add AQI support as shown in the original design:

1. **Update API call** in `OpenMeteoClient.php`:
   ```php
   'current' => 'temperature_2m,relative_humidity_2m,precipitation_probability,wind_speed_10m,weather_code,us_aqi',
   ```

2. **Process AQI data** in `processWeatherData()`:
   ```php
   'aqi' => $current['us_aqi'] ?? NULL,
   ```

3. **Update template** to display AQI section (see original HTML design)

4. **Add AQI visualization** with color scale and descriptions

### Custom Weather Sources

To use a different weather API:

1. Create new service class (e.g., `CustomWeatherClient.php`)
2. Implement same interface as `OpenMeteoClient`
3. Update services.yml to use your client
4. Adjust data processing for your API format

### Drush Commands

You can add custom Drush commands by creating `src/Commands/WeatherCommands.php`:

```php
<?php

namespace Drupal\openmeteo_widget\Commands;

use Drush\Commands\DrushCommands;

class WeatherCommands extends DrushCommands {

  /**
   * Fetches fresh weather data for all cities.
   *
   * @command openmeteo:fetch
   * @aliases omf
   */
  public function fetch() {
    $weatherCache = \Drupal::service('openmeteo_widget.weather_cache');
    $cityManager = \Drupal::service('openmeteo_widget.city_manager');

    $cities = $cityManager->getAllCities();
    foreach ($cities as $cityId => $city) {
      $weatherCache->fetchAndCache($cityId, $city['latitude'], $city['longitude']);
      $this->output()->writeln("Fetched: {$city['name']}");
    }
  }
}
```

## Configuration Export/Import

The module uses Drupal's configuration system. Export your configuration:

```bash
# Export single config
drush config:export --destination=/tmp openmeteo_widget.settings

# Include in full site export
drush config:export
```

Import configuration:
```bash
drush config:import
```

Configuration file location: `config/install/openmeteo_widget.settings.yml`

## Troubleshooting

### Weather Data Not Displaying

1. **Check API connectivity**:
   ```bash
   curl "https://api.open-meteo.com/v1/forecast?latitude=40.4168&longitude=-3.7038&current=temperature_2m"
   ```

2. **Check Drupal logs**: Admin → Reports → Recent log messages

3. **Clear cache**: `drush cr`

4. **Verify cities configured**: Admin → Configuration → Open-Meteo Widget

### City Selection Not Saving

1. **Authenticated users**: Check database table `users_data`
2. **Anonymous users**: Check browser cookies (openmeteo_city)
3. **Check JavaScript console** for AJAX errors

### Cron Not Running

1. **Test cron manually**: `drush cron`
2. **Check system cron**: `crontab -l`
3. **Enable Automated Cron** module (simple option)
4. **Check cron status**: Admin → Reports → Status report

### Performance Issues

1. **Enable caching** in module settings
2. **Verify cron running** every 30-60 minutes
3. **Check cache backend**: Consider Redis/Memcache for high-traffic sites
4. **Review API calls**: Check Open-Meteo usage in logs

## Development

### Code Standards

This module follows:
- Drupal 11 coding standards
- PHP 8.3 features (strict typing, readonly properties, constructor property promotion)
- PSR-4 autoloading
- Dependency injection for all services

### Testing

Create tests in `tests/src/`:
- `Unit/`: Unit tests for services
- `Functional/`: Functional tests for UI
- `FunctionalJavascript/`: JavaScript-enabled tests

Run tests:
```bash
../vendor/bin/phpunit modules/custom/openmeteo_widget
```

## Security

- API calls use HTTPS
- No user input sent to external APIs
- City coordinates validated as floats
- AJAX endpoints validate city existence
- Cookie settings use appropriate security flags

## License

GPL-2.0-or-later

## Support

For issues, questions, or contributions:
- Issue queue: [Link to issue tracker]
- Documentation: This README
- Drupal.org project page: [Link if published]

## Credits

- **Weather Data**: Open-Meteo (https://open-meteo.com/)
- **UI Framework**: TailwindCSS (https://tailwindcss.com/)
- **Module Development**: [Your name/organization]

## Changelog

### 1.1.0
- **NEW**: Intelligent city autocomplete search powered by Open-Meteo Geocoding API
- **NEW**: Automatic coordinate filling when selecting cities
- **NEW**: Keyboard navigation for autocomplete suggestions (Arrow keys, Enter, Esc)
- **IMPROVED**: Enhanced admin UI with readonly coordinate fields
- **IMPROVED**: Manual override option for coordinates

### 1.0.0
- Initial release
- Multi-city support
- User preferences (authenticated + anonymous)
- Smart caching with cron integration
- AJAX city switching
- Responsive TailwindCSS design
- 7-day weather forecast
- WMO weather code icon mapping

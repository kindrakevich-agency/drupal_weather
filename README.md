# Build Drupal 11 Weather Widget Module (Open-Meteo)

TASK:
Create a complete, production-ready Drupal 11 custom module (multilanguage support) named openmeteo_widget.
The module must display a weather widget based on the uploaded HTML design (Temperature/Precipitation/Wind/AQI tabs removed).
The module must use Open-Meteo API for weather data: https://open-meteo.com/

1. Widget UI Requirements
- Use the provided HTML file (weather-widget.html).
- Remove tabs: Temperature, Precipitation, Wind, AQI.
- Must show: City name, current date/time, current temperature, humidity, wind speed, precipitation probability, 7‑day forecast with weathercode icon mapping.
- Styled using TailwindCSS.

2. Data Source Requirements
Use Open‑Meteo fields:
current=temperature_2m,relative_humidity_2m,precipitation_probability,wind_speed_10m,weathercode
daily=temperature_2m_max,temperature_2m_min,weathercode

3. Drupal Features
3.1 City selection system:
- Admin UI: add/remove cities with autocomplete; each city has name, lat, lon.
- On frontend: first city is default.
- When user changes city, save preference:
  - Authenticated: user entity field
  - Anonymous: cookie
- Next visit: load selected city automatically.

4. Cron + Caching Logic
- Cache weather per city.
- Cron fetches fresh data every 3 hours.
- If cache older than 3 hours and cron not run → fetch live on-demand.

5. Admin Settings
Create config form:
- Add/remove cities
- API settings (endpoint + API key field even if not needed)
- Toggle caching
- Manual “Fetch now”
- Show last cron time

6. Module Structure (AI must generate)
openmeteo_widget.info.yml
openmeteo_widget.routing.yml
openmeteo_widget.links.menu.yml
openmeteo_widget.services.yml
src/Controller/WeatherWidgetController.php
src/Form/SettingsForm.php
src/Service/OpenMeteoClient.php
src/Service/CityManager.php
src/Service/WeatherCache.php
src/Cron/WeatherCron.php
src/Plugin/Block/WeatherWidgetBlock.php
src/Entity/City.php (config entity)
src/Entity/WeatherRecord.php OR DB schema
templates/openmeteo-widget.html.twig
js/weather-widget.js

7. JS Interaction Requirements
weather-widget.js:
- Handles city switching
- AJAX reload of widget
- Save preference (cookie or user entity)
- No full reload

8. README.md Requirements
Must include:
- Module purpose
- Features
- Installation
- Cron + caching documentation
- City selection behavior
- How preferences work
- Theming instructions
- How to extend for AQI
- Drush commands (if generated)
- Exportable config notes

9. Code Requirements
- Strict typing
- Drupal 11 APIs only
- PHP 8.3 features
- Dependency injection everywhere
- No deprecated services
- Documented code

OUTPUT FORMAT
AI must return:
1. Full module folder structure
2. All code files
3. Twig template
4. JS file
5. README.md
6. API mapping info
7. Installation steps

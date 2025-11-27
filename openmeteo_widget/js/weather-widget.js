/**
 * @file
 * JavaScript for Open-Meteo Weather Widget.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Weather widget behavior.
   */
  Drupal.behaviors.openmeteoWidget = {
    attach: function (context, settings) {
      once('openmeteo-widget', '#city-selector', context).forEach(function (element) {
        // Handle city selection change.
        element.addEventListener('change', function (e) {
          const cityId = e.target.value;

          // Save preference.
          savePreference(cityId).then(function () {
            // Reload widget with new city data.
            loadWeatherData(cityId);
          }).catch(function (error) {
            console.error('Failed to save preference:', error);
          });
        });
      });
    }
  };

  /**
   * Saves city preference via AJAX.
   *
   * @param {string} cityId
   *   The city ID.
   *
   * @return {Promise}
   *   Promise that resolves when saved.
   */
  function savePreference(cityId) {
    return fetch('/openmeteo-widget/save-preference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        city_id: cityId
      }),
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Failed to save preference');
      }
      return response.json();
    });
  }

  /**
   * Loads weather data for a city via AJAX.
   *
   * @param {string} cityId
   *   The city ID.
   */
  function loadWeatherData(cityId) {
    const widget = document.querySelector('[data-widget-id="openmeteo-widget"]');

    if (!widget) {
      return;
    }

    // Add loading indicator.
    widget.classList.add('loading');

    fetch('/openmeteo-widget/weather/' + encodeURIComponent(cityId), {
      credentials: 'same-origin'
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load weather data');
        }
        return response.json();
      })
      .then(function (data) {
        updateWidget(data);
        widget.classList.remove('loading');
      })
      .catch(function (error) {
        console.error('Error loading weather data:', error);
        widget.classList.remove('loading');
        showError(widget);
      });
  }

  /**
   * Updates the widget with new weather data.
   *
   * @param {Object} data
   *   The weather data.
   */
  function updateWidget(data) {
    const widget = document.querySelector('[data-widget-id="openmeteo-widget"]');

    if (!widget) {
      return;
    }

    // Update city name.
    const cityName = widget.querySelector('h1');
    if (cityName && data.city) {
      cityName.textContent = data.city.name;
    }

    // Update current temperature.
    const temp = widget.querySelector('.text-9xl');
    if (temp && data.weather && data.weather.current) {
      temp.textContent = Math.round(data.weather.current.temperature);
    }

    // Update humidity.
    const humidityElements = widget.querySelectorAll('.text-right p');
    if (humidityElements.length >= 3 && data.weather && data.weather.current) {
      humidityElements[0].textContent = 'Humidity: ' + data.weather.current.humidity + '%';
      humidityElements[1].textContent = 'Precipitation: ' + data.weather.current.precipitation_probability + '%';
      humidityElements[2].textContent = 'Wind: ' + data.weather.current.wind_speed + ' km/h';
    }

    // Update 7-day forecast temperatures.
    if (data.weather && data.weather.daily) {
      const forecastCards = widget.querySelectorAll('.grid > div');
      data.weather.daily.forEach(function (day, index) {
        if (forecastCards[index]) {
          const tempSpans = forecastCards[index].querySelectorAll('.font-semibold span');
          if (tempSpans.length >= 2) {
            tempSpans[0].textContent = Math.round(day.temp_max) + '°';
            tempSpans[1].textContent = Math.round(day.temp_min) + '°';
          }
        }
      });
    }
  }

  /**
   * Shows error message in widget.
   *
   * @param {Element} widget
   *   The widget element.
   */
  function showError(widget) {
    const error = document.createElement('div');
    error.className = 'error-message bg-red-100 text-red-700 p-4 rounded-lg mb-4';
    error.textContent = 'Failed to load weather data. Please try again.';

    widget.insertBefore(error, widget.firstChild);

    // Remove error after 5 seconds.
    setTimeout(function () {
      error.remove();
    }, 5000);
  }

})(Drupal, once);

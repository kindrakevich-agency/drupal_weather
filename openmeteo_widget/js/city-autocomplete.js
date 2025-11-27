/**
 * @file
 * City autocomplete functionality for Open-Meteo Weather Widget.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * City autocomplete behavior.
   */
  Drupal.behaviors.openmeteoAutocomplete = {
    attach: function (context, settings) {
      once('city-autocomplete', '.city-autocomplete', context).forEach(function (input) {
        const autocompleteUrl = input.getAttribute('data-autocomplete-url');
        if (!autocompleteUrl) {
          return;
        }

        const wrapper = input.closest('fieldset');
        const latitudeField = wrapper.querySelector('.city-latitude');
        const longitudeField = wrapper.querySelector('.city-longitude');

        // Create autocomplete suggestions container.
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'city-autocomplete-suggestions';
        suggestionsContainer.style.cssText = 'position: absolute; z-index: 1000; background: white; border: 1px solid #ccc; max-height: 300px; overflow-y: auto; display: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(suggestionsContainer);

        let debounceTimer;
        let currentCityData = null;

        // Handle input changes.
        input.addEventListener('input', function () {
          const query = input.value.trim();

          clearTimeout(debounceTimer);

          if (query.length < 2) {
            hideSuggestions();
            return;
          }

          debounceTimer = setTimeout(function () {
            fetchCities(query);
          }, 300);
        });

        // Handle city selection from dropdown.
        function selectCity(city) {
          input.value = city.label;
          currentCityData = city;

          // Fill in coordinates - ensure dot as decimal separator.
          if (latitudeField && longitudeField) {
            // Convert to string with dot as decimal separator
            latitudeField.value = Number(city.latitude).toFixed(6).replace(',', '.');
            longitudeField.value = Number(city.longitude).toFixed(6).replace(',', '.');

            // Remove readonly to allow form submission.
            latitudeField.removeAttribute('readonly');
            longitudeField.removeAttribute('readonly');
          }

          hideSuggestions();
        }

        // Fetch cities from autocomplete endpoint.
        function fetchCities(query) {
          fetch(autocompleteUrl + '?q=' + encodeURIComponent(query), {
            credentials: 'same-origin'
          })
            .then(function (response) {
              if (!response.ok) {
                throw new Error('Failed to fetch cities');
              }
              return response.json();
            })
            .then(function (cities) {
              displaySuggestions(cities);
            })
            .catch(function (error) {
              console.error('Autocomplete error:', error);
              hideSuggestions();
            });
        }

        // Display autocomplete suggestions.
        function displaySuggestions(cities) {
          if (!cities || cities.length === 0) {
            hideSuggestions();
            return;
          }

          suggestionsContainer.innerHTML = '';

          cities.forEach(function (city) {
            const item = document.createElement('div');
            item.className = 'city-suggestion-item';
            item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';

            const cityName = document.createElement('div');
            cityName.textContent = city.label;
            cityName.style.fontWeight = 'bold';

            const coordinates = document.createElement('div');
            // Ensure dot as decimal separator (not comma)
            const lat = Number(city.latitude).toFixed(4).replace(',', '.');
            const lon = Number(city.longitude).toFixed(4).replace(',', '.');
            coordinates.textContent = 'Lat: ' + lat + ', Lon: ' + lon;
            coordinates.style.fontSize = '0.85em';
            coordinates.style.color = '#666';

            item.appendChild(cityName);
            item.appendChild(coordinates);

            // Hover effect.
            item.addEventListener('mouseenter', function () {
              item.style.backgroundColor = '#f0f0f0';
            });
            item.addEventListener('mouseleave', function () {
              item.style.backgroundColor = 'white';
            });

            // Click handler.
            item.addEventListener('click', function () {
              selectCity(city);
            });

            suggestionsContainer.appendChild(item);
          });

          suggestionsContainer.style.display = 'block';
        }

        // Hide suggestions.
        function hideSuggestions() {
          suggestionsContainer.style.display = 'none';
        }

        // Hide suggestions when clicking outside.
        document.addEventListener('click', function (e) {
          if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            hideSuggestions();
          }
        });

        // Allow manual editing of coordinates.
        if (latitudeField && longitudeField) {
          latitudeField.addEventListener('click', function () {
            this.removeAttribute('readonly');
            this.focus();
          });

          longitudeField.addEventListener('click', function () {
            this.removeAttribute('readonly');
            this.focus();
          });
        }

        // Handle keyboard navigation.
        input.addEventListener('keydown', function (e) {
          const items = suggestionsContainer.querySelectorAll('.city-suggestion-item');

          if (items.length === 0) {
            return;
          }

          let currentIndex = -1;
          items.forEach(function (item, index) {
            if (item.style.backgroundColor === 'rgb(240, 240, 240)') {
              currentIndex = index;
            }
          });

          if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex = Math.min(currentIndex + 1, items.length - 1);
            highlightItem(items, currentIndex);
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex = Math.max(currentIndex - 1, 0);
            highlightItem(items, currentIndex);
          } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            items[currentIndex].click();
          } else if (e.key === 'Escape') {
            hideSuggestions();
          }
        });

        function highlightItem(items, index) {
          items.forEach(function (item, i) {
            item.style.backgroundColor = i === index ? '#f0f0f0' : 'white';
          });
        }
      });
    }
  };

})(Drupal, once);

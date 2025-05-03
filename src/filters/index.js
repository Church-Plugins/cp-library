/**
 * CP Library Filter System
 *
 * Provides filter functionality across different contexts (archive, service type, block, etc.)
 * and post types (sermons, series, etc.)
 */

import * as ErrorHandler from './errorHandler';

class CPLibraryFilter {
	/**
	 * Filter constructor
	 *
	 * @param {Object} config Configuration object
	 */
	constructor (config) {
		// Default configuration
		this.config = Object.assign({
			context      : 'archive',
			container    : null,
			contextArgs  : {},
			targetElement: null,
			lazyLoad     : true,
			autoSubmit   : true, // Auto-submit form on filter change
			debug        : false,
			postType     : 'cpl_item', // Default to sermon post type
		}, config);

		// Set container
		if (typeof this.config.container === 'string') {
			this.container = document.querySelector(this.config.container);
		} else {
			this.container = this.config.container;
		}

		if (!this.container) {
			this.log('Error: Container element not found');
			return;
		}

		// Initialize
		this.initialize();
		this.log('Filter initialized', this.config);
	}

	/**
	 * Log debug messages
	 *
	 * @param {...any} args Arguments to log
	 */
	log (...args) {
		if (this.config.debug) {
			console.log(`[CPLibraryFilter:${this.config.postType}]`, ...args);
		}
	}

	/**
	 * Log and handle errors
	 *
	 * @param {string|Object} error Error code, message, or object
	 * @param {Object} data Additional error data
	 */
	logError (error, data = {}) {
		// Ensure we have a container for the error
		const container = this.container;

		// Add post type and context to the error data
		const errorData = {
			...data,
			postType: this.config.postType,
			context : this.config.context
		};

		// Handle different error types
		if (typeof error === 'string') {
			// Simple error message or code
			ErrorHandler.reportError(
				ErrorHandler.ERROR_CODES.GENERAL_ERROR,
				error,
				errorData
			);

			// Display error if debug mode is on
			if (this.config.debug) {
				ErrorHandler.displayError(container, error);
			}
		} else if (error instanceof Error) {
			// JavaScript Error object
			ErrorHandler.handleCaughtError(error, this.config.debug ? container : null);
		} else {
			// Formatted error object
			ErrorHandler.reportError(
				error.code || ErrorHandler.ERROR_CODES.GENERAL_ERROR,
				error.message || 'An error occurred',
				{...errorData, ...error.data}
			);

			// Display error if debug mode is on
			if (this.config.debug) {
				ErrorHandler.displayError(container, error);
			}
		}

		// Always log to console in debug mode
		if (this.config.debug) {
			console.error(`[CPLibraryFilter:${this.config.postType}] Error:`, error, errorData);
		}
	}

	/**
	 * Initialize the filter
	 */
	initialize () {
		this.log('Initializing filter', this.config);

		// Get filter dropdowns
		this.filterDropdowns = this.container.querySelectorAll('.cpl-filter--dropdown');
		if (!this.filterDropdowns.length) {
			this.log('No filter dropdowns found');
			return;
		}

		// Initialize event listeners
		this.initEventListeners();

		// Initialize dropdowns
		this.initializeDropdowns();

		// Initial check for filter button state
		this.checkAndUpdateFilterButtonState();
	}

	/**
	 * Initialize event listeners
	 */
	initEventListeners () {
		// Toggle filter visibility on mobile
		const toggleButton = this.container.querySelector('.cpl-filter--toggle--button');
		if (toggleButton) {
			// Add ARIA attributes to the toggle button
			toggleButton.setAttribute('role', 'button');
			toggleButton.setAttribute('aria-expanded', 'false');
			toggleButton.setAttribute('aria-controls', 'cpl-filter-dropdowns-container');

			// Add keyboard support and click handling
			toggleButton.addEventListener('click', (e) => {
				e.preventDefault();
				const dropdowns = this.container.querySelectorAll('.cpl-filter--has-dropdown');
				const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';

				// Toggle visibility
				dropdowns.forEach(dropdown => {
					dropdown.style.display = isExpanded ? 'none' : 'block';
				});

				// Update ARIA state
				toggleButton.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

				// Announce state change to screen readers
				this.announceToScreenReader(isExpanded ?
					'Filters collapsed' :
					'Filters expanded. Use tab key to navigate between filters.');
			});

			// Add keyboard support
			toggleButton.addEventListener('keydown', (e) => {
				if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					toggleButton.click();
				}
			});
		}

		// Add container ID for ARIA controls
		const filtersContainer = this.container.querySelector('.cpl-filter--form');
		if (filtersContainer) {
			// Create a wrapper for dropdowns if it doesn't exist
			const dropdownsContainer = this.container.querySelector('.cpl-filter--dropdowns-container');
			if (!dropdownsContainer) {
				const dropdowns = this.container.querySelectorAll('.cpl-filter--has-dropdown');
				if (dropdowns.length) {
					// Add an ID to the first dropdown's parent container for ARIA controls
					const firstDropdownContainer = dropdowns[0].parentElement;
					if (firstDropdownContainer && firstDropdownContainer !== this.container) {
						firstDropdownContainer.id = 'cpl-filter-dropdowns-container';
					}
				}
			}
		}

		// Handle search button click and Enter key in search input
		const searchButton = this.container.querySelector('.cpl-search-submit');
		const searchInput = this.container.querySelector('input[name="cpl_search"]');

		if (searchButton) {
			// Add ARIA attributes to search button
			searchButton.setAttribute('aria-label', 'Search');

			searchButton.addEventListener('click', (e) => {
				e.preventDefault();
				this.submitOnChange();

				// Announce search to screen readers
				if (searchInput) {
					const searchTerm = searchInput.value.trim();
					this.announceToScreenReader(searchTerm ?
						`Searching for ${searchTerm}. Results updating.` :
						'Search submitted. Results updating.');
				}
			});
		}

		if (searchInput) {
			// Add ARIA attributes to search input
			searchInput.setAttribute('aria-label', 'Search content');

			searchInput.addEventListener('keypress', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					this.submitOnChange();

					// Announce search to screen readers
					const searchTerm = searchInput.value.trim();
					this.announceToScreenReader(searchTerm ?
						`Searching for ${searchTerm}. Results updating.` :
						'Search submitted. Results updating.');
				}
			});
		}

		// Close dropdowns when clicking outside
		document.addEventListener('click', () => {
			const dropdowns = this.container.querySelectorAll('.cpl-filter--has-dropdown');
			dropdowns.forEach(dropdown => {
				dropdown.classList.remove('open');

				// Reset ARIA states
				const button = dropdown.querySelector('a');
				if (button) {
					button.setAttribute('aria-expanded', 'false');
				}
			});
		});

		// Prevent clicks inside dropdowns from propagating
		this.container.querySelectorAll('.cpl-filter--has-dropdown').forEach(dropdown => {
			dropdown.addEventListener('click', (e) => {
				e.stopPropagation();
			});
		});
	}

	/**
	 * General purpose screen reader announcement function
	 *
	 * @param {string} message The message to announce
	 */
	announceToScreenReader (message) {
		// Create or get the live region
		let liveRegion = this.container.querySelector('.cpl-filter--sr-live-region');
		if (!liveRegion) {
			liveRegion = document.createElement('div');
			liveRegion.classList.add('cpl-filter--sr-live-region');
			liveRegion.setAttribute('aria-live', 'polite');
			liveRegion.setAttribute('role', 'status');
			liveRegion.setAttribute('aria-atomic', 'true');
			liveRegion.style.position = 'absolute';
			liveRegion.style.width = '1px';
			liveRegion.style.height = '1px';
			liveRegion.style.overflow = 'hidden';
			liveRegion.style.clip = 'rect(1px, 1px, 1px, 1px)';
			this.container.appendChild(liveRegion);
		}

		// Set the announcement message
		liveRegion.textContent = message;
	}

	/**
	 * Initialize filter dropdowns
	 */
	initializeDropdowns () {
		this.filterDropdowns.forEach(dropdown => {
			// For non-AJAX facets, initialize immediately
			// For AJAX facets, initialization will happen after loading options
			if (!dropdown.classList.contains('cpl-ajax-facet')) {
				this.initializeDropdown(dropdown);
				return;
			}

			// For AJAX facets, just add the initial event listeners without initializing the dropdown content
			// This prevents duplicate event listeners later

			const facetType = dropdown.dataset.facetType;
			const context = dropdown.dataset.context || this.config.context;

			if (!facetType) {
				this.log('No facet type specified for dropdown', dropdown);
				return;
			}

			// Mark as loading
			dropdown.parentElement.classList.add('disabled');

			// Get current filter selections - from URL and any current form state
			const params = new URLSearchParams(window.location.search);
			const urlParams = this.formatQueryParams(params);
			const currentFilters = this.getCurrentFilters();

			// Get the parameter name for this facet type
			const paramName = `facet-${facetType}`;
			
			// Try different ways to find selected values:
			// 1. From current form state using facet type as key
			// 2. From current form state using parameter name 
			// 3. From URL params using facet type as key
			// 4. From URL params using parameter name
			// 5. Default to empty array if nothing found
			const selected = (
				currentFilters[facetType] || 
				currentFilters[paramName] || 
				urlParams[facetType] || 
				urlParams[paramName] || 
				[]
			);

			// Load filter options via AJAX with the current state of all filters
			this.loadFilterOptions(dropdown, facetType, context, selected);
		});
	}

	/**
	 * Initialize just the checkboxes in a dropdown without adding dropdown toggle events
	 *
	 * @param {Element} dropdown The dropdown element
	 */
	initializeCheckboxes (dropdown) {
		// Handle checkbox changes
		dropdown.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
			checkbox.addEventListener('change', () => {
				// Submit the form on any filter change
				this.submitOnChange();

				// Announce change to screen readers
				this.announceFilterChange(checkbox);
			});

			// Add keyboard handling for checkboxes
			checkbox.addEventListener('keydown', (e) => {
				// Close dropdown on Escape
				if (e.key === 'Escape') {
					e.preventDefault();
					const dropdownParent = dropdown.parentElement;
					const button = dropdownParent.querySelector('a');
					if (button) {
						dropdownParent.classList.remove('open');
						button.setAttribute('aria-expanded', 'false');
						button.focus();
					}
				}
			});
		});

		// Set role for the labels inside dropdowns
		dropdown.querySelectorAll('label').forEach(label => {
			label.setAttribute('role', 'menuitem');
			label.setAttribute('tabindex', '-1');

			// Make labels focusable and clickable via keyboard
			label.addEventListener('keydown', (e) => {
				if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					const checkbox = label.querySelector('input[type="checkbox"]');
					if (checkbox) {
						checkbox.checked = !checkbox.checked;
						checkbox.dispatchEvent(new Event('change'));
					}
				}
			});
		});
	}

	/**
	 * Initialize a single dropdown
	 *
	 * @param {Element} dropdown The dropdown element
	 */
	initializeDropdown (dropdown) {
		// Toggle dropdown on button click
		const button = dropdown.parentElement.querySelector('a');
		const dropdownParent = dropdown.parentElement;

		if (button) {
			// Add ARIA attributes to the button
			button.setAttribute('role', 'button');
			button.setAttribute('aria-haspopup', 'true');
			button.setAttribute('aria-expanded', 'false');

			// Generate a unique ID for the dropdown if it doesn't have one
			if (!dropdown.id) {
				dropdown.id = `cpl-filter-dropdown-${Math.random().toString(36).substring(2, 9)}`;
			}

			// Connect button to dropdown with ARIA
			button.setAttribute('aria-controls', dropdown.id);

			// Handle click events
			button.addEventListener('click', (e) => {
				e.preventDefault();
				const isOpen = dropdownParent.classList.contains('open');

				// Close all dropdowns and reset ARIA states
				this.container.querySelectorAll('.cpl-filter--has-dropdown').forEach(dropdown => {
					const btn = dropdown.querySelector('a');
					if (btn) {
						btn.setAttribute('aria-expanded', 'false');
					}
					dropdown.classList.remove('open');
				});

				// Open this dropdown if it was closed
				if (!isOpen) {
					dropdownParent.classList.add('open');
					button.setAttribute('aria-expanded', 'true');

					// Focus the first checkbox for keyboard navigation
					const firstCheckbox = dropdown.querySelector('input[type="checkbox"]');
					if (firstCheckbox) {
						setTimeout(() => {
							firstCheckbox.focus();
						}, 10);
					}
				}
			});

			// Add keyboard handling for dropdown button
			button.addEventListener('keydown', (e) => {
				// Open dropdown on Space or Enter
				if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					button.click();
				}

				// Close dropdown on Escape
				if (e.key === 'Escape') {
					e.preventDefault();
					dropdownParent.classList.remove('open');
					button.setAttribute('aria-expanded', 'false');
					button.focus();
				}
			});
		}

		// Add ARIA label for the dropdown
		const labelText = button ? button.textContent.trim() : 'Filter options';
		dropdown.setAttribute('aria-label', `${labelText} options`);
		dropdown.setAttribute('role', 'menu');

		// Initialize the checkboxes and labels inside the dropdown
		this.initializeCheckboxes(dropdown);
	}

	/**
	 * Announce filter changes to screen readers
	 *
	 * @param {HTMLInputElement} checkbox The changed checkbox
	 */
	announceFilterChange (checkbox) {
		// Get the filter label
		const label = checkbox.closest('label');
		const filterText = label ? label.textContent.trim() : '';
		const checked = checkbox.checked;

		// Create or get the live region
		let liveRegion = this.container.querySelector('.cpl-filter--sr-live-region');
		if (!liveRegion) {
			liveRegion = document.createElement('div');
			liveRegion.classList.add('cpl-filter--sr-live-region');
			liveRegion.setAttribute('aria-live', 'polite');
			liveRegion.setAttribute('role', 'status');
			liveRegion.setAttribute('aria-atomic', 'true');
			liveRegion.style.position = 'absolute';
			liveRegion.style.width = '1px';
			liveRegion.style.height = '1px';
			liveRegion.style.overflow = 'hidden';
			liveRegion.style.clip = 'rect(1px, 1px, 1px, 1px)';
			this.container.appendChild(liveRegion);
		}

		// Set the announcement message
		const action = checked ? 'selected' : 'unselected';
		liveRegion.textContent = `Filter ${filterText} ${action}. Results updating.`;
	}

	/**
	 * Load filter options via AJAX
	 *
	 * @param {Element} dropdown The dropdown element
	 * @param {string} facetType The facet type
	 * @param {string} context The context
	 * @param {Array|string} selected Currently selected values
	 */
	loadFilterOptions (dropdown, facetType, context, selected) {
		// Get current filter selections from the form
		const currentFilters = this.getCurrentFilters();
		
		// Ensure selected is always an array
		const selectedArray = Array.isArray(selected) ? selected : (selected ? [selected] : []);

		// Mark as loading
		dropdown.parentElement.classList.add('loading');

		// Add a loading message for screen readers
		this.announceToScreenReader(`Loading ${facetType} filter options. Please wait.`);

		// Prepare AJAX data
		const data = {
			action     : 'cpl_filter_options',
			filter_type: facetType,
			context    : context,
			selected   : selected,
			post_type  : this.config.postType, // Include post type in the request
			args       : this.config.contextArgs,
			query_vars : this.buildQueryVars(currentFilters, facetType),
		};

		// Add query context if available from cplVars (but don't override existing filters)
		// First try specific post_type context, then fall back to general query_vars
		if (typeof cplFilter !== 'undefined') {
			// Check for post type specific query context first
			if (cplFilter.query_context && cplFilter.query_context[this.config.postType]) {
				data.query_vars = cplFilter.query_context[this.config.postType];
			}
		}
		// Fall back to general query_vars for backward compatibility
		else if (cplVars.query_vars) {
			// Add to our query vars rather than overriding
			Object.keys(cplVars.query_vars).forEach(key => {
				// Only add if not already set by our filters
				if (!data.query_vars[key]) {
					data.query_vars[key] = cplVars.query_vars[key];
				}
			});
		}

		this.log('Loading filter options', data);

		try {
			// Make AJAX request
			jQuery.ajax({
				url     : typeof cplVars !== 'undefined' ? cplVars.ajax_url : ajaxurl,
				type    : 'POST',
				data    : data,
				success : (response) => {
					// Remove loading state
					dropdown.parentElement.classList.remove('loading');

					if (response.success && response.data.options && response.data.options.length) {
						// Build options HTML
						let html = '';
						response.data.options.forEach(option => {
							// Ensure both values are strings for comparison
							const isChecked = selected.map(val => val.toString()).includes(option.value.toString());
							const showCount = window.cplVars?.show_filter_count !== 'hide';

							// Get the param name from response data if available, or use a prefixed version
							const paramName = response.data.param_name || `facet-${facetType}`;

							// Create unique IDs for checkboxes and labels
							const checkboxId = `cpl-filter-${facetType}-${option.value}-${Math.random().toString(36).substring(2,
								6)}`;

							html += `
                <label class="cp-has-checkmark" role="menuitem" tabindex="-1" for="${checkboxId}">
                  <input
                    type="checkbox"
                    id="${checkboxId}"
                    ${isChecked ? 'checked' : ''}
                    name="${paramName}[]"
                    value="${option.value}"
                    aria-checked="${isChecked ? 'true' : 'false'}"
                  />
                  <span class="cp-checkmark" aria-hidden="true"></span>
                  <span class="cp-filter-label">${option.title}
                    ${showCount ? `<sup class="cp-filter-count" aria-label="(${option.count} items)">(${option.count})</sup>` : ''}
                  </span>
                </label>
              `;
						});

						// Update the dropdown content
						dropdown.innerHTML = html;
						dropdown.parentElement.classList.remove('disabled');
						dropdown.parentElement.classList.add('initialized');

						// Initialize the dropdown (which will also initialize checkboxes)
						this.initializeDropdown(dropdown);

						// Announce to screen readers
						this.announceToScreenReader(
							`${facetType} filter options loaded successfully. ${response.data.options.length} options available.`);
					} else if (response.success && (
						!response.data.options || !response.data.options.length
					)) {
						// No options returned, but request was successful
						dropdown.innerHTML = `<div class="cp-no-filter-options">No filter options available</div>`;
						dropdown.parentElement.classList.add('disabled');
						dropdown.parentElement.classList.add('initialized');

						// Announce to screen readers
						this.announceToScreenReader(`No ${facetType} filter options available for the current selection.`);

						this.log('No filter options returned', response);
					} else {
						// Success false or other error
						dropdown.parentElement.classList.add('disabled');

						// Handle error in response
						const errorCode = response.data?.code || ErrorHandler.ERROR_CODES.AJAX_RESPONSE_ERROR;
						const errorMessage = response.data?.message || 'Error loading filter options';

						// Create error element inside dropdown
						dropdown.innerHTML = `<div class="cp-filter-error" role="alert">Error loading filter options</div>`;

						// Log and handle error
						this.logError({
							code   : errorCode,
							message: errorMessage,
							data   : {
								facetType,
								response
							}
						});

						// Announce to screen readers
						this.announceToScreenReader(`Error loading ${facetType} filter options: ${errorMessage}`);
					}
				},
				error   : (jqXHR, textStatus, errorThrown) => {
					// Remove loading state
					dropdown.parentElement.classList.remove('loading');
					dropdown.parentElement.classList.add('disabled');

					// Create error element inside dropdown
					dropdown.innerHTML = `<div class="cp-filter-error" role="alert">Error loading filter options</div>`;

					// Use error handler
					const error = ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, null);

					// Add context to error data
					error.data.facetType = facetType;
					error.data.context = context;

					// Log detailed error
					this.logError(error);

					// Announce to screen readers
					this.announceToScreenReader(`Error loading ${facetType} filter options. Please try again.`);
				},
				complete: () => {
					// Always check the filter button state after finishing
					this.checkAndUpdateFilterButtonState();
				}
			});
		} catch (error) {
			// Remove loading state
			dropdown.parentElement.classList.remove('loading');
			dropdown.parentElement.classList.add('disabled');

			// Create error element inside dropdown
			dropdown.innerHTML = `<div class="cp-filter-error" role="alert">Error loading filter options</div>`;

			// Handle JavaScript error
			const formattedError = ErrorHandler.handleCaughtError(error, null);

			// Add context to error data
			formattedError.data.facetType = facetType;
			formattedError.data.context = context;

			// Log detailed error
			this.logError(formattedError);

			// Announce to screen readers
			this.announceToScreenReader(`Error loading ${facetType} filter options. Please try again.`);

			// Check filter button state
			this.checkAndUpdateFilterButtonState();
		}
	}

	/**
	 * Format URL query parameters
	 *
	 * @param {URLSearchParams} params URL search parameters
	 * @returns {Object} Formatted parameters
	 */
	formatQueryParams (params) {
		const output = {};

		for (const [key, value] of params.entries()) {
			if (key.slice(-2) === '[]') {
				const newKey = key.slice(0, -2);
				output[newKey] = [
					...(
						output[newKey] || []
					), value
				];
			} else {
				output[key] = value;
			}
		}

		return output;
	}

	/**
	 * Get all currently selected filters from the form
	 *
	 * @returns {Object} Current filter selections
	 */
	getCurrentFilters () {
		const form = this.container.querySelector('form.cpl-filter--form');
		if (!form) {
			return {};
		}

		const formData = new FormData(form);
		const filters = {};

		// Process form data into structured format
		for (const [key, value] of formData.entries()) {
			const cleanKey = key.endsWith('[]') ? key.slice(0, -2) : key;

			if (!filters[cleanKey]) {
				filters[cleanKey] = [];
			}

			if (value && value.trim()) {
				filters[cleanKey].push(value);
			}
		}

		return filters;
	}

	/**
	 * Build query vars for WP_Query from current filters
	 *
	 * @param {Object} filters Current filter selections
	 * @param {string} currentFacet The facet type currently being loaded (to exclude from query)
	 * @returns {Object} Query vars for WP_Query
	 */
	buildQueryVars (filters, currentFacet) {
		// Start with basic query variables, using the configured post type
		const queryVars = {
			post_type  : this.config.postType,
			post_status: 'publish'
		};

		// Add all filter selections directly
		Object.keys(filters).forEach(key => {
			// Skip empty filters
			if (!filters[key] || !filters[key].length) {
				return;
			}

			// Handle facet-prefixed parameters
			if (key.startsWith('facet-')) {
				// Skip if this is the currentFacet
				const facetName = key.substring(6);
				if (facetName === currentFacet) {
					return;
				}

				// Add with 'facet-' prefix to ensure server knows it's a facet parameter
				queryVars[key] = filters[key];
			}
			// Handle non-facet parameters
			else if (key !== currentFacet) {
				queryVars[key] = filters[key];
			}
		});

		// Special handling for search since it's not an array
		if (filters.cpl_search && filters.cpl_search[0]) {
			queryVars.s = filters.cpl_search[0];
		}

		this.log('Built query vars:', queryVars);
		return queryVars;
	}

	/**
	 * Check if all dropdowns are disabled and update filter button state
	 */
	checkAndUpdateFilterButtonState () {
		const dropdownContainers = this.container.querySelectorAll('.cpl-filter--has-dropdown');
		const toggleButton = this.container.querySelector('.cpl-filter--toggle--button');

		if (!toggleButton) {
			return;
		}

		// Count total dropdowns and disabled dropdowns
		const totalDropdowns = dropdownContainers.length;
		let disabledDropdowns = 0;

		dropdownContainers.forEach(dropdown => {
			if (dropdown.classList.contains('disabled')) {
				disabledDropdowns++;
			}
		});

		// If all dropdowns are disabled or there are no dropdowns, disable the filter button
		if (disabledDropdowns === totalDropdowns || totalDropdowns === 0) {
			toggleButton.classList.add('disabled');
			toggleButton.style.opacity = '0.5';
			toggleButton.style.pointerEvents = 'none';
			this.log('All filter options are disabled. Disabling filter button.');
		} else {
			toggleButton.classList.remove('disabled');
			toggleButton.style.opacity = '';
			toggleButton.style.pointerEvents = '';
		}
	}

	/**
	 * Refresh all other filter options when one filter changes
	 *
	 * @param {string} changedFacet The facet type that was changed
	 */
	refreshOtherFilters (changedFacet) {
		// Get all dropdowns except the one that changed
		const otherDropdowns = this.container.querySelectorAll(
			`.cpl-filter--dropdown:not([data-facet-type="${changedFacet}"])`);

		// Get current form selections directly from the form
		const currentFilters = this.getCurrentFilters();

		otherDropdowns.forEach(dropdown => {
			const facetType = dropdown.dataset.facetType;
			const context = dropdown.dataset.context || this.config.context;

			if (!facetType) {
				return;
			}

			// Get selected values for this facet from current form state
			// instead of URL params (which may be outdated)
			const selected = currentFilters[facetType] || [];

			// Reload the filter options
			this.loadFilterOptions(dropdown, facetType, context, selected);
		});
	}

	/**
	 * Submit the form when a filter changes
	 */
	submitOnChange () {
		// Get the form
		const form = this.container.querySelector('form.cpl-filter--form');
		if (!form) {
			return;
		}

		// Remove pagination from URL
		const location = window.location;
		const baseUrl = location.protocol + '//' + location.hostname;
		const pathSplit = location.pathname.split('/');
		let finalPath = '';

		// Get the URL before the `page` element
		let gotBoundary = false;
		pathSplit.forEach(token => {
			if ('page' === token) {
				gotBoundary = true;
			}
			if (!gotBoundary) {
				if ('' === token) {
					if (!finalPath.endsWith('/')) {
						finalPath += '/';
					}
				} else {
					finalPath += token;
					if (!finalPath.endsWith('/')) {
						finalPath += '/';
					}
				}
			}
		});

		// Finish path
		if (!finalPath.endsWith('/')) {
			finalPath += '/';
		}

		// Parse existing URL parameters
		const urlParams = new URLSearchParams(window.location.search);
		const formData = new FormData(form);

		// Create a new URLSearchParams object for merged parameters
		const mergedParams = new URLSearchParams();

		// Add existing parameters to mergedParams (except pagination)
		for (const [key, value] of urlParams.entries()) {
			// Skip pagination parameters
			if (key !== 'paged' && key !== 'page') {
				mergedParams.append(key, value);
			}
		}

		// Add form parameters to mergedParams, handling arrays properly
		for (const [key, value] of formData.entries()) {
			// Extract clean key (remove [] for array parameters)
			const cleanKey = key.endsWith('[]') ? key.slice(0, -2) : key;

			// Skip empty values
			if (!value || !value.trim()) {
				continue;
			}

			// If parameter already exists in URL (might be from a different facet)
			if (urlParams.has(cleanKey) || urlParams.has(key)) {
				// For array parameters (facets)
				if (key.endsWith('[]')) {
					// Keep existing values in URL
					mergedParams.append(cleanKey, value);
				}
				// For non-array parameters (like search)
				else {
					// Replace with form value
					mergedParams.set(cleanKey, value);
				}
			}
			// Parameter doesn't exist in URL
			else {
				if (key.endsWith('[]')) {
					mergedParams.append(cleanKey, value);
				} else {
					mergedParams.set(cleanKey, value);
				}
			}
		}

		// Create the final URL
		const finalUrl = baseUrl + finalPath + (
			mergedParams.toString() ? '?' + mergedParams.toString() : ''
		);

		// Redirect to the new URL instead of form submission
		window.location.href = finalUrl;
	}

	/**
	 * Handle AJAX pagination for the filter
	 *
	 * @param {Element} paginationElement The pagination element
	 */
	setupAjaxPagination (paginationElement) {
		if (!paginationElement) {
			return;
		}

		// Add ARIA attributes to pagination container
		paginationElement.setAttribute('role', 'navigation');
		paginationElement.setAttribute('aria-label', 'Pagination');

		// Find all pagination links and replace their default behavior
		const paginationLinks = paginationElement.querySelectorAll('a');
		paginationLinks.forEach(link => {
			// Add ARIA roles and labels to pagination links
			link.setAttribute('role', 'button');

			// Parse page info from URL to set appropriate aria-label
			const href = link.getAttribute('href');
			const url = new URL(href, window.location.origin);
			const page = url.searchParams.get('paged') || 1;

			// Set descriptive labels for screen readers
			if (link.classList.contains('prev')) {
				link.setAttribute('aria-label', 'Previous page');
			} else if (link.classList.contains('next')) {
				link.setAttribute('aria-label', 'Next page');
			} else {
				const linkText = link.textContent.trim();
				link.setAttribute('aria-label', `Page ${linkText}`);
			}

			// Add keyboard support
			link.addEventListener('keydown', (e) => {
				if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					link.click();
				}
			});

			// Handle clicks to load pages via AJAX
			link.addEventListener('click', (e) => {
				e.preventDefault();

				// Announce page change to screen readers
				const pageText = link.textContent.trim();
				const message = link.classList.contains('prev')
					? 'Loading previous page'
					: link.classList.contains('next')
						? 'Loading next page'
						: `Loading page ${pageText}`;

				this.announceToScreenReader(`${message}. Please wait.`);

				// Call the appropriate AJAX function based on post type
				this.loadPageViaAjax(page);
			});
		});
	}

	/**
	 * Load a page of results via AJAX
	 *
	 * @param {number} page The page number to load
	 */
	loadPageViaAjax (page) {
		try {
			// Get current filter selections
			const currentFilters = this.getCurrentFilters();

			// Determine which AJAX action to use based on post type
			let action = 'cpl_filter_sermons'; // Default for cpl_item

			if (this.config.postType === 'cpl_item_type') {
				action = 'cpl_filter_series';
			}

			// Prepare AJAX data
			const data = {
				action      : action,
				filters     : currentFilters,
				context     : this.config.context,
				context_args: this.config.contextArgs,
				paged       : page,
				post_type   : this.config.postType,
				template    : this.config.template || 'grid',
			};

			// Get target element
			const targetElement = typeof this.config.targetElement === 'string'
				? document.querySelector(this.config.targetElement)
				: this.config.targetElement;

			if (!targetElement) {
				throw new Error('Target element not found for pagination results');
			}

			// Show loading state
			targetElement.classList.add('cpl-loading');

			// Add ARIA attributes to indicate loading
			targetElement.setAttribute('aria-busy', 'true');

			// Add a loading indicator with screen reader text
			const loadingIndicator = document.createElement('div');
			loadingIndicator.className = 'cpl-loading-indicator';
			loadingIndicator.setAttribute('role', 'status');
			loadingIndicator.setAttribute('aria-live', 'assertive');

			// Add a visually hidden message for screen readers
			const srText = document.createElement('span');
			srText.className = 'screen-reader-text';
			srText.textContent = 'Loading page ' + page + ' of results. Please wait.';

			// Add a visual spinner for sighted users
			const spinner = document.createElement('div');
			spinner.className = 'cpl-spinner';
			spinner.setAttribute('aria-hidden', 'true');

			// Assemble the loading indicator
			loadingIndicator.appendChild(srText);
			loadingIndicator.appendChild(spinner);

			// Add to the page
			targetElement.appendChild(loadingIndicator);

			// Log the request
			this.log('Loading page via AJAX', {page, action, postType: this.config.postType});

			// Make AJAX request
			jQuery.ajax({
				url    : cplVars?.ajax_url || ajaxurl,
				type   : 'POST',
				data   : data,
				success: (response) => {
					if (response.success && response.data) {
						// Replace content
						targetElement.innerHTML = response.data.html;

						// Replace pagination if provided
						const paginationContainer = document.querySelector('.cpl-pagination');
						if (paginationContainer && response.data.pagination) {
							paginationContainer.outerHTML = response.data.pagination;

							// Setup pagination links again
							this.setupAjaxPagination(document.querySelector('.cpl-pagination'));
						}

						// Remove loading state
						targetElement.classList.remove('cpl-loading');
						targetElement.setAttribute('aria-busy', 'false');

						// Scroll to top of results (with smooth behavior for better UX)
						targetElement.scrollIntoView({behavior: 'smooth', block: 'start'});

						// Announce to screen readers that content has been loaded
						this.announceToScreenReader(
							`Page ${page} loaded. Displaying ${response.data.count || 'multiple'} results.`);

						// Set focus to the first result for keyboard navigation
						const firstResult = targetElement.querySelector('article, .cpl-item-card, .cpl-item');
						if (firstResult) {
							firstResult.setAttribute('tabindex', '-1');
							setTimeout(() => {
								firstResult.focus();
							}, 500); // Give time for the scroll to complete
						}
					} else {
						// Handle error response
						this.handlePaginationError(targetElement, response, page);
					}
				},
				error  : (jqXHR, textStatus, errorThrown) => {
					// Use error handler
					const error = ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, null);

					// Add pagination context
					error.data.page = page;
					error.data.action = action;

					// Log the error
					this.logError(error);

					// Handle in the UI
					this.handlePaginationError(targetElement, error, page);
				}
			});
		} catch (error) {
			// Handle JavaScript errors
			const formattedError = ErrorHandler.handleCaughtError(error, null);

			// Add pagination context
			formattedError.data.page = page;

			// Log the error
			this.logError(formattedError);

			// Find target element if possible
			const targetElement = typeof this.config.targetElement === 'string'
				? document.querySelector(this.config.targetElement)
				: this.config.targetElement;

			if (targetElement) {
				this.handlePaginationError(targetElement, formattedError, page);
			}
		}
	}

	/**
	 * Handle pagination errors consistently
	 *
	 * @param {Element} targetElement Target element for content
	 * @param {Object} error Error object
	 * @param {number} page Page that was being loaded
	 */
	handlePaginationError (targetElement, error, page) {
		if (!targetElement) {
			return;
		}

		// Remove loading state
		targetElement.classList.remove('cpl-loading');
		targetElement.setAttribute('aria-busy', 'false');

		// Get user-friendly error message
		const errorMessage = ErrorHandler.getUserMessage(
			error,
			`Error loading page ${page}. Please try again.`
		);

		// Add error message to the content area
		const errorElement = document.createElement('div');
		errorElement.className = 'cpl-filter-pagination-error';
		errorElement.setAttribute('role', 'alert');
		errorElement.innerHTML = `
      <p>${errorMessage}</p>
      <button class="cpl-retry-button">Retry</button>
    `;

		// Clear target and add error
		targetElement.innerHTML = '';
		targetElement.appendChild(errorElement);

		// Add retry functionality
		const retryButton = errorElement.querySelector('.cpl-retry-button');
		if (retryButton) {
			retryButton.addEventListener('click', () => {
				this.loadPageViaAjax(page);
			});
		}

		// Announce error to screen readers
		this.announceToScreenReader(`Error loading page ${page}. ${errorMessage}`);
	}
}

/**
 * Initialize filters when the document is ready
 */
jQuery($ => {
	$(document).ready(function () {
		// Store filter instances
		window.cpLibraryFilters = window.cpLibraryFilters || {};

		// Initialize filters for sermon post type (cpl_item)
		initializeSermonFilters();

		// Initialize filters for series post type (cpl_item_type)
		initializeSeriesFilters();

		// Initialize custom filter contexts
		initializeCustomFilters();
	});

	/**
	 * Initialize sermon filters
	 */
	function initializeSermonFilters () {
		// Archive filters for sermons
		const archiveFilter = $('.cpl-filter[data-context="archive"][data-post-type="cpl_item"]');
		if (archiveFilter.length) {
			archiveFilter.each(function () {
				const filter = $(this);
				window.cpLibraryFilters[`sermon-archive-${Math.random().toString(36).substring(2, 9)}`] = new CPLibraryFilter({
					context    : 'archive',
					container  : filter[0],
					contextArgs: {},
					postType   : 'cpl_item',
					debug      : false
				});
			});
		}

		// Default sermon archive filter (for backward compatibility)
		const defaultArchiveFilter = $('.cpl-filter[data-context="archive"]:not([data-post-type])');
		if (defaultArchiveFilter.length) {
			defaultArchiveFilter.each(function () {
				const filter = $(this);
				window.cpLibraryFilters['archive'] = new CPLibraryFilter({
					context    : 'archive',
					container  : filter[0],
					contextArgs: {},
					postType   : 'cpl_item',
					debug      : false
				});
			});
		}

		// Service type filters
		const serviceTypeFilters = $('.cpl-filter[data-context="service-type"]');
		serviceTypeFilters.each(function () {
			const filter = $(this);
			const serviceTypeId = filter.data('service-type-id');

			if (serviceTypeId) {
				window.cpLibraryFilters[`service-type-${serviceTypeId}`] = new CPLibraryFilter({
					context    : 'service-type',
					container  : filter[0],
					contextArgs: {
						service_type_id: serviceTypeId
					},
					postType   : 'cpl_item',
					debug      : false
				});
			}
		});

		// Speaker filters
		const speakerFilters = $('.cpl-filter[data-context="speaker"]');
		speakerFilters.each(function () {
			const filter = $(this);
			const speakerId = filter.data('speaker-id');

			if (speakerId) {
				window.cpLibraryFilters[`speaker-${speakerId}`] = new CPLibraryFilter({
					context    : 'speaker',
					container  : filter[0],
					contextArgs: {
						speaker_id: speakerId
					},
					postType   : 'cpl_item',
					debug      : false
				});
			}
		});
	}

	/**
	 * Initialize series filters
	 */
	function initializeSeriesFilters () {
		// Archive filters for series
		const seriesArchiveFilter = $('.cpl-filter[data-context="archive"][data-post-type="cpl_item_type"]');
		if (seriesArchiveFilter.length) {
			seriesArchiveFilter.each(function () {
				const filter = $(this);
				window.cpLibraryFilters[`series-archive-${Math.random().toString(36).substring(2, 9)}`] = new CPLibraryFilter({
					context    : 'archive',
					container  : filter[0],
					contextArgs: {},
					postType   : 'cpl_item_type',
					debug      : false
				});
			});
		}

		// Season filters
		const seasonFilters = $('.cpl-filter[data-context="season"]');
		seasonFilters.each(function () {
			const filter = $(this);
			const seasonId = filter.data('season-id');

			if (seasonId) {
				window.cpLibraryFilters[`season-${seasonId}`] = new CPLibraryFilter({
					context    : 'season',
					container  : filter[0],
					contextArgs: {
						season_id: seasonId
					},
					postType   : 'cpl_item_type',
					debug      : false
				});
			}
		});

		// Topic filters
		const topicFilters = $('.cpl-filter[data-context="topic"]');
		topicFilters.each(function () {
			const filter = $(this);
			const topicId = filter.data('topic-id');

			if (topicId) {
				window.cpLibraryFilters[`topic-${topicId}`] = new CPLibraryFilter({
					context    : 'topic',
					container  : filter[0],
					contextArgs: {
						topic_id: topicId
					},
					postType   : 'cpl_item_type',
					debug      : false
				});
			}
		});
	}

	/**
	 * Initialize custom filter contexts that are not specific to sermons or series
	 */
	function initializeCustomFilters () {
		$('.cpl-filter[data-context]:not([data-context="archive"]):not([data-context="service-type"]):not([data-context="speaker"]):not([data-context="season"]):not([data-context="topic"])').each(
			function () {
				const filter = $(this);
				const context = filter.data('context');
				const postType = filter.data('post-type') || 'cpl_item'; // Default to sermon
				const contextArgsStr = filter.data('context-args') || '{}';
				let contextArgs = {};

				try {
					contextArgs = JSON.parse(contextArgsStr);
				} catch (e) {
					console.error('Error parsing context args:', e);
				}

				window.cpLibraryFilters[`custom-${context}-${Math.random().toString(36).substring(2, 9)}`] =
					new CPLibraryFilter({
						context    : context,
						container  : filter[0],
						contextArgs: contextArgs,
						postType   : postType,
						debug      : false
					});
			});
	}
});
/**
 * CP Library Filter System
 *
 * Provides filter functionality across different contexts (archive, service type, block, etc.)
 */

class CPLibraryFilter {
  /**
   * Filter constructor
   *
   * @param {Object} config Configuration object
   */
  constructor(config) {
    // Default configuration
    this.config = Object.assign({
      context: 'archive',
      container: null,
      contextArgs: {},
      targetElement: null,
      lazyLoad: true,
      autoSubmit: true, // Auto-submit form on filter change
      debug: false,
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
  log(...args) {
    if (this.config.debug) {
      console.log('[CPLibraryFilter]', ...args);
    }
  }

  /**
   * Initialize the filter
   */
  initialize() {
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
  initEventListeners() {
    // Toggle filter visibility on mobile
    const toggleButton = this.container.querySelector('.cpl-filter--toggle--button');
    if (toggleButton) {
      toggleButton.addEventListener('click', (e) => {
        e.preventDefault();
        const dropdowns = this.container.querySelectorAll('.cpl-filter--has-dropdown');
        dropdowns.forEach(dropdown => {
          dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        });
      });
    }
    
    // Handle search button click and Enter key in search input
    const searchButton = this.container.querySelector('.cpl-search-submit');
    const searchInput = this.container.querySelector('input[name="cpl_search"]');
    
    if (searchButton) {
      searchButton.addEventListener('click', (e) => {
        e.preventDefault();
        this.submitOnChange();
      });
    }
    
    if (searchInput) {
      searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.submitOnChange();
        }
      });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
      const dropdowns = this.container.querySelectorAll('.cpl-filter--has-dropdown');
      dropdowns.forEach(dropdown => {
        dropdown.classList.remove('open');
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
   * Initialize filter dropdowns
   */
  initializeDropdowns() {
    this.filterDropdowns.forEach(dropdown => {
      // Skip if already initialized
      if (!dropdown.classList.contains('cpl-ajax-facet')) {
        this.initializeDropdown(dropdown);
        return;
      }

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
      
      // Merge URL params and current form state, prioritizing form state
      const selected = (currentFilters[facetType] || urlParams[facetType] || []);
      
      // Load filter options via AJAX with the current state of all filters
      this.loadFilterOptions(dropdown, facetType, context, selected);
    });
  }

  /**
   * Initialize a single dropdown
   *
   * @param {Element} dropdown The dropdown element
   */
  initializeDropdown(dropdown) {
    // Toggle dropdown on button click
    const button = dropdown.parentElement.querySelector('a');
    if (button) {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        const hasClass = dropdown.parentElement.classList.contains('open');

        // Close all dropdowns
        this.container.querySelectorAll('.cpl-filter--has-dropdown').forEach(dropdown => {
          dropdown.classList.remove('open');
        });

        // Open this dropdown if it was closed
        if (!hasClass) {
          dropdown.parentElement.classList.add('open');
        }
      });
    }

    // Handle checkbox changes
    dropdown.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        // Submit the form on any filter change
        this.submitOnChange();
      });
    });
  }

  /**
   * Load filter options via AJAX
   *
   * @param {Element} dropdown The dropdown element
   * @param {string} facetType The facet type
   * @param {string} context The context
   * @param {Array} selected Currently selected values
   */
  loadFilterOptions(dropdown, facetType, context, selected) {
    // Get current filter selections from the form
    const currentFilters = this.getCurrentFilters();
    
    // Prepare AJAX data
    const data = {
      action: 'cpl_filter_options',
      filter_type: facetType,
      context: context,
      selected: selected,
      args: this.config.contextArgs,
      query_vars: this.buildQueryVars(currentFilters, facetType)
    };
    
    // Add wp_query vars if available from cplVars (but don't override existing filters)
    if (typeof cplVars !== 'undefined' && cplVars.query_vars) {
      // Add to our query vars rather than overriding
      Object.keys(cplVars.query_vars).forEach(key => {
        // Only add if not already set by our filters
        if (!data.query_vars[key]) {
          data.query_vars[key] = cplVars.query_vars[key];
        }
      });
    }

    this.log('Loading filter options', data);

    // Make AJAX request
    jQuery.ajax({
      url: typeof cplVars !== 'undefined' ? cplVars.ajax_url : ajaxurl,
      type: 'POST',
      data: data,
      success: (response) => {
        if (response.success && response.data.options && response.data.options.length) {
          // Build options HTML
          let html = '';
          response.data.options.forEach(option => {
            const isChecked = selected.includes(option.value.toString());
            const showCount = window.cplVars?.show_filter_count !== 'hide';

            // Get the param name from response data if available, or use a prefixed version
            const paramName = response.data.param_name || `facet-${facetType}`;
            
            html += `
              <label class="cp-has-checkmark">
                <input type="checkbox" ${isChecked ? 'checked' : ''} name="${paramName}[]" value="${option.value}"/>
                <span class="cp-checkmark"></span>
                <span class="cp-filter-label">${option.title}
                  ${showCount ? `<sup class="cp-filter-count">(${option.count})</sup>` : ''}
                </span>
              </label>
            `;
          });

          dropdown.innerHTML = html;
          dropdown.parentElement.classList.remove('disabled');
          dropdown.parentElement.classList.add('initialized');
          this.initializeDropdown(dropdown);
        } else {
          dropdown.parentElement.classList.add('disabled');
          this.log('No options returned or error', response);
        }
        
        // Check if all dropdowns are disabled, and if so, disable the filter button
        this.checkAndUpdateFilterButtonState();
      },
      error: (error) => {
        dropdown.parentElement.classList.add('disabled');
        this.log('AJAX error', error);
        // Check if all dropdowns are disabled, and if so, disable the filter button
        this.checkAndUpdateFilterButtonState();
      }
    });
  }

  /**
   * Format URL query parameters
   *
   * @param {URLSearchParams} params URL search parameters
   * @returns {Object} Formatted parameters
   */
  formatQueryParams(params) {
    const output = {};

    for (const [key, value] of params.entries()) {
      if (key.slice(-2) === '[]') {
        const newKey = key.slice(0, -2);
        output[newKey] = [
          ...(output[newKey] || []), value
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
  getCurrentFilters() {
    const form = this.container.querySelector('form.cpl-filter--form');
    if (!form) return {};
    
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
  buildQueryVars(filters, currentFacet) {
    // Start with basic query variables
    const queryVars = {
      post_type: 'cpl_item',
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
  checkAndUpdateFilterButtonState() {
    const dropdownContainers = this.container.querySelectorAll('.cpl-filter--has-dropdown');
    const toggleButton = this.container.querySelector('.cpl-filter--toggle--button');
    
    if (!toggleButton) return;
    
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
  refreshOtherFilters(changedFacet) {
    // Get all dropdowns except the one that changed
    const otherDropdowns = this.container.querySelectorAll(`.cpl-filter--dropdown:not([data-facet-type="${changedFacet}"])`);
    
    // Get current form selections directly from the form
    const currentFilters = this.getCurrentFilters();
    
    otherDropdowns.forEach(dropdown => {
      const facetType = dropdown.dataset.facetType;
      const context = dropdown.dataset.context || this.config.context;
      
      if (!facetType) return;
      
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
  submitOnChange() {
    // Get the form
    const form = this.container.querySelector('form.cpl-filter--form');
    if (!form) return;

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

    // Finish and add already-used GET params
    if (!finalPath.endsWith('/')) {
      finalPath += '/';
    }

    // Set form action and submit
    form.setAttribute('action', baseUrl + finalPath + location.search);
    form.submit();
  }
}

/**
 * Initialize filters when the document is ready
 */
jQuery($ => {
  $(document).ready(function () {
    // Store filter instances
    window.cpLibraryFilters = window.cpLibraryFilters || {};

    // Initialize default archive filter
    const archiveFilter = $('.cpl-filter[data-context="archive"]');
    if (archiveFilter.length) {
      window.cpLibraryFilters.archive = new CPLibraryFilter({
        context: 'archive',
        container: archiveFilter[0],
        contextArgs: {},
        debug: false
      });
    }

    // Initialize service type filters
    const serviceTypeFilters = $('.cpl-filter[data-context="service-type"]');
    serviceTypeFilters.each(function(index) {
      const filter = $(this);
      const serviceTypeId = filter.data('service-type-id');

      if (serviceTypeId) {
        window.cpLibraryFilters[`service-type-${serviceTypeId}`] = new CPLibraryFilter({
          context: 'service-type',
          container: filter[0],
          contextArgs: {
            service_type_id: serviceTypeId
          },
          debug: false
        });
      }
    });

    // Initialize custom filter contexts
    $('.cpl-filter[data-context]:not([data-context="archive"]):not([data-context="service-type"])').each(function() {
      const filter = $(this);
      const context = filter.data('context');
      const contextArgsStr = filter.data('context-args') || '{}';
      let contextArgs = {};

      try {
        contextArgs = JSON.parse(contextArgsStr);
      } catch (e) {
        console.error('Error parsing context args:', e);
      }

      window.cpLibraryFilters[`custom-${context}-${Math.random().toString(36).substring(2, 9)}`] =
        new CPLibraryFilter({
          context: context,
          container: filter[0],
          contextArgs: contextArgs,
          debug: false
        });
    });
  });
});

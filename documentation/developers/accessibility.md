# CP Library Accessibility Guide

This document provides information about the accessibility features implemented in the CP Library filter system. These enhancements help ensure that the filter functionality is usable by people with a wide range of abilities.

## Keyboard Navigation

The filter system has been enhanced to support full keyboard navigation:

1. **Filter Buttons**: All filter buttons can be navigated to using the Tab key and activated using Enter or Space.
2. **Dropdowns**: When a dropdown is opened, focus moves to the first checkbox inside the dropdown.
3. **Checkboxes**: Users can navigate between checkboxes using Tab and activate them using Space.
4. **Escape Key**: Pressing Escape when inside an open dropdown will close the dropdown and return focus to the trigger button.
5. **Search Input**: The search input is fully keyboard accessible and can be submitted with Enter.
6. **Selected Filters**: All selected filter buttons can be navigated to and activated using the keyboard.
7. **Pagination**: Pagination controls are fully keyboard accessible with clear focus indicators.

## Screen Reader Support

Several features have been added to improve the experience for screen reader users:

1. **ARIA Attributes**: Key components include appropriate ARIA roles, states, and properties:
   - `role="button"` for clickable elements
   - `aria-haspopup="true"` and `aria-expanded` for dropdown triggers
   - `aria-controls` to associate triggers with their corresponding content
   - `aria-label` for improved context where needed
   - `role="menu"` and `role="menuitem"` for dropdown options

2. **Live Regions**: The filter system uses ARIA live regions to announce:
   - When filters are selected or unselected
   - When search is performed
   - When content is loading
   - When new results are loaded

3. **Focus Management**: Proper focus management ensures that users are:
   - Notified when dropdowns open and close
   - Aware of the current filter state
   - Informed when filter results update
   - Moved to the first result item when pagination changes

4. **Descriptive Labels**: All interactive elements have clear, descriptive labels including:
   - Filter category names in selected filters
   - Counts for each filter option
   - Clear instructions for removing filters

## Visual Accessibility

Visual enhancements for improved accessibility include:

1. **Focus Indicators**: All interactive elements have visible focus indicators with sufficient contrast.
2. **Loading States**: Clear loading indicators show when content is being fetched.
3. **Error States**: Error messages are clearly displayed when issues occur.
4. **Sufficient Contrast**: Text and UI elements maintain appropriate contrast ratios.
5. **Consistent Design**: Interactive elements use consistent design patterns for predictability.

## Mobile Accessibility

The filter system is designed to be accessible on mobile devices:

1. **Responsive Design**: All filter controls adapt to smaller screen sizes.
2. **Touch Targets**: Buttons and checkboxes have adequate touch target sizes.
3. **Simple Interactions**: Mobile interactions are simplified for easier use.

## Implemented WCAG Guidelines

The filter system aims to meet the following WCAG 2.1 success criteria:

- **1.3.1 Info and Relationships**: Information, structure, and relationships are programmatically determined.
- **1.4.3 Contrast**: Text and interactive elements have sufficient contrast.
- **2.1.1 Keyboard**: All functionality is operable through a keyboard interface.
- **2.4.3 Focus Order**: Focus order preserves meaning and operability.
- **2.4.6 Headings and Labels**: Headings and labels describe topic or purpose.
- **2.4.7 Focus Visible**: Keyboard focus indicator is visible.
- **3.2.1 On Focus**: Components do not initiate a change of context on focus.
- **3.2.2 On Input**: Changing user interface components does not automatically cause unexpected context changes.
- **3.3.1 Error Identification**: Input errors are clearly identified.
- **4.1.2 Name, Role, Value**: For all UI components, name and role can be programmatically determined.

## Test Instructions

To test the accessibility of the filter system:

1. **Keyboard Navigation Test**:
   - Tab through all interactive elements
   - Open and close dropdowns using the keyboard
   - Select and unselect filter options
   - Submit search queries
   - Navigate pagination

2. **Screen Reader Test**:
   - Test with VoiceOver (Mac) or NVDA/JAWS (Windows)
   - Verify that all controls are announced properly
   - Check that state changes are announced
   - Ensure all interactive elements have appropriate roles and labels

3. **Mobile Test**:
   - Test on various mobile devices
   - Verify touch target sizes are adequate
   - Check that all functions work with touch input

## Future Improvements

Areas for future accessibility improvements:

1. **Expanded Keyboard Shortcuts**: Add more keyboard shortcuts for power users.
2. **Enhanced High Contrast Mode**: Improve visibility in high contrast mode.
3. **Support for Reduced Motion**: Add options for users who prefer reduced motion.
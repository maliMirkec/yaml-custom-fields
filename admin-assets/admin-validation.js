/**
 * YAML Custom Fields - Data Validation Page
 * Filter functionality for validation results
 */

(function($) {
  'use strict';

  $(document).ready(function() {
    // Only run on validation page
    if (!$('.yaml-cf-validation-container').length) {
      return;
    }

    // Filter functionality
    const filterBtns = document.querySelectorAll('.yaml-cf-filter-btn');
    const rows = document.querySelectorAll('.yaml-cf-validation-row');

    filterBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        // Update active state, in the class the styles use and for screen readers
        filterBtns.forEach(b => {
          b.classList.remove('active');
          b.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('active');
        this.setAttribute('aria-pressed', 'true');

        const filter = this.dataset.filter;

        // Show/hide rows
        rows.forEach(row => {
          const visible = filter === 'all' || row.dataset.status === filter;
          row.classList.toggle('yaml-cf-hidden', !visible);
        });
      });
    });
  });

})(jQuery);

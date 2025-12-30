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
        // Update active state
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;

        // Show/hide rows
        rows.forEach(row => {
          const status = row.dataset.status;
          if (filter === 'all') {
            row.style.display = '';
          } else if (filter === 'issues' && status === 'issues') {
            row.style.display = '';
          } else if (filter === 'healthy' && status === 'healthy') {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      });
    });
  });

})(jQuery);

# Integration Tests

Integration tests will be added in a future phase.

These tests will require the WordPress test environment to be installed first:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

## Planned Test Coverage

- Data Repositories (PostDataRepository, GlobalDataRepository, etc.)
- AJAX Handlers (SaveSchema, GetSchema, etc.)
- Import/Export functionality
- Admin Controllers
- Field Renderers


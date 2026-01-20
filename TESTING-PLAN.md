# Testing Plan for InstaWP Integration Plugin

## Overview

This document outlines a comprehensive testing strategy for the InstaWP Integration plugin to ensure reliability, prevent regressions, and maintain code quality.

## Current State

**Status**: No automated tests exist
**Testing Method**: Manual testing only via admin interface and debug logs
**Risk Level**: High - complex integrations with no safety net

## Testing Goals

1. **Prevent Regressions**: Catch bugs before they reach production
2. **Validate Core Features**: Ensure site creation, reconciliation, and WooCommerce integration work correctly
3. **Improve Development Speed**: Faster feedback loop during development
4. **Document Behavior**: Tests serve as living documentation
5. **Enable Refactoring**: Safe code improvements with test coverage

---

## Phase 1: Foundation Setup (Week 1-2)

### 1.1 Testing Framework Installation

**Tool**: PHPUnit with WordPress Test Suite

**Steps**:
```bash
# Install PHPUnit via Composer
composer require --dev phpunit/phpunit ^9.0
composer require --dev yoast/phpunit-polyfills
composer require --dev wp-phpunit/wp-phpunit

# Install WP-CLI test scaffold
wp scaffold plugin-tests iwp-wp-integration
```

**Files to Create**:
- `composer.json` - PHP dependencies
- `phpunit.xml.dist` - PHPUnit configuration
- `tests/bootstrap.php` - Test environment bootstrap
- `bin/install-wp-tests.sh` - WordPress test suite installer
- `.phpunit.result.cache` - Add to `.gitignore`

### 1.2 Directory Structure

```
iwp-wp-integration/
├── tests/
│   ├── bootstrap.php                    # Test environment setup
│   ├── unit/                            # Unit tests (isolated)
│   │   ├── core/
│   │   │   ├── test-api-client.php
│   │   │   ├── test-sites-model.php
│   │   │   ├── test-logger.php
│   │   │   └── test-utilities.php
│   │   └── integrations/
│   │       └── woocommerce/
│   │           ├── test-order-processor.php
│   │           └── test-product-integration.php
│   ├── integration/                     # Integration tests (with DB)
│   │   ├── test-site-creation.php
│   │   ├── test-demo-reconciliation.php
│   │   ├── test-database-migrations.php
│   │   └── test-woocommerce-flow.php
│   ├── e2e/                             # End-to-end tests (optional)
│   │   └── test-complete-workflows.php
│   ├── fixtures/                        # Mock data
│   │   ├── api-responses.php
│   │   └── sample-orders.php
│   └── helpers/                         # Test utilities
│       ├── class-test-helper.php
│       └── class-mock-api-client.php
├── phpunit.xml.dist
├── composer.json
└── .phpunit.result.cache (gitignored)
```

### 1.3 Configuration Files

**phpunit.xml.dist**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    verbose="true">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">includes</directory>
        </include>
        <exclude>
            <directory>tests</directory>
            <directory>vendor</directory>
        </exclude>
    </coverage>
</phpunit>
```

**composer.json** (minimal):
```json
{
    "name": "instawp/iwp-wp-integration",
    "description": "InstaWP Integration Plugin",
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "yoast/phpunit-polyfills": "^1.0",
        "wp-phpunit/wp-phpunit": "^6.0",
        "mockery/mockery": "^1.5"
    },
    "autoload-dev": {
        "psr-4": {
            "IWP\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "test:unit": "phpunit --testsuite='Unit Tests'",
        "test:integration": "phpunit --testsuite='Integration Tests'",
        "test:coverage": "phpunit --coverage-html coverage"
    }
}
```

---

## Phase 2: Critical Feature Tests (Week 3-4)

### Priority 1: Core Database Operations

**File**: `tests/unit/core/test-sites-model.php`

**Test Cases**:
```php
class Test_IWP_Sites_Model extends WP_UnitTestCase {

    public function test_create_demo_site() {
        // Test creating a demo site record
        $site_data = array(
            'site_id' => 'test-site-123',
            'site_url' => 'https://test.instawp.io',
            'site_type' => 'demo',
            'wp_username' => 'admin',
            'wp_password' => 'password123',
            'status' => 'completed',
            'source' => 'shortcode',
            'source_data' => array('email' => 'test@example.com')
        );

        $db_id = IWP_Sites_Model::create($site_data);

        $this->assertIsInt($db_id);
        $this->assertGreaterThan(0, $db_id);
    }

    public function test_update_site_with_credentials() {
        // Test updating site with full credentials
    }

    public function test_get_demo_sites_by_email() {
        // Test email-based demo site lookup
    }

    public function test_reconcile_demo_to_paid() {
        // Test converting demo to paid
    }

    public function test_array_values_json_encoded() {
        // Ensure array values are JSON encoded (bug fix verification)
    }
}
```

**Coverage**:
- ✅ Create operations
- ✅ Update operations (including the bug we just fixed)
- ✅ Query operations (by email, user, order)
- ✅ Demo site reconciliation
- ✅ Array value handling

### Priority 2: Site Creation Flow

**File**: `tests/integration/test-site-creation.php`

**Test Cases**:
```php
class Test_Site_Creation extends WP_UnitTestCase {

    private $mock_api_client;

    public function setUp(): void {
        parent::setUp();
        $this->mock_api_client = $this->createMockApiClient();
    }

    public function test_pool_site_creation_immediate() {
        // Test instant pool site creation
        // Verify database stores all credentials immediately
    }

    public function test_task_based_site_creation() {
        // Test non-pool site with task polling
        // Verify initial storage with pending status
    }

    public function test_task_completion_updates_database() {
        // CRITICAL: Test the bug we just fixed
        // Verify database is updated when task completes
        // Verify credentials are stored after polling
    }

    public function test_failed_site_creation() {
        // Test error handling
    }
}
```

**Coverage**:
- ✅ Pool (instant) sites
- ✅ Task-based (polling) sites
- ✅ Status checking updates database (bug fix)
- ✅ Credential storage
- ✅ Error handling

### Priority 3: Demo Site Reconciliation

**File**: `tests/integration/test-demo-reconciliation.php`

**Test Cases**:
```php
class Test_Demo_Reconciliation extends WP_UnitTestCase {

    public function test_single_demo_site_reconciliation() {
        // Create demo site with email
        // Create WooCommerce order with same email
        // Process order
        // Verify demo converted to paid
    }

    public function test_multiple_demo_sites_reconciliation() {
        // Create 3 demo sites with same email
        // Create order
        // Verify all 3 converted
    }

    public function test_guest_to_registered_user_reconciliation() {
        // Demo created as guest (user_id=0)
        // User registers with same email
        // User purchases
        // Verify demo reconciled with new user_id
    }

    public function test_subscription_id_stored() {
        // With WooCommerce Subscriptions
        // Verify subscription_id stored in source_data
    }

    public function test_demo_helper_disabled_on_conversion() {
        // Verify API call to disable demo helper plugin
    }

    public function test_order_note_added() {
        // Verify order note documents conversion
    }
}
```

**Coverage**:
- ✅ Email-based matching
- ✅ Multiple site conversion
- ✅ Guest to registered flow
- ✅ Subscription tracking
- ✅ Demo helper auto-disable
- ✅ Order meta updates

### Priority 4: Database Migrations

**File**: `tests/integration/test-database-migrations.php`

**Test Cases**:
```php
class Test_Database_Migrations extends WP_UnitTestCase {

    public function test_migration_adds_site_type_column() {
        // Drop column if exists
        // Run migration
        // Verify column exists
    }

    public function test_migration_is_idempotent() {
        // Run migration twice
        // Verify no errors
    }

    public function test_existing_sites_default_to_paid() {
        // Create site without site_type
        // Run migration
        // Verify site_type='paid'
    }

    public function test_version_tracking() {
        // Verify iwp_db_version option updates
    }
}
```

**Coverage**:
- ✅ Column addition
- ✅ Index creation
- ✅ Idempotency
- ✅ Backward compatibility
- ✅ Version tracking

---

## Phase 3: API Integration Tests (Week 5)

### Mock API Client

**File**: `tests/helpers/class-mock-api-client.php`

```php
class Mock_IWP_API_Client extends IWP_API_Client {

    private $responses = array();

    public function mock_response($method, $endpoint, $response) {
        $this->responses[$method][$endpoint] = $response;
    }

    public function create_site_from_snapshot($snapshot_slug, $site_data, $plan_id = null) {
        return $this->responses['POST']['/sites/template'] ?? $this->default_response();
    }

    public function get_task_status($task_id) {
        return $this->responses['GET']["/tasks/{$task_id}/status"] ?? $this->progress_response();
    }

    public function get_site_details($site_id) {
        return $this->responses['GET']["/sites/{$site_id}"] ?? $this->site_details_response();
    }

    private function default_response() {
        return array(
            'success' => true,
            'data' => array(
                'id' => 'mock-site-' . uniqid(),
                'url' => 'https://mock.instawp.io',
                'status' => 0,
                'is_pool' => false,
                'task_id' => 'mock-task-' . uniqid()
            )
        );
    }
}
```

### API Test Cases

**File**: `tests/unit/core/test-api-client.php`

```php
class Test_IWP_API_Client extends WP_UnitTestCase {

    public function test_authentication_header() {
        // Verify Bearer token in headers
    }

    public function test_error_handling() {
        // Test WP_Error returns
    }

    public function test_timeout_handling() {
        // Test 60-second timeout
    }

    public function test_team_id_parameter() {
        // Verify ?team_id parameter added when selected
    }
}
```

---

## Phase 4: WooCommerce Integration Tests (Week 6)

### Test Cases

**File**: `tests/integration/test-woocommerce-flow.php`

```php
class Test_WooCommerce_Flow extends WP_UnitTestCase {

    public function test_order_completion_creates_site() {
        // Create product with InstaWP settings
        // Create order
        // Complete order
        // Verify site created
    }

    public function test_multiple_products_create_multiple_sites() {
        // Order with 2 InstaWP products
        // Verify 2 sites created
    }

    public function test_site_upgrade_via_url_parameter() {
        // Set $_SESSION['iwp_site_id_for_upgrade']
        // Purchase plan-enabled product
        // Verify upgrade instead of new site
    }

    public function test_frontend_display() {
        // Verify sites appear in My Account
        // Verify order details page
        // Verify email notifications
    }
}
```

---

## Phase 5: Shortcode Tests (Week 7)

### Test Cases

**File**: `tests/integration/test-shortcode.php`

```php
class Test_Shortcode extends WP_UnitTestCase {

    public function test_shortcode_renders() {
        // Test [iwp_site_creator] output
    }

    public function test_ajax_site_creation() {
        // Test AJAX handler
        // Verify database storage
    }

    public function test_ajax_status_checking() {
        // Test status polling AJAX handler
        // CRITICAL: Verify database update on completion
    }

    public function test_parameter_prefilling() {
        // Test email, name, snapshot_slug parameters
    }
}
```

---

## Phase 6: Edge Cases & Error Handling (Week 8)

### Test Cases

**File**: `tests/integration/test-edge-cases.php`

```php
class Test_Edge_Cases extends WP_UnitTestCase {

    public function test_api_key_missing() {
        // Verify graceful handling
    }

    public function test_invalid_snapshot_slug() {
        // Verify error message
    }

    public function test_network_timeout() {
        // Verify timeout handling
    }

    public function test_database_connection_failure() {
        // Verify error logging
    }

    public function test_concurrent_site_creation() {
        // Test race conditions
    }

    public function test_expired_demo_sites() {
        // Test mark_expired_demos() method
    }
}
```

---

## Running Tests

### Local Development

```bash
# Install dependencies
composer install

# Install WordPress test suite (one time)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests only
composer test:integration

# Generate coverage report
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/integration/test-demo-reconciliation.php

# Run specific test method
vendor/bin/phpunit --filter test_single_demo_site_reconciliation
```

### Continuous Integration

**File**: `.github/workflows/tests.yml`

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1']
        wordpress: ['6.0', '6.3', 'latest']

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mysqli
          coverage: xdebug

      - name: Install dependencies
        run: composer install

      - name: Install WordPress
        run: bash bin/install-wp-tests.sh wordpress_test root '' localhost ${{ matrix.wordpress }}

      - name: Run tests
        run: composer test

      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

## Test Coverage Goals

### Phase 1-2 (Weeks 1-4)
- **Target**: 40% code coverage
- **Focus**: Core database operations, site creation

### Phase 3-4 (Weeks 5-6)
- **Target**: 60% code coverage
- **Focus**: API integration, WooCommerce flow

### Phase 5-6 (Weeks 7-8)
- **Target**: 75% code coverage
- **Focus**: Shortcode, edge cases

### Long-term Goal
- **Target**: 80%+ code coverage
- **Focus**: Maintain as new features added

---

## Critical Tests (Must Have)

These tests address the most important functionality and recent bugs:

1. ✅ **Demo site database storage** (`test_create_demo_site`)
2. ✅ **Task completion updates database** (`test_task_completion_updates_database`) - **Bug fix verification**
3. ✅ **Email-based reconciliation** (`test_single_demo_site_reconciliation`)
4. ✅ **Multiple demo sites conversion** (`test_multiple_demo_sites_reconciliation`)
5. ✅ **Database migration idempotency** (`test_migration_is_idempotent`)
6. ✅ **Array value JSON encoding** (`test_array_values_json_encoded`) - **Bug fix verification**
7. ✅ **WooCommerce order completion** (`test_order_completion_creates_site`)
8. ✅ **Site upgrade functionality** (`test_site_upgrade_via_url_parameter`)

---

## Test Data Fixtures

**File**: `tests/fixtures/api-responses.php`

```php
<?php
/**
 * Mock API responses for testing
 */
class IWP_Test_Fixtures {

    public static function pool_site_response() {
        return array(
            'success' => true,
            'data' => array(
                'id' => 'test-site-pool-123',
                'url' => 'https://pool-test.instawp.io',
                'status' => 0,
                'is_pool' => true,
                'site_meta' => array(
                    'wp_username' => 'admin',
                    'wp_password' => 'TestPass123!',
                ),
                'wp_admin_url' => 'https://pool-test.instawp.io/wp-admin',
                'hash' => 'test-hash-pool-123'
            )
        );
    }

    public static function task_site_response() {
        return array(
            'success' => true,
            'data' => array(
                'id' => 'test-site-task-456',
                'status' => 1,
                'is_pool' => false,
                'task_id' => 'test-task-456'
            )
        );
    }

    public static function task_completed_response() {
        return array(
            'success' => true,
            'data' => array(
                'status' => 0,
                'resource_id' => 'test-site-task-456',
                'message' => 'Site created successfully'
            )
        );
    }

    public static function site_details_response() {
        return array(
            'success' => true,
            'data' => array(
                'id' => 'test-site-task-456',
                'url' => 'https://task-test.instawp.io',
                'site_meta' => array(
                    'wp_username' => 'admin',
                    'wp_password' => 'TaskPass456!',
                ),
                'wp_admin_url' => 'https://task-test.instawp.io/wp-admin',
                'hash' => 'test-hash-task-456'
            )
        );
    }
}
```

---

## Testing Best Practices

### 1. Test Isolation
- Each test should be independent
- Use `setUp()` to create test data
- Use `tearDown()` to clean up
- Don't rely on test execution order

### 2. Naming Conventions
- Test methods: `test_what_it_tests()`
- Test classes: `Test_Class_Name`
- Be descriptive: `test_demo_site_reconciles_on_order_completion()`

### 3. Assertions
```php
// Good - specific assertions
$this->assertSame('demo', $site->site_type);
$this->assertArrayHasKey('email', $source_data);
$this->assertInstanceOf(WP_Error::class, $result);

// Avoid - generic assertions
$this->assertTrue($result); // What does true mean?
```

### 4. Mock External Services
- Never make real API calls in tests
- Mock `IWP_API_Client` responses
- Use fixtures for consistent test data

### 5. Test Documentation
```php
/**
 * Test that demo sites are converted to paid when customer purchases
 *
 * This test verifies the email-based reconciliation system:
 * 1. Create demo site with email
 * 2. Create WooCommerce order with same email
 * 3. Process order completion
 * 4. Verify demo site updated to paid
 * 5. Verify order_id and user_id populated
 *
 * @ticket #0.0.3 Demo reconciliation feature
 */
public function test_demo_site_reconciles_on_order_completion() {
    // Test implementation
}
```

---

## Maintenance & Continuous Improvement

### 1. Pre-commit Hook
```bash
#!/bin/bash
# .git/hooks/pre-commit

# Run tests before commit
composer test:unit

if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi
```

### 2. Regular Reviews
- Review test coverage monthly
- Add tests for new features
- Update tests when fixing bugs
- Remove outdated tests

### 3. Performance Monitoring
- Track test execution time
- Optimize slow tests
- Target: All tests run in < 5 minutes

### 4. Documentation Updates
- Keep TESTING-PLAN.md updated
- Document test utilities
- Add examples for common patterns

---

## Success Metrics

### Short-term (3 months)
- ✅ All critical features have tests
- ✅ 60% code coverage achieved
- ✅ Tests run in CI/CD pipeline
- ✅ Zero failing tests in main branch

### Medium-term (6 months)
- ✅ 75% code coverage achieved
- ✅ All bug fixes include regression tests
- ✅ New features require tests before merge
- ✅ Test execution time < 5 minutes

### Long-term (12 months)
- ✅ 80%+ code coverage maintained
- ✅ Automated security testing
- ✅ Performance testing suite
- ✅ End-to-end browser testing

---

## Resources

### WordPress Testing
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)
- [WP_UnitTestCase Documentation](https://developer.wordpress.org/reference/classes/wp_unittestcase/)

### PHPUnit
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)

### WooCommerce Testing
- [WooCommerce Testing Guide](https://github.com/woocommerce/woocommerce/wiki/How-to-set-up-WooCommerce-development-environment)

---

## Next Steps

1. **Week 1**: Review and approve this plan
2. **Week 1-2**: Set up testing framework and directory structure
3. **Week 3-4**: Write critical feature tests (database, site creation, reconciliation)
4. **Week 5**: Add API integration tests with mocks
5. **Week 6**: Add WooCommerce flow tests
6. **Week 7**: Add shortcode and AJAX tests
7. **Week 8**: Add edge case and error handling tests
8. **Ongoing**: Maintain 75%+ coverage, add tests for new features

---

**Version**: 1.0
**Last Updated**: 2025-01-20
**Author**: InstaWP Development Team
**Status**: Planning Phase

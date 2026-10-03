---
name: codeigniter-test
description: Create or run CodeIgniter 4 tests for a feature
argument-hint: "<feature_or_test_name>"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Help write or run CodeIgniter 4 tests.

1. Find existing tests for similar features to match conventions.
2. Use `php spark make:test` when creating a new test.
3. Extend `CIUnitTestCase` and use `setUp()`/`tearDown()` as needed.
4. Run the test with `phpunit` or the project’s documented test command.
5. Report results and any failures.

Do not declare a task complete until tests pass or the user explicitly accepts the test plan.

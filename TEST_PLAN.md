# QQConnectOAuth2Bundle Test Plan

## Overview
This test plan covers the QQ Connect OAuth2 Bundle implementation using TDD approach.

## Unit Tests

### Entity Tests
- [x] **QQOAuth2ConfigTest**
  - Test default values
  - Test setters and getters
  - Test updatedAt modification
  
- [x] **QQOAuth2StateTest**
  - Test default values and TTL
  - Test expiration logic
  - Test state validation
  - Test mark as used functionality
  
- [x] **QQOAuth2UserTest**
  - Test default values
  - Test token management
  - Test token expiration logic
  - Test user profile data management

### Service Tests
- [x] **QQOAuth2ServiceTest**
  - Test authorization URL generation
  - Test callback handling with valid state
  - Test callback handling with invalid state
  - Test user info retrieval
  - Test token refresh functionality

## Integration Tests
- [x] **QQOAuth2BundleTest**
  - Test service registration
  - Test repository functionality
  - Test route loader registration

## Functional Tests (To Be Implemented)
- [ ] Test actual OAuth flow with mock HTTP client
- [ ] Test controller actions
- [ ] Test command functionality

## Manual Testing Checklist
- [ ] Create QQ OAuth application and get credentials
- [ ] Configure bundle with real credentials
- [ ] Test login flow
- [ ] Test user info retrieval
- [ ] Test token refresh
- [ ] Test error handling

## Performance Tests
- [ ] State cleanup performance
- [ ] Large user dataset handling
- [ ] Token refresh under load

## Security Tests
- [ ] State validation
- [ ] CSRF protection
- [ ] Token storage security
- [ ] SQL injection prevention

## Coverage Goals
- Unit test coverage: > 80%
- Integration test coverage: > 60%
- Overall coverage: > 70%

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests/

# Run with coverage
vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests/ --coverage-html coverage/

# Run specific test suite
vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests/ --testsuite="Entity Tests"
```
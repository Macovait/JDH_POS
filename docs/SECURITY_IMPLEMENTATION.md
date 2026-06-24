# Role-Based Access Control Implementation - Complete Security System

## Overview

This document outlines the comprehensive role-based access control (RBAC) and data security system implemented for the JDH POS system. The implementation ensures strict tenant isolation, proper data scoping, and prevents any data leakage between users or companies.

## Security Components Implemented

### 1. Core Security Classes

#### DataAccessScope (`src/Security/DataAccessScope.php`)
- **Purpose**: Enforces tenant and branch-level data isolation
- **Features**:
  - Automatic tenant WHERE clause generation
  - Record-level access validation
  - Super admin bypass capabilities
  - Context-aware parameter binding

#### RoleBasedAccess (`src/Security/RoleBasedAccess.php`)
- **Purpose**: Manages permissions and role hierarchy
- **Features**:
  - Hierarchical role system (Super Admin → Admin → Manager → Supervisor → Cashier → Clerk → Viewer)
  - Wildcard permission support
  - Module-level access control
  - User editing permissions based on role level

#### AuditLogger (`src/Security/AuditLogger.php`)
- **Purpose**: Comprehensive audit trail system
- **Features**:
  - Data access logging
  - Security violation tracking
  - Permission check auditing
  - Compliance reporting

#### SecureQueryBuilder (`src/Security/SecureQueryBuilder.php`)
- **Purpose**: Secure database operations with automatic tenant scoping
- **Features**:
  - Automatic tenant context addition
  - Data access validation
  - Audit logging for all operations
  - Protection against data leakage

### 2. Middleware Implementation

#### ApiSecurityMiddleware (`src/Middleware/ApiSecurityMiddleware.php`)
- **Purpose**: Protects API endpoints with comprehensive security
- **Features**:
  - Authentication validation
  - Permission enforcement
  - CSRF protection
  - Rate limiting
  - Session integrity validation
  - API key authentication

#### TenantIsolationMiddleware (`src/Middleware/TenantIsolationMiddleware.php`)
- **Purpose**: Enforces multi-tenant data isolation
- **Features**:
  - Parameter tampering detection
  - File access validation
  - Database-level security
  - Tenant-specific upload directories

### 3. Security Bootstrap

#### SecurityBootstrap (`src/Security/SecurityBootstrap.php`)
- **Purpose**: Centralized security system initialization
- **Features**:
  - Single-point security initialization
  - Convenience functions for global access
  - System-wide security enforcement

### 4. Audit and Monitoring

#### AuditTrailManager (`src/Security/AuditTrailManager.php`)
- **Purpose**: Advanced audit and compliance system
- **Features**:
  - Database triggers for automatic logging
  - Security event monitoring
  - Compliance reporting
  - Retention policy management

#### DatabaseSecurityPatches (`src/Security/DatabaseSecurityPatches.php`)
- **Purpose**: Updates existing database queries for security
- **Features**:
  - Automatic query patching
  - Vulnerable pattern detection
  - Security improvement reporting

## Security Features

### 1. Tenant Isolation
- **Complete Data Separation**: Each tenant can only access their own data
- **Branch-Level Scoping**: Users are restricted to their assigned branches
- **Parameter Validation**: Prevents tenant ID tampering in requests
- **File Isolation**: Upload directories are separated by tenant

### 2. Role-Based Access Control
- **Hierarchical Roles**: 7-level role hierarchy with appropriate permissions
- **Granular Permissions**: Fine-grained permission system
- **Wildcard Support**: Permission patterns like `products.*`
- **Module Access**: Control access to entire system modules

### 3. Data Scoping
- **Automatic Filtering**: All queries automatically include tenant filters
- **Record Validation**: Users can only access records they're authorized to see
- **Secure Operations**: All CRUD operations enforce data boundaries
- **Query Builder**: Secure alternative to direct database queries

### 4. Audit and Compliance
- **Comprehensive Logging**: All data access and modifications are logged
- **Security Events**: Automatic detection and logging of security violations
- **Permission Auditing**: Track all permission checks and denials
- **Compliance Reports**: Generate detailed compliance documentation

### 5. API Security
- **Authentication Required**: All API endpoints require valid authentication
- **Permission Enforcement**: API calls respect user permissions
- **CSRF Protection**: State-changing operations require CSRF tokens
- **Rate Limiting**: Prevent abuse with configurable rate limits
- **Session Security**: Advanced session validation and fingerprinting

## Implementation Usage

### Basic Security Initialization
```php
// Initialize the complete security system
SecurityBootstrap::initialize();

// Secure an API endpoint
secure_api_endpoint('products.view');

// Check user permissions
if (has_permission('users.create')) {
    // Allow user creation
}

// Enforce permission (throws exception if denied)
enforce_permission('sales.delete');
```

### Secure Database Operations
```php
// Use secure query builder
$builder = new SecureQueryBuilder();
$users = $builder->select('users', ['id', 'name'], ['status' => 1]);

// Or use convenience functions
$products = secure_db_select('products', ['active' => 1]);
$user_id = secure_db_insert('users', $user_data);
```

### API Endpoint Protection
```php
// In API files
require_once __DIR__ . '/../src/Security/SecurityBootstrap.php';

// Secure with specific permission
secure_ajax_endpoint('api.sales.access');

// Apply rate limiting
apply_rate_limit('api_calls', 100, 60);
```

### Audit Logging
```php
// Log security events
log_security_event('data_access', ['table' => 'users', 'action' => 'select']);

// Get audit trail
$audit_manager = new AuditTrailManager();
$report = $audit_manager->generateComplianceReport('2024-01-01', '2024-12-31');
```

## Security Testing

### Test Suite
A comprehensive test suite is provided at `tests/SecurityTestSuite.php`:

```bash
# Run security tests
php tests/SecurityTestSuite.php
```

The test suite validates:
- Data access scope enforcement
- Role-based permission checks
- Tenant isolation mechanisms
- API security middleware
- Audit logging functionality
- Secure query builder operations

## Database Schema

### Audit Tables Created
1. **audit_logs** - General audit trail
2. **security_events** - Security violations and events
3. **data_access_logs** - Database operation logging
4. **permission_checks** - Permission validation tracking

### Security Triggers
Automatic database triggers for:
- Sales table modifications
- User account changes
- Product updates
- Inventory adjustments

## Configuration

### Session Security
- Secure session configuration with HttpOnly, SameSite, and Secure flags
- Session fingerprinting to prevent hijacking
- Automatic session regeneration
- Configurable timeout periods

### Rate Limiting
- Per-action rate limiting
- Configurable windows and limits
- IP and user-based tracking
- Automatic violation logging

### File Security
- Tenant-specific upload directories
- File access validation
- Path traversal prevention
- Secure file serving

## Migration Guide

### For Existing Code
1. **Initialize Security**: Add `SecurityBootstrap::initialize()` to your bootstrap
2. **Secure APIs**: Replace authentication checks with `secure_api_endpoint()`
3. **Update Queries**: Use `secure_db_*()` functions instead of direct queries
4. **Add Permissions**: Define required permissions for each operation
5. **Test Thoroughly**: Run the security test suite to validate implementation

### Database Updates
Run the security patches to update existing queries:
```php
$patches = new DatabaseSecurityPatches();
$patches->applyAllPatches();
```

## Compliance and Standards

This implementation addresses:
- **GDPR**: Data access logging and user rights
- **SOC 2**: Security monitoring and audit trails
- **ISO 27001**: Information security management
- **PCI DSS**: Payment card data protection (where applicable)

## Monitoring and Alerting

### Security Dashboard
- Real-time security event monitoring
- User activity tracking
- Permission denial analytics
- Data access patterns

### Automated Alerts
- Critical security violations
- Unusual access patterns
- Rate limit exceeded
- Multiple failed permission attempts

## Best Practices

1. **Always Use Security Functions**: Never bypass the security layer
2. **Principle of Least Privilege**: Grant minimum necessary permissions
3. **Regular Auditing**: Review audit logs and security reports
4. **Test Security**: Run security tests after any changes
5. **Monitor Violations**: Investigate all security violations
6. **Keep Updated**: Regularly update security components

## Support and Maintenance

### Regular Tasks
- Clean old audit logs (automated retention)
- Review security reports
- Update permission mappings
- Monitor system performance
- Test security updates

### Troubleshooting
- Check audit logs for security issues
- Use test suite for validation
- Monitor error logs for security exceptions
- Review permission configurations

This comprehensive security implementation ensures that users can only access data according to their role permissions, preventing any data leakage through proper scoping and tenant isolation.
